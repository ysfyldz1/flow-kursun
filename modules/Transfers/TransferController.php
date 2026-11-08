<?php

declare(strict_types=1);

namespace Modules\Transfers;

use App\Core\ValidationException;
use App\Core\View;
use App\Services\Settings;
use App\Services\TransferService;
use Throwable;

final class TransferController
{
    public function __construct()
    {
        if (!can('transfers.own') && !can('transfers.all')) {
            abort(403);
        }
    }

    public function index(): void
    {
        View::render('Transfers::index', [
            'title' => __('transfers.title'),
            'rows'  => TransferService::list(),
        ]);
    }

    public function create(): void
    {
        View::render('Transfers::create', [
            'title'  => __('transfers.new'),
            'locale' => (string) Settings::get('org.locale', 'tr'),
        ]);
    }

    public function show(string $id): void
    {
        $t = $this->load($id);
        View::render('Transfers::show', [
            'title'     => $t['title'],
            't'         => $t,
            'files'     => TransferService::files((int) $t['id']),
            'downloads' => TransferService::downloads((int) $t['id']),
        ]);
    }

    public function store(): void
    {
        $this->json(function () {
            [$id, $fileIds] = TransferService::create($_POST, json_decode((string) ($_POST['files'] ?? ''), true) ?: []);
            return ['id' => $id, 'file_ids' => $fileIds];
        });
    }

    public function session(string $id): void
    {
        $this->json(function () use ($id) {
            $t = $this->uploading($id);
            return ['upload_url' => TransferService::uploadUrl($t, $this->uploadFile($t))];
        });
    }

    public function fileDone(string $id): void
    {
        $this->json(function () use ($id) {
            $t = $this->uploading($id);
            TransferService::confirmFile($t, $this->uploadFile($t));
            return ['ok' => true];
        });
    }

    public function finalize(string $id): void
    {
        $this->json(function () use ($id) {
            $t = $this->uploading($id);
            return ['url' => TransferService::publicUrl($t), 'id' => (int) $t['id'], 'emailed' => TransferService::finalize($t)];
        });
    }

    public function expiry(string $id): void
    {
        TransferService::setExpiry($this->load($id), (string) ($_POST['expiry'] ?? ''));
        flash('success', __('app.saved'));
        redirect("/transfers/$id");
    }

    public function resend(string $id): void
    {
        $t = $this->load($id);
        if ($t['status'] === 'ready' && TransferService::sendToRecipients($t)) {
            flash('success', __('transfers.resent'));
        } else {
            flash('error', __('transfers.resend_failed'));
        }
        redirect("/transfers/$id");
    }

    public function delete(string $id): void
    {
        try {
            TransferService::delete($this->load($id));
        } catch (Throwable $e) {
            flash('error', __('transfers.delete_failed', ['error' => $e->getMessage()]));
            redirect("/transfers/$id");
        }
        flash('success', __('transfers.deleted'));
        redirect('/transfers');
    }

    private function load(string $id): array
    {
        $t = TransferService::find((int) $id) ?? abort(404);
        if (!TransferService::canSee($t)) {
            abort(404);
        }
        return $t;
    }

    /** Yükleme uçları yalnız transferi başlatan kişi içindir. */
    private function uploading(string $id): array
    {
        $t = TransferService::find((int) $id);
        if (!$t || $t['status'] !== 'uploading' || (int) $t['created_by'] !== (int) auth()['id']) {
            json_response(['error' => __('transfers.not_found')], 404);
        }
        return $t;
    }

    private function uploadFile(array $t): array
    {
        return TransferService::file($t, (int) ($_POST['file_id'] ?? 0)) ?? json_response(['error' => __('transfers.not_found')], 404);
    }

    private function json(callable $fn): never
    {
        try {
            json_response($fn());
        } catch (ValidationException $e) {
            json_response(['error' => (string) reset($e->errors)], 422);
        } catch (Throwable $e) {
            json_response(['error' => $e->getMessage()], 502);
        }
    }
}
