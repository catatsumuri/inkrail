<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ArticleFeedController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless(config('articles.public_index_enabled'), 404);
        $locale = $request->query('lang', config('app.locale'));
        abort_unless(is_string($locale) && in_array($locale, config('articles.locales'), true), 404);
        app()->setLocale($locale);

        $articles = Article::query()->with('namespace')->publiclyVisible()
            ->when(config('app.demo_mode'), fn (Builder $query): Builder => $query->whereRelation('user', 'is_showcase_owner', true))
            ->orderByRaw('COALESCE(published_at, created_at) DESC')->orderByDesc('id')->limit(20)->get();

        $items = $articles->map(function (Article $article) use ($locale): array {
            return [
                'title' => $article->title,
                'url' => route('public.articles.show', ['article' => $article->slug, 'lang' => $article->resolvedTranslation()->locale ?? $locale]),
                'description' => Str::limit(Str::squish(html_entity_decode(strip_tags(Str::markdown($article->content, ['html_input' => 'strip', 'allow_unsafe_links' => false])), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 160),
                'date' => ($article->published_at ?? $article->created_at)->toRssString(),
                'guid' => 'urn:inkrail:article:'.hash('sha256', $article->sync_id).':'.$locale,
            ];
        });

        return response()->view('articles.feed', [
            'items' => $items,
            'locale' => $locale,
            'title' => config('app.name'),
            'url' => route('public.articles.index'),
            'description' => __('Ideas, notes, and things worth sharing.'),
            'xml' => static fn (string $value): string => htmlspecialchars(
                preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '',
                ENT_XML1 | ENT_QUOTES, 'UTF-8',
            ),
        ])->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
