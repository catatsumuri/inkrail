<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, PublicArticleController $publicArticles): Response
    {
        if (config('articles.public_index_enabled') && config('articles.home_page') === 'articles') {
            return $publicArticles->index($request);
        }

        return Inertia::render('welcome', [
            'social' => $this->socialMetadata(
                __('Welcome to InkRail'),
                __('Write in Markdown, keep your drafts private, and publish articles in English and Japanese when they are ready.'),
                'website', url('/'),
            ),
            'publicIndexEnabled' => (bool) config('articles.public_index_enabled'),
        ]);
    }
}
