<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Inertia\Inertia;
use Inertia\Response;

class PublicArticleController extends Controller
{
    public function show(Article $article): Response
    {
        abort_unless($article->is_published, 404);

        return Inertia::render('articles/public-show', [
            'article' => $article->only(['title', 'content', 'slug']),
        ]);
    }
}
