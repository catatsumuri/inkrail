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
            'inertia' => 'Inertia.js integration — From Markdown to React',
        ] as $syntax => $title) {
            $article = $user->articles()->firstOrCreate(
                ['slug' => 'showcase-'.$syntax],
                [
                    'is_published' => true,
                ],
            );
            $article->translations()->firstOrCreate(['locale' => 'en'], ['title' => $title, 'content' => File::get(database_path('seeders/showcase/en/'.$syntax.'.md'))]);
            $legacyTitles = json_decode(File::get(database_path('seeders/showcase/ja/titles.json')), true);
            if (isset($legacyTitles[$syntax]) && File::exists(database_path('seeders/showcase/ja/'.$syntax.'.md'))) {
                $article->translations()->firstOrCreate(['locale' => 'ja'], ['title' => $legacyTitles[$syntax], 'content' => File::get(database_path('seeders/showcase/ja/'.$syntax.'.md'))]);
                $article->unsetRelation('translations');
            }
            $english = $article->translations()->where('locale', 'en')->firstOrFail();
            if (isset($legacyTitles[$syntax]) && $english->title === $legacyTitles[$syntax] && $english->content === File::get(database_path('seeders/showcase/ja/'.$syntax.'.md'))) {
                $english->update(['title' => $title, 'content' => File::get(database_path('seeders/showcase/en/'.$syntax.'.md'))]);
            }
        }
    }
}
