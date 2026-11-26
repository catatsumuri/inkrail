<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\ArticleTranslationReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ArticleHistoryController extends Controller
{
    public function index(Request $request, Article $article): Response
    {
        Gate::authorize('view', $article);
        $input = $request->validate(['locale' => ['sometimes', 'in:en,ja'], 'from' => ['nullable', 'integer'], 'to' => ['nullable', 'integer']]);
        $locale = $article->isSandbox() ? 'ja' : ($input['locale'] ?? app()->getLocale());
        $revisions = ArticleRevision::where('article_id', $article->id)->where('locale', $locale);
        $selection = [];
        foreach (['from', 'to'] as $key) {
            $selection[$key] = isset($input[$key]) ? (clone $revisions)->findOrFail($input[$key])->only(['id', 'title', 'content', 'is_deleted', 'created_at']) : null;
        }

        return Inertia::render('articles/history', [
            'article' => [...$article->only(['id', 'title', 'edit_version']), 'is_sandbox' => $article->isSandbox(), 'translationStatus' => $article->translationStatus()],
            'locale' => $locale,
            'revisions' => $revisions->latest('id')->select(['id', 'title', 'is_deleted', 'created_at', 'user_id', 'restored_from_id'])->paginate(20)->withQueryString(),
            'selection' => $selection,
            'translations' => $article->translations->mapWithKeys(fn ($translation) => [$translation->locale => $translation->only(['title', 'content'])]),
        ]);
    }

    public function confirm(Request $request, Article $article): RedirectResponse
    {
        abort_if($article->isSandbox(), 403);
        $this->mutate($request, $article, function (Article $locked): void {
            $translations = $locked->translations()->get()->keyBy('locale');
            if (! isset($translations['en'], $translations['ja'])) {
                throw ValidationException::withMessages(['history' => __('Both languages must exist before confirming their correspondence.')]);
            }
            ArticleTranslationReview::create(['article_id' => $locked->id, 'en_revision_id' => $translations['en']->current_revision_id, 'ja_revision_id' => $translations['ja']->current_revision_id, 'user_id' => auth()->id()]);
            $locked->increment('edit_version');
        });

        return back();
    }

    public function restore(Request $request, Article $article, ArticleRevision $revision): RedirectResponse
    {
        $this->mutate($request, $article, function (Article $locked) use ($revision): void {
            abort_if($locked->isSandbox() && $revision->locale !== 'ja', 403);
            abort_unless($revision->article_id === $locked->id && ! $revision->is_deleted, 404);
            $translation = $locked->translations()->withTrashed()->firstOrNew(['locale' => $revision->locale]);
            if (! $translation->trashed() && $translation->title === $revision->title && $translation->content === $revision->content) {
                return;
            }
            $translation->fill($revision->only(['title', 'content']));
            $translation->deleted_at = null;
            $translation->saveQuietly();
            $translation->recordRevision($revision->id);
        });

        return back();
    }

    public function destroy(Request $request, Article $article, string $translationLocale): RedirectResponse
    {
        $this->mutate($request, $article, function (Article $locked) use ($translationLocale): void {
            $translation = $locked->translations()->where('locale', $translationLocale)->firstOrFail();
            if ($locked->translations()->count() <= 1) {
                throw ValidationException::withMessages(['history' => __('The last language version cannot be deleted.')]);
            }
            $translation->delete();
        });

        return back();
    }

    private function mutate(Request $request, Article $article, \Closure $operation): void
    {
        Gate::authorize('update', $article);
        $input = $request->validate(['edit_version' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($article, $input, $operation): void {
            $locked = Article::whereKey($article->id)->lockForUpdate()->firstOrFail();
            if ($locked->edit_version !== (int) $input['edit_version']) {
                throw ValidationException::withMessages(['history' => __('This article has changed. Reload before continuing.')]);
            }
            $operation($locked);
        });
    }
}
