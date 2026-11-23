<?php

namespace App\Http\Controllers;

use App\Actions\LogSecurityEvent;
use App\Actions\Zenn\PushArticleToZenn;
use App\Exceptions\ZennPushException;
use App\Models\Article;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ZennPushController extends Controller
{
    public function __invoke(Article $article, PushArticleToZenn $push, LogSecurityEvent $logSecurityEvent, Request $request): RedirectResponse|StreamedResponse
    {
        Gate::authorize('push-to-zenn', $article);

        if ($request->header('Accept') === 'text/event-stream') {
            return response()->stream(function () use ($article, $push, $logSecurityEvent): void {
                ignore_user_abort(true);
                $emit = function (string $event, array $data): void {
                    echo 'event: '.$event."\n".'data: '.json_encode([...$data, 'time' => now()->toIso8601String()], JSON_THROW_ON_ERROR)."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                };
                try {
                    $result = $push($article, $emit);
                    $logSecurityEvent('article.zenn_pushed', $article, ['zenn_slug' => $result['zennSlug'], 'changed' => $result['changed'], 'published' => $article->is_published]);
                    $emit('result', ['status' => $result['changed'] ? 'saved' : 'unchanged', 'message' => $result['changed'] ? 'Saved to GitHub.' : 'The GitHub repository is already up to date.']);
                } catch (\Throwable $exception) {
                    report($exception);
                    $logSecurityEvent->failure('article.zenn_push_failed', $article);
                    $emit('result', ['status' => 'failed', 'message' => $exception->getPrevious() instanceof LockTimeoutException ? 'The repository is busy or the sync lock was lost. Try again shortly.' : 'The article could not be saved to GitHub.']);
                }
            }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache, no-store', 'X-Accel-Buffering' => 'no']);
        }

        try {
            $result = $push($article);
        } catch (ZennPushException $exception) {
            report($exception);
            $logSecurityEvent->failure('article.zenn_push_failed', $article);
            Inertia::flash('toast', ['type' => 'error', 'message' => __($exception->getPrevious() instanceof LockTimeoutException
                ? 'The repository is busy or the sync lock was lost. Try again shortly.'
                : 'The article could not be saved to GitHub.')]);

            return back();
        }

        $logSecurityEvent('article.zenn_pushed', $article, [
            'zenn_slug' => $result['zennSlug'],
            'changed' => $result['changed'],
            'published' => $article->is_published,
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __($result['changed'] ? 'Saved to GitHub.' : 'The GitHub repository is already up to date.')]);

        return back();
    }
}
