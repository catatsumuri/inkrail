<?php

namespace App\Http\Controllers;

use App\Actions\Zenn\RenderZennArticle;
use App\Models\Article;
use App\Support\ArticleBackupStore;
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
                    ->where('zenn_branch', config('services.github_zenn.branch'))->whereNotNull('zenn_path')->latest('id')->paginate(20)
                    ->through(fn (Article $article): array => [
                        'id' => $article->id, 'title' => $article->title, 'path' => $article->zenn_path,
                        'locallyChanged' => $render->localSha($article) !== ($article->zenn_local_sha ?? $article->zenn_sha),
                    ]),
                'result' => $request->session()->get('zenn_import'),
            ] : null,
            'backups' => Gate::allows('manage-article-backups') ? [
                'disk' => $backups->diskName(),
                'items' => $backups->list(),
            ] : null,
        ]);
    }
}
