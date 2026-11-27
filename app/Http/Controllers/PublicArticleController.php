<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicArticleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(config('articles.public_index_enabled'), 404);

        $topic = $request->validate(['topic' => ['nullable', 'string', 'max:255']])['topic'] ?? null;
        $articles = Article::query()->publiclyVisible()
            ->when(config('app.demo_mode'), fn (Builder $query): Builder => $query->whereRelation('user', 'is_showcase_owner', true));
        $topics = (clone $articles)->without('translations')->select('zenn_frontmatter')->get()
            ->flatMap(fn (Article $article): array => $article->topics)->unique()->sort()->values()->all();

        return Inertia::render('articles/public-index', [
            'topics' => $topics,
            'selectedTopic' => $topic,
            'social' => $this->socialMetadata(
                __('Public articles'), __('Ideas, notes, and things worth sharing.'), 'website',
                route($request->routeIs('home') ? 'home' : 'public.articles.index', array_filter(['topic' => $topic, 'page' => $request->integer('page', 1) > 1 ? $request->integer('page') : null], fn (mixed $value): bool => $value !== null)),
            ),
            'articles' => $articles
                ->when($topic !== null, fn (Builder $query): Builder => $query->whereJsonContains('zenn_frontmatter->topics', $topic))
                ->select(['id', 'slug', 'zenn_frontmatter', 'published_at', 'created_at'])->latest('id')->paginate(20)->withQueryString()->through(fn (Article $article): array => [...$article->only(['title', 'slug', 'topics', 'eyecatch_url']), 'published_at' => $article->published_at ?? $article->created_at, ...$article->languageMetadata()]),
        ]);
    }

    public function show(Request $request, Article $article): Response
    {
        $forcedLocale = $this->preparePublicArticle($request, $article);

        return Inertia::render('articles/public-show', [
            'social' => $this->socialMetadata(
                $article->title,
                Str::limit(Str::squish(html_entity_decode(strip_tags(Str::markdown($article->content, ['html_input' => 'strip', 'allow_unsafe_links' => false])), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 160),
                'article', route('public.articles.show', array_filter(['article' => $article->slug, 'lang' => $forcedLocale])),
                $article->eyecatch_url === null ? null : url($article->eyecatch_url),
            ),
            'article' => [...$article->only(['title', 'content', 'slug', 'topics', 'eyecatch_url']), 'published_at' => $article->published_at ?? $article->created_at, ...$article->languageMetadata()],
            'editableArticleId' => $request->user()?->can('update', $article) ? $article->id : null,
            'markdownDownloadEnabled' => (bool) config('articles.markdown_download_enabled'),
            'publicIndexEnabled' => (bool) config('articles.public_index_enabled'),
        ]);
    }

    public function markdown(Request $request, Article $article): StreamedResponse
    {
        abort_unless(config('articles.markdown_download_enabled'), 404);
        $this->preparePublicArticle($request, $article);
        $content = $article->content;

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $article->slug.'.md', [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    private function preparePublicArticle(Request $request, Article $article): ?string
    {
        abort_unless($article->publicationStatus() === 'published' && (! config('app.demo_mode') || $article->user->is_showcase_owner), 404);

        $forcedLocale = $request->validate(['lang' => ['nullable', Rule::in(config('articles.locales'))]])['lang'] ?? null;
        if ($forcedLocale !== null) {
            app()->setLocale($forcedLocale);
        }

        return $forcedLocale;
    }
}
