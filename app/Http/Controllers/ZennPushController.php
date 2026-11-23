<?php

namespace App\Http\Controllers;

use App\Actions\LogSecurityEvent;
use App\Actions\Zenn\PushArticleToZenn;
use App\Exceptions\ZennPushException;
use App\Models\Article;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ZennPushController extends Controller
{
    public function __invoke(Article $article, PushArticleToZenn $push, LogSecurityEvent $logSecurityEvent): RedirectResponse
    {
        Gate::authorize('push-to-zenn', $article);

        try {
            $result = $push($article);
        } catch (ZennPushException $exception) {
            report($exception);
            $logSecurityEvent->failure('article.zenn_push_failed', $article);
            Inertia::flash('toast', ['type' => 'error', 'message' => __($exception->getPrevious() instanceof LockTimeoutException
                ? 'The repository is busy or the sync lock was lost. Try again shortly.'
                : 'The article could not be sent to Zenn.')]);

            return back();
        }

        $logSecurityEvent('article.zenn_pushed', $article, [
            'zenn_slug' => $result['zennSlug'],
            'changed' => $result['changed'],
            'published' => $article->is_published,
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __($result['changed'] ? 'Sent to Zenn.' : 'Zenn is already up to date.')]);

        return back();
    }
}
