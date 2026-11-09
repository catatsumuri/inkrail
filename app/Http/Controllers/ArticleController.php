<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('articles/index', [
            'articles' => $request->user()->articles()
                ->select(['id', 'title'])->latest('id')->paginate(20),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('articles/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $article = $request->user()->articles()->create($this->validated($request));

        return to_route('articles.show', $article);
    }

    public function show(Article $article): Response
    {
        Gate::authorize('view', $article);

        return Inertia::render('articles/show', ['article' => $article->only(['id', 'title', 'content'])]);
    }

    public function edit(Article $article): Response
    {
        Gate::authorize('update', $article);

        return Inertia::render('articles/edit', ['article' => $article->only(['id', 'title', 'content'])]);
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        Gate::authorize('update', $article);
        $article->update($this->validated($request));

        return to_route('articles.show', $article);
    }

    public function destroy(Article $article): RedirectResponse
    {
        Gate::authorize('delete', $article);
        $article->delete();

        return to_route('articles.index');
    }

    /** @return array{title: string, content: string} */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ]);

        return ['title' => $validated['title'], 'content' => $validated['content'] ?? ''];
    }
}
