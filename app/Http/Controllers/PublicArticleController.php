<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicArticleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(config('articles.public_index_enabled'), 404);

        return Inertia::render('articles/public-index', [
            'social' => $this->socialMetadata(
                __('Public articles'), __('Ideas, notes, and things worth sharing.'), 'website',
                route('public.articles.index', $request->integer('page', 1) > 1 ? ['page' => $request->integer('page')] : []),
            ),
            'articles' => Article::query()->publiclyVisible()
                ->when(config('app.demo_mode'), fn (Builder $query): Builder => $query->whereRelation('user', 'is_showcase_owner', true))
                ->select(['id', 'slug', 'zenn_frontmatter', 'published_at', 'created_at'])->orderBy('id')->paginate(20)->through(fn (Article $article): array => [...$article->only(['title', 'slug', 'topics', 'eyecatch_url']), 'published_at' => $article->published_at ?? $article->created_at, ...$article->languageMetadata()]),
        ]);
    }

    public function show(Request $request, Article $article): Response
    {
        abort_unless($article->publicationStatus() === 'published' && (! config('app.demo_mode') || $article->user->is_showcase_owner), 404);

        return Inertia::render('articles/public-show', [
            'social' => $this->socialMetadata(
                $article->title,
                Str::limit(Str::squish(html_entity_decode(strip_tags(Str::markdown($article->content, ['html_input' => 'strip', 'allow_unsafe_links' => false])), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 160),
                'article', route('public.articles.show', $article->slug),
                $article->eyecatch_url === null ? null : url($article->eyecatch_url),
            ),
            'article' => [...$article->only(['title', 'content', 'slug', 'topics', 'eyecatch_url']), 'published_at' => $article->published_at ?? $article->created_at, ...$article->languageMetadata()],
            'editableArticleId' => $request->user()?->can('update', $article) ? $article->id : null,
            'publicIndexEnabled' => (bool) config('articles.public_index_enabled'),
        ]);
    }

    /** @return array{title: string, url: string, meta: array<string, string>} */
    private function socialMetadata(string $title, string $description, string $type, string $url, ?string $image = null): array
    {
        $meta = [
            'description' => $description,
            'og:title' => $title,
            'og:description' => $description,
            'og:type' => $type,
            'og:url' => $url,
            'og:site_name' => (string) config('app.name'),
            'twitter:card' => $image === null ? 'summary' : 'summary_large_image',
            'twitter:title' => $title,
            'twitter:description' => $description,
        ];
        if ($image !== null) {
            $meta += ['og:image' => $image, 'og:image:alt' => $title, 'twitter:image' => $image, 'twitter:image:alt' => $title];
        }

        return ['title' => $title, 'url' => $url, 'meta' => $meta];
    }
}
