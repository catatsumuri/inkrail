<?php

namespace App\Http\Controllers;

use App\Actions\Zenn\RenderZennArticle;
use App\Models\Article;
use App\Support\ArticleBackupStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, RenderZennArticle $render, ArticleBackupStore $backups): Response
    {
        $canImport = Gate::allows('import-from-zenn');

        return Inertia::render('dashboard', [
            'zenn' => $canImport ? [
                'repository' => config('services.github_zenn.repository'),
                'branch' => config('services.github_zenn.branch'),
                'configured' => filled(config('services.github_zenn.repository')) && filled(config('services.github_zenn.token')) && filled(config('services.github_zenn.branch')),
                'articles' => $request->user()->articles()->with('namespace')->where('zenn_repository', config('services.github_zenn.repository'))
                    ->where('zenn_branch', config('services.github_zenn.branch'))->whereNotNull('zenn_path')->latest('id')->paginate(20)->withQueryString()
                    ->through(fn (Article $article): array => [
                        'id' => $article->id, 'title' => $article->title, 'path' => $article->zenn_path,
                        'locallyChanged' => $render->localSha($article) !== ($article->zenn_local_sha ?? $article->zenn_sha),
                    ]),
                'sandboxArticles' => $request->user()->articles()->with('namespace')
                    ->join('article_github_sources', 'article_github_sources.article_id', '=', 'articles.id')
                    ->where('article_github_sources.repository', config('services.github_zenn.repository'))
                    ->where('article_github_sources.branch', config('services.github_zenn.branch'))
                    ->whereHas('namespace', fn (Builder $query): Builder => $query->where('slug', 'sandbox'))
                    ->select('articles.*', 'article_github_sources.path as github_path', 'article_github_sources.local_fingerprint as github_fingerprint')
                    ->latest('articles.id')->paginate(20, pageName: 'sandbox_page')->withQueryString()
                    ->through(function (Article $article): array {
                        $translation = $article->translations->firstWhere('locale', 'ja');

                        return [
                            'id' => $article->id, 'title' => $article->title, 'path' => $article->getAttribute('github_path'),
                            'locallyChanged' => $translation === null || hash('sha256', serialize([$translation->title, $translation->content])) !== $article->getAttribute('github_fingerprint'),
                        ];
                    }),
                'result' => $request->session()->get('zenn_import'),
            ] : null,
            'backups' => Gate::allows('manage-article-backups') ? [
                'disk' => $backups->diskName(),
                'items' => $backups->list(),
            ] : null,
        ]);
    }
}
