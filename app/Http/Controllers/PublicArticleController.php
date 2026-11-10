<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicArticleController extends Controller
{
    public function index(): Response
    {
        abort_unless(config('articles.public_index_enabled'), 404);

        return Inertia::render('articles/public-index', [
            'articles' => Article::query()->where('is_published', true)
                ->when(config('app.demo_mode'), fn (Builder $query): Builder => $query->whereRelation('user', 'is_showcase_owner', true))
                ->select(['title', 'slug'])->orderBy('id')->paginate(20),
        ]);
    }

    public function show(Request $request, Article $article): Response
    {
        abort_unless($article->is_published && (! config('app.demo_mode') || $article->user->is_showcase_owner), 404);

        return Inertia::render('articles/public-show', [
            'article' => $article->only(['title', 'content', 'slug']),
            'editableArticleId' => $request->user()?->can('update', $article) ? $article->id : null,
            'publicIndexEnabled' => (bool) config('articles.public_index_enabled'),
        ]);
    }
}
