<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleNamespace;
use App\Support\ArticleEyecatch;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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
        $topic = $request->validate(['topic' => ['nullable', 'string', 'max:255']])['topic'] ?? null;
        $articles = $request->user()->articles()->getQuery();
        $namespace = $request->validate(['namespace' => ['nullable', 'in:sandbox']])['namespace'] ?? null;
        $articles->when($namespace === 'sandbox', fn (Builder $query): Builder => $query->whereHas('namespace', fn (Builder $query): Builder => $query->where('slug', 'sandbox')));
        $topics = (clone $articles)->without('translations')->select('zenn_frontmatter')->get()
            ->flatMap(fn (Article $article): array => $article->topics)->unique()->sort()->values()->all();

        return Inertia::render('articles/index', [
            'selectedNamespace' => $namespace,
            'sandboxImport' => $request->session()->get('sandbox_import'),
            'topics' => $topics,
            'selectedTopic' => $topic,
            'articles' => $articles
                ->when($topic !== null, fn (Builder $query): Builder => $query->whereJsonContains('zenn_frontmatter->topics', $topic))
                ->with(['allTranslations', 'latestReview', 'namespace'])
                ->select(['id', 'namespace_id', 'slug', 'is_published', 'published_at', 'zenn_frontmatter'])->latest('id')->paginate(20)->withQueryString()->through(fn (Article $article): array => [...$article->only(['id', 'title', 'slug', 'topics', 'is_published', 'published_at', 'eyecatch_url']), 'is_sandbox' => $article->isSandbox(), 'publication_status' => $article->publicationStatus(), ...$article->languageMetadata(), 'translationStatus' => $article->translationStatus()]),
        ]);
    }

    public function create(Request $request): Response
    {
        $namespace = $request->validate(['namespace' => ['nullable', 'in:sandbox']])['namespace'] ?? null;

        return Inertia::render('articles/create', ['namespace' => $namespace]);
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->validated($request);
        $article = DB::transaction(function () use ($request, $attributes): Article {
            $translations = $attributes['translations'] ?? null;
            unset($attributes['translations']);
            if ($request->input('namespace') === 'sandbox') {
                $article = new Article($attributes);
                $article->user_id = $request->user()->id;
                $article->namespace()->associate(ArticleNamespace::where('slug', 'sandbox')->firstOrFail());
                $article->save();
            } else {
                $article = $request->user()->articles()->create($attributes);
            }
            if ($translations !== null) {
                $article->replaceTranslations($translations);
            }

            return $article;
        });

        return to_route($request->boolean('continue_editing') ? 'articles.edit' : 'articles.show', $article);
    }

    public function show(Article $article): Response
    {
        Gate::authorize('view', $article);

        return Inertia::render('articles/show', [
            'article' => [...$article->only(['id', 'title', 'content', 'slug', 'is_published', 'published_at', 'edit_version', 'topics', 'eyecatch', 'eyecatch_url']), 'is_sandbox' => $article->isSandbox(), 'publication_status' => $article->publicationStatus(), ...$article->languageMetadata(), 'translationStatus' => $article->translationStatus(), 'translations' => $article->translations->mapWithKeys(fn ($translation) => [$translation->locale => $translation->only(['title', 'content'])])->all()],
            'canPushToZenn' => Gate::allows('push-to-zenn', $article),
        ]);
    }

    public function edit(Article $article): Response
    {
        Gate::authorize('update', $article);

        return Inertia::render('articles/edit', ['article' => [...$article->only(['id', 'title', 'content', 'slug', 'is_published', 'published_at', 'edit_version', 'topics', 'eyecatch', 'eyecatch_url']), 'is_sandbox' => $article->isSandbox(), 'publication_status' => $article->publicationStatus(), ...$article->languageMetadata(), 'translationStatus' => $article->translationStatus(), 'translations' => $article->translations->mapWithKeys(fn ($translation) => [$translation->locale => $translation->only(['title', 'content'])])->all()]]);
    }

    public function update(Request $request, Article $article): RedirectResponse
    {
        Gate::authorize('update', $article);
        $attributes = $this->validated($request, $article);
        DB::transaction(function () use ($article, $attributes, $request): void {
            $locked = Article::whereKey($article->id)->lockForUpdate()->firstOrFail();
            if ($request->integer('edit_version') !== $locked->edit_version) {
                throw ValidationException::withMessages(['edit_version' => __('This article has changed. Reload before continuing.')]);
            }
            $translations = $attributes['translations'] ?? null;
            unset($attributes['translations']);
            $locked->update($attributes);
            if ($locked->wasChanged(['slug', 'is_published', 'published_at', 'zenn_frontmatter'])) {
                $locked->increment('edit_version');
            }
            if ($translations !== null) {
                $locked->replaceTranslations($translations);
            }
        });

        if (! $request->boolean('continue_editing')) {
            $returnTo = $this->safeReturnPath($request->string('return_to')->toString());

            if ($returnTo !== null) {
                $heading = ltrim($request->string('return_heading')->toString(), '#');

                return redirect()->to($returnTo.($heading !== '' ? '#'.rawurlencode($heading) : ''));
            }
        }

        return to_route($request->boolean('continue_editing') ? 'articles.edit' : 'articles.show', $article);
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
        if ($article?->isSandbox() || ($article === null && $request->input('namespace') === 'sandbox')) {
            $input = $request->validate([
                'edit_version' => [$article === null ? 'sometimes' : 'required', 'integer', 'min:0'],
                'title' => ['required', 'string', 'max:255'],
                'content' => ['nullable', 'string'],
                'translations' => ['prohibited'],
                'is_published' => ['sometimes', Rule::in([false, 0, '0'])],
                'published_at' => ['prohibited'],
                'namespace_id' => ['prohibited'],
                'namespace' => $article === null ? ['required', 'in:sandbox'] : ['prohibited'],
                'eyecatch' => ['prohibited'],
            ]);

            return ['is_published' => false, 'published_at' => null, 'translations' => ['ja' => ['title' => $input['title'], 'content' => $input['content'] ?? '']]];
        }
        $validated = $request->validate([
            'edit_version' => [$article === null ? 'sometimes' : 'required', 'integer', 'min:0'],
            'title' => ['required_without:translations', 'string', 'max:255'],
            'translations' => ['sometimes', 'required', 'array:'.implode(',', config('articles.locales'))],
            'translations.*' => ['array:title,content'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'translations.*.content' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', Rule::unique(Article::class, 'slug')->ignore($article)],
            'topics' => ['sometimes', 'array', 'list', 'max:5'],
            'eyecatch' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:'.ArticleEyecatch::PATTERN],
            'topics.*' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'published_at' => ['sometimes', 'nullable', 'date_format:Y-m-d\TH:i'],
            'is_published' => ['sometimes', 'boolean', ...($request->user()->is_sandbox ? [Rule::in([false, 0, '0'])] : [])],
        ]);

        $attributes = [
            'is_published' => $request->has('is_published') ? $request->boolean('is_published') : ($article->is_published ?? false),
        ];

        if (array_key_exists('published_at', $validated)) {
            $attributes['published_at'] = ! $attributes['is_published'] ? null : (
                $validated['published_at'] !== null
                    ? CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $validated['published_at'], 'Asia/Tokyo')->utc()
                    : ($article?->published_at !== null && ! $article->published_at->isFuture() ? $article->published_at : now())
            );
        }

        if (array_key_exists('translations', $validated)) {
            $translations = [];
            foreach ($validated['translations'] as $locale => $translation) {
                $title = trim($translation['title'] ?? '');
                $content = $translation['content'] ?? '';
                if ($title === '' && trim($content) === '') {
                    if ($article?->translations->contains('locale', $locale)) {
                        throw ValidationException::withMessages(["translations.$locale.title" => __('validation.required', ['attribute' => __('Title')])]);
                    }

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

        if (array_key_exists('topics', $validated)) {
            $attributes['topics'] = $validated['topics'];
        }

        if (array_key_exists('eyecatch', $validated)) {
            $attributes['eyecatch'] = $validated['eyecatch'];
        }

        if (isset($validated['slug'])) {
            $attributes['slug'] = $validated['slug'];
        }

        return $attributes;
    }

    private function safeReturnPath(string $path): ?string
    {
        if (
            $path === ''
            || ! str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, '\\')
            || parse_url($path, PHP_URL_SCHEME) !== null
            || parse_url($path, PHP_URL_HOST) !== null
        ) {
            return null;
        }

        return $path;
    }
}
