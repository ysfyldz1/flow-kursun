<?php

declare(strict_types=1);

namespace Modules\Portal;

use App\Core\DB;
use App\Core\ValidationException;
use App\Services\Mailer;
use App\Services\PortalService;

/** Ofis tarafı: müvekkile portal erişimi verme ve ödeme bildirimlerini işleme. */
final class PortalAccessController
{
    public function invite(string $id): void
    {
        try {
            $token = PortalService::invite((int) $id, [
                'name'       => $_POST['portal_name'] ?? '',
                'email'      => $_POST['portal_email'] ?? '',
                'contact_id' => $_POST['contact_id'] ?? null,
            ]);
        } catch (ValidationException $e) {
            fail($e, "/clients/$id#portal");
        }
        $pu = DB::one(
            'SELECT pu.name, pu.email, pu.locale, c.name AS client_name
             FROM portal_users pu JOIN clients c ON c.id = pu.client_id
             WHERE pu.client_id = ? AND pu.email = ?',
            [(int) $id, mb_strtolower(trim((string) ($_POST['portal_email'] ?? '')))]
        );
        $url = PortalService::inviteUrl($token);
        $sent = Mailer::delivers() && Mailer::sendView($pu['email'], $pu['locale'], 'portal_invite', [
            'name'   => $pu['name'],
            'client' => $pu['client_name'],
            'url'    => $url,
            'days'   => PortalService::INVITE_DAYS,
        ]);

        if ($sent) {
            flash('success', __('portal.invite_sent', ['email' => $pu['email']]));
        } else {
            // E-posta gitmediyse bağlantı bir kez gösterilir, elle iletilir
            $_SESSION['_portal_invite_link'] = $url;
            flash('success', __(Mailer::delivers() ? 'portal.invite_mail_failed' : 'portal.invite_created'));
        }
        redirect("/clients/$id#portal");
    }

    public function toggle(string $id, string $user): void
    {
        $u = DB::one('SELECT * FROM portal_users WHERE id = ? AND client_id = ?', [(int) $user, (int) $id]) ?? abort(404);
        PortalService::setActive((int) $id, (int) $user, !$u['is_active']);
        flash('success', __('app.saved'));
        redirect("/clients/$id#portal");
    }

    public function approve(string $id, string $notice): void
    {
        try {
            PortalService::approveNotice((int) $id, (int) $notice, ['amount' => $_POST['amount'] ?? null]);
        } catch (ValidationException $e) {
            fail($e, "/billing/$id");
        }
        flash('success', __('portal.notice_approved_msg'));
        redirect("/billing/$id");
    }

    public function reject(string $id, string $notice): void
    {
        try {
            PortalService::rejectNotice((int) $id, (int) $notice, (string) ($_POST['reject_reason'] ?? ''));
        } catch (ValidationException $e) {
            fail($e, "/billing/$id");
        }
        flash('success', __('portal.notice_rejected_msg'));
        redirect("/billing/$id");
    }

    public function receipt(string $id, string $notice): void
    {
        $n = DB::one('SELECT * FROM payment_notices WHERE id = ? AND statement_id = ?', [(int) $notice, (int) $id]) ?? abort(404);
        $path = PortalService::receiptPath($n) ?? abort(404);
        header('Content-Type: ' . mime_content_type($path));
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        readfile($path);
    }
}
