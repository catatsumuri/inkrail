<?php

namespace App\Http\Controllers;

use App\Actions\LogSecurityEvent;
use App\Actions\Zenn\ImportZennArticles;
use App\Exceptions\ZennImportException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ZennImportController extends Controller
{
    public function __invoke(Request $request, ImportZennArticles $import, LogSecurityEvent $logSecurityEvent): RedirectResponse|StreamedResponse
    {
        Gate::authorize('import-from-zenn');

        if ($request->header('Accept') === 'text/event-stream') {
            return response()->stream(function () use ($request, $import, $logSecurityEvent): void {
                ignore_user_abort(true);
                $emit = function (string $event, array $data): void {
                    echo 'event: '.$event."\n".'data: '.json_encode([...$data, 'time' => now()->toIso8601String()], JSON_THROW_ON_ERROR)."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                };
                try {
                    $result = $import($request->user(), $emit);
                    $emit('result', ['status' => 'imported', 'message' => 'Import finished.', 'result' => $result]);
                } catch (\Throwable $exception) {
                    report($exception);
                    $logSecurityEvent->failure('articles.zenn_import_failed', $request->user());
                    $emit('result', ['status' => 'failed', 'message' => $exception->getPrevious() instanceof LockTimeoutException ? 'The repository is busy or the sync lock was lost. Try again shortly.' : 'The articles could not be imported from GitHub.']);
                }
            }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache, no-store', 'X-Accel-Buffering' => 'no']);
        }

        try {
            $result = $import($request->user());
        } catch (ZennImportException $exception) {
            report($exception);
            $logSecurityEvent->failure('articles.zenn_import_failed', $request->user());
            Inertia::flash('toast', ['type' => 'error', 'message' => __($exception->getPrevious() instanceof LockTimeoutException
                ? 'The repository is busy or the sync lock was lost. Try again shortly.'
                : 'The articles could not be imported from GitHub.')]);

            return to_route('dashboard');
        }

        $request->session()->flash('zenn_import', $result);

        return to_route('dashboard');
    }
}
