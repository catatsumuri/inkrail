<?php

namespace App\Http\Controllers;

use App\Actions\Backup\RestoreArticleBackup;
use App\Actions\LogSecurityEvent;
use App\Support\ArticleBackupStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ArticleBackupController extends Controller
{
    public function __construct(
        private ArticleBackupStore $store,
        private LogSecurityEvent $logSecurityEvent,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-article-backups');

        try {
            $result = $this->store->create();
        } catch (Throwable $exception) {
            report($exception);
            $this->logSecurityEvent->failure('articles.backup_failed', $request->user());
            Inertia::flash('toast', ['type' => 'error', 'message' => __('The backup could not be created.')]);

            return to_route('dashboard');
        }

        ($this->logSecurityEvent)('articles.backup_created', $request->user(), ['filename' => $result['filename']]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Backup created.')]);

        return to_route('dashboard');
    }

    public function upload(Request $request): RedirectResponse
    {
        Gate::authorize('manage-article-backups');

        $data = $request->validate([
            'backup' => ['required', 'file', 'extensions:zip', 'max:102400'],
        ]);

        $path = $data['backup']->getRealPath();

        try {
            app(RestoreArticleBackup::class)->manifest($path);
        } catch (RuntimeException) {
            $this->logSecurityEvent->failure('articles.backup_upload_rejected', $request->user());

            throw ValidationException::withMessages(['backup' => __('The file is not a valid InkRail backup.')]);
        }

        $filename = $this->store->put($path);

        ($this->logSecurityEvent)('articles.backup_uploaded', $request->user(), ['filename' => $filename]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Backup uploaded.')]);

        return to_route('dashboard');
    }

    public function download(Request $request, string $filename): StreamedResponse
    {
        Gate::authorize('manage-article-backups');
        abort_unless($this->store->exists($filename), 404);

        ($this->logSecurityEvent)('articles.backup_downloaded', $request->user(), ['filename' => $filename]);

        return $this->store->download($filename);
    }

    public function restore(Request $request, string $filename, RestoreArticleBackup $restore): RedirectResponse
    {
        Gate::authorize('manage-article-backups');
        abort_unless($this->store->exists($filename), 404);

        $request->validate(['confirmation' => ['required', 'string', Rule::in(['restore'])]]);

        $temporary = null;

        try {
            $temporary = $this->store->copyToTemporaryFile($filename);
            $result = $restore($temporary, $request->user());
        } catch (RuntimeException $exception) {
            $this->logSecurityEvent->failure('articles.backup_restore_failed', $request->user(), ['filename' => $filename]);

            throw ValidationException::withMessages(['backup' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);
            $this->logSecurityEvent->failure('articles.backup_restore_failed', $request->user(), ['filename' => $filename]);

            throw ValidationException::withMessages(['backup' => __('The backup could not be restored.')]);
        } finally {
            if ($temporary !== null) {
                @unlink($temporary);
            }
        }

        ($this->logSecurityEvent)('articles.backup_restored', $request->user(), ['filename' => $filename, 'articles' => $result['articles']]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Restored :count article(s) from the backup.', ['count' => $result['articles']])]);

        return to_route('dashboard');
    }

    public function destroy(Request $request, string $filename): RedirectResponse
    {
        Gate::authorize('manage-article-backups');
        abort_unless($this->store->exists($filename), 404);

        $this->store->delete($filename);

        ($this->logSecurityEvent)('articles.backup_deleted', $request->user(), ['filename' => $filename]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Backup deleted.')]);

        return to_route('dashboard');
    }
}
