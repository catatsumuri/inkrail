<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ShowcaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->firstOrFail();

        foreach ([
            'markdown' => 'Basic Markdown — Writing fundamentals',
            'zenn' => 'Zenn flavor — Messages and disclosures',
            'mintlify' => 'Mintlify flavor — Composing documentation',
            'gfm' => 'GitHub flavor — Tables, tasks, and alerts',
            'extensions' => 'inkstream extensions — Diagrams, charts, trees, and quizzes',
        ] as $syntax => $title) {
            $article = $user->articles()->firstOrCreate(
                ['slug' => 'showcase-'.$syntax],
                [
                    'title' => $title,
                    'content' => File::get(database_path('seeders/showcase/en/'.$syntax.'.md')),
                    'is_published' => true,
                ],
            );
            $legacyTitles = json_decode(File::get(database_path('seeders/showcase/ja/titles.json')), true);
            if ($article->title === $legacyTitles[$syntax] && $article->content === File::get(database_path('seeders/showcase/ja/'.$syntax.'.md'))) {
                $article->update(['title' => $title, 'content' => File::get(database_path('seeders/showcase/en/'.$syntax.'.md'))]);
            }
        }
    }
}
