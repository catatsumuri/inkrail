<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ArticleController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('articles/index', [
            'articles' => $request->user()->articles()
                ->select(['id', 'slug', 'is_published'])->latest('id')->paginate(20)->through(fn (Article $article): array => [...$article->only(['id', 'title', 'slug', 'is_published']), ...$article->languageMetadata()]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('articles/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->validated($request);
        $article = DB::transaction(function () use ($request, $attributes): Article {
            $translations = $attributes['translations'] ?? null;
            unset($attributes['translations']);
            $article = $request->user()->articles()->create($attributes);
            if ($translations !== null) {
                $article->replaceTranslations($translations);
            }

            return $article;
        });

        return to_route('articles.show', $article);
    }

    public function show(Article $article): Response
    {
        Gate::authorize('view', $article);

        return Inertia::render('articles/show', ['article' => [...$article->only(['id', 'title', 'content', 'slug', 'is_published']), ...$article->languageMetadata(), 'translations' => $article->translations->mapWithKeys(fn ($translation) => [$translation->locale => $translation->only(['title', 'content'])])->all()]]);
    }

    public function edit(Article $article): Response
    {
        Gate::authorize('update', $article);

        return Inertia::render('articles/edit', ['article' => [...$article->only(['id', 'title', 'content', 'slug', 'is_published']), ...$article->languageMetadata(), 'translations' => $article->translations->mapWithKeys(fn ($translation) => [$translation->locale => $translation->only(['title', 'content'])])->all()]]);
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        Gate::authorize('update', $article);
        $attributes = $this->validated($request, $article);
        DB::transaction(function () use ($article, $attributes): void {
            $translations = $attributes['translations'] ?? null;
            unset($attributes['translations']);
            $article->update($attributes);
            if ($translations !== null) {
                $article->replaceTranslations($translations);
            }
        });

        return to_route('articles.show', $article);
    }

    public function destroy(Article $article): RedirectResponse
    {
        Gate::authorize('delete', $article);
        $article->delete();

        return to_route('articles.index');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Article $article = null): array
    {
        $validated = $request->validate([
            'title' => ['required_without:translations', 'string', 'max:255'],
            'translations' => ['sometimes', 'required', 'array:'.implode(',', config('articles.locales'))],
            'translations.*' => ['array:title,content'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.content' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', Rule::unique(Article::class, 'slug')->ignore($article)],
            'is_published' => ['sometimes', 'boolean', ...($request->user()->is_sandbox ? [Rule::in([false, 0, '0'])] : [])],
        ]);

        $attributes = [
            'is_published' => $request->has('is_published') ? $request->boolean('is_published') : ($article->is_published ?? false),
        ];

        if (array_key_exists('translations', $validated)) {
            $translations = [];
            foreach ($validated['translations'] as $locale => $translation) {
                $title = trim($translation['title'] ?? '');
                $content = $translation['content'] ?? '';
                if ($title === '' && trim($content) === '') {
                    continue;
                }
                if ($title === '') {
                    throw ValidationException::withMessages(["translations.$locale.title" => __('validation.required', ['attribute' => __('Title')])]);
                }
                $translations[$locale] = ['title' => $title, 'content' => $content];
            }
            if ($translations === []) {
                throw ValidationException::withMessages(['translations' => __('validation.required', ['attribute' => 'translations'])]);
            }
            $attributes['translations'] = $translations;
        } else {
            $attributes['title'] = $validated['title'];
            $attributes['content'] = $validated['content'] ?? '';
        }

        if (isset($validated['slug'])) {
            $attributes['slug'] = $validated['slug'];
        }

        return $attributes;
    }
}
