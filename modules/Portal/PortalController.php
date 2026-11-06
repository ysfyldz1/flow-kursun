<?php

declare(strict_types=1);

namespace Modules\Portal;

use App\Core\PortalAuth;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\PortalService;
use App\Services\StatementPdf;
use App\Services\StatementService;

/** Müvekkilin gördüğü ekranlar. Her sorgu PortalAuth::clientId() ile sınırlanır. */
final class PortalController
{
    public function showLogin(): void
    {
        if (PortalAuth::check()) {
            redirect('/portal');
        }
        View::render('Portal::login', ['title' => __('portal.title')], 'portal');
    }

    public function login(): void
    {
        $email = (string) ($_POST['email'] ?? '');
        $lock = $_SESSION['_portal_lock'] ?? ['count' => 0, 'until' => 0];
        if ($lock['until'] > time()) {
            flash('error', __('auth.throttled'));
            $_SESSION['_old'] = ['email' => $email];
            redirect('/portal/login');
        }
        if (!PortalAuth::attempt($email, (string) ($_POST['password'] ?? ''))) {
            $lock['count']++;
            if ($lock['count'] >= 5) {
                $lock = ['count' => 0, 'until' => time() + 300];
            }
            $_SESSION['_portal_lock'] = $lock;
            flash('error', __('auth.failed'));
            $_SESSION['_old'] = ['email' => $email];
            redirect('/portal/login');
        }
        unset($_SESSION['_portal_lock']);
        redirect('/portal');
    }

    public function logout(): void
    {
        PortalAuth::logout();
        redirect('/portal/login');
    }

    public function showInvite(string $token): void
    {
        $user = PortalService::findByToken($token);
        if ($user) {
            \App\Core\Lang::set($user['locale']);
        }
        View::render('Portal::invite', ['title' => __('portal.title'), 'user' => $user, 'token' => $token], 'portal');
    }

    public function acceptInvite(string $token): void
    {
        try {
            $user = PortalService::acceptInvite($token, (string) ($_POST['password'] ?? ''), (string) ($_POST['password_confirm'] ?? ''));
        } catch (ValidationException $e) {
            fail($e, "/portal/invite/$token");
        }
        PortalAuth::login(\App\Core\DB::one('SELECT * FROM portal_users WHERE id = ?', [$user['id']]));
        flash('success', __('portal.welcome_done'));
        redirect('/portal');
    }

    public function index(): void
    {
        $clientId = PortalAuth::clientId();
        View::render('Portal::index', [
            'title'      => __('portal.title'),
            'balances'   => PortalService::balances($clientId),
            'statements' => PortalService::statements($clientId),
            'projects'   => PortalService::projects($clientId),
            'showHours'  => PortalService::showHours(),
        ], 'portal');
    }

    public function project(string $id): void
    {
        $p = PortalService::project(PortalAuth::clientId(), (int) $id) ?? abort(404);
        $docs = \App\Services\DocumentService::enabled()
            ? \App\Core\DB::all('SELECT * FROM document_shares WHERE project_id = ? ORDER BY created_at DESC', [$p['id']])
            : null;
        View::render('Portal::project', ['title' => $p['name'], 'p' => $p, 'showHours' => PortalService::showHours(), 'docs' => $docs], 'portal');
    }

    public function statement(string $id): void
    {
        $s = PortalService::statement(PortalAuth::clientId(), (int) $id) ?? abort(404);
        View::render('Portal::statement', ['title' => $s['number'], 's' => $s], 'portal');
    }

    public function pdf(string $id): void
    {
        $s = PortalService::statement(PortalAuth::clientId(), (int) $id) ?? abort(404);
        $pdf = StatementPdf::render($s);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . StatementPdf::filename($s) . '"');
        echo $pdf;
    }

    public function invoice(string $id): void
    {
        $s = PortalService::statement(PortalAuth::clientId(), (int) $id) ?? abort(404);
        $path = StatementService::invoicePath($s) ?? abort(404);
        header('Content-Type: ' . mime_content_type($path));
        header('Content-Disposition: inline; filename="' . ($s['invoice_number'] ?: $s['number']) . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
        readfile($path);
    }

    public function notice(string $id): void
    {
        try {
            PortalService::submitNotice((int) $id, $_POST, $_FILES['receipt'] ?? null);
        } catch (ValidationException $e) {
            fail($e, "/portal/statements/$id");
        }
        flash('success', __('portal.notice_sent'));
        redirect("/portal/statements/$id");
    }
}
