<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('articles/index', [
            'articles' => $request->user()->articles()
                ->select(['id', 'title', 'slug', 'is_published'])->latest('id')->paginate(20),
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

        return Inertia::render('articles/show', ['article' => $article->only(['id', 'title', 'content', 'slug', 'is_published'])]);
    }

    public function edit(Article $article): Response
    {
        Gate::authorize('update', $article);

        return Inertia::render('articles/edit', ['article' => $article->only(['id', 'title', 'content', 'slug', 'is_published'])]);
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        Gate::authorize('update', $article);
        $article->update($this->validated($request, $article));

        return to_route('articles.show', $article);
    }

    public function destroy(Article $article): RedirectResponse
    {
        Gate::authorize('delete', $article);
        $article->delete();

        return to_route('articles.index');
    }

    /** @return array{title: string, content: string, slug?: string, is_published: bool} */
    private function validated(Request $request, ?Article $article = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', Rule::unique(Article::class, 'slug')->ignore($article)],
            'is_published' => ['sometimes', 'boolean', ...($request->user()->is_sandbox ? [Rule::in([false, 0, '0'])] : [])],
        ]);

        $attributes = [
            'title' => $validated['title'],
            'content' => $validated['content'] ?? '',
            'is_published' => $request->has('is_published') ? $request->boolean('is_published') : ($article->is_published ?? false),
        ];

        if (isset($validated['slug'])) {
            $attributes['slug'] = $validated['slug'];
        }

        return $attributes;
    }
}
