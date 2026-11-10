<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'email_verified_at' => now(),
            'password' => config('app.demo_mode') ? Str::random(64) : 'password',
            'is_showcase_owner' => (bool) config('app.demo_mode'),
        ]);

        foreach ([
            [
                'title' => 'Welcome to InkRail',
                'legacy_title' => 'InkRailへようこそ',
                'is_published' => true,
                'legacy_content' => "InkRailは、考えやアイデアを書き留めるための場所です。\n\nまずは新しい記事を作成して、今日の気づきを残してみましょう。",
                'content' => "InkRail is a place to capture your thoughts and ideas.\n\nCreate an article and write down something you learned today.",
            ],
            [
                'title' => 'Editing and managing articles',
                'legacy_title' => '記事の編集と管理',
                'is_published' => false,
                'legacy_content' => "作成した記事は、詳細画面から編集できます。\n\n不要になった記事は削除できます。確認ダイアログで内容を確認してから削除してください。",
                'content' => "Open an article to edit its title and content.\n\nYou can delete articles you no longer need. Review the confirmation dialog before deleting.",
            ],
        ] as $article) {
            $seededArticle = $user->articles()->whereHas('translations', fn ($query) => $query->whereIn('title', [$article['title'], $article['legacy_title']]))->first();
            if ($seededArticle !== null) {
                $english = $seededArticle->translations()->where('locale', 'en')->first();
                if ($english !== null && $english->title === $article['legacy_title'] && $english->content === $article['legacy_content']) {
                    $english->update(['title' => $article['title'], 'content' => $article['content']]);
                }

                $seededArticle->translations()->firstOrCreate(['locale' => 'en'], ['title' => $article['title'], 'content' => $article['content']]);
                $seededArticle->translations()->firstOrCreate(['locale' => 'ja'], ['title' => $article['legacy_title'], 'content' => $article['legacy_content']]);

                continue;
            }

            $created = $user->articles()->create(['slug' => Str::lower((string) Str::ulid()), 'is_published' => $article['is_published']]);
            $created->replaceTranslations(['en' => ['title' => $article['title'], 'content' => $article['content']], 'ja' => ['title' => $article['legacy_title'], 'content' => $article['legacy_content']]]);
        }

        $this->call(ShowcaseSeeder::class);
    }
}
