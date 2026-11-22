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

        $articles = [
            [
                'title' => 'Welcome to InkRail',
                'slug' => 'welcome-to-inkrail',
                'legacy_title' => 'InkRailへようこそ',
                'is_published' => true,
                'legacy_content' => "InkRailは、考えやアイデアを書き留めるための場所です。\n\nまずは新しい記事を作成して、今日の気づきを残してみましょう。",
                'content' => "## What is this?\n\n`InkRail` is a place to capture your thoughts and ideas.\n\nCreate an article and write down something you learned today.",
                'previous_content' => "InkRail is a place to capture your thoughts and ideas.\n\nCreate an article and write down something you learned today.",
                'ja_content' => "## これは何ですか\n\n`InkRail`は、考えやアイデアを書き留めるための場所です。\n\nまずは新しい記事を作成して、今日の気づきを残してみましょう。",
            ],
        ];

        if (config('app.demo_mode')) {
            $articles[] = [
                'title' => 'Editing and managing articles',
                'slug' => 'editing-and-managing-articles',
                'legacy_title' => '記事の編集と管理',
                'is_published' => false,
                'legacy_content' => "作成した記事は、詳細画面から編集できます。\n\n不要になった記事は削除できます。確認ダイアログで内容を確認してから削除してください。",
                'content' => "Open an article to edit its title and content.\n\nYou can delete articles you no longer need. Review the confirmation dialog before deleting.",
            ];
        }

        foreach ($articles as $article) {
            $seededArticle = $user->articles()->where(fn ($query) => $query
                ->where('slug', $article['slug'])
                ->orWhereHas('translations', fn ($query) => $query->whereIn('title', [$article['title'], $article['legacy_title']])))->first();
            if ($seededArticle !== null) {
                $english = $seededArticle->translations()->where('locale', 'en')->first();
                if ($english !== null && $english->title === $article['legacy_title'] && $english->content === $article['legacy_content']) {
                    $english->update(['title' => $article['title'], 'content' => $article['content']]);
                }
                if ($english !== null && $english->title === $article['title'] && isset($article['previous_content']) && $english->content === $article['previous_content']) {
                    $english->update(['content' => $article['content']]);
                }

                $japanese = $seededArticle->translations()->where('locale', 'ja')->first();
                if ($japanese !== null && $japanese->title === $article['legacy_title'] && $japanese->content === $article['legacy_content'] && isset($article['ja_content'])) {
                    $japanese->update(['content' => $article['ja_content']]);
                }

                $seededArticle->translations()->firstOrCreate(['locale' => 'en'], ['title' => $article['title'], 'content' => $article['content']]);
                $seededArticle->translations()->firstOrCreate(['locale' => 'ja'], ['title' => $article['legacy_title'], 'content' => $article['ja_content'] ?? $article['legacy_content']]);

                continue;
            }

            $created = $user->articles()->create(['slug' => $article['slug'], 'is_published' => $article['is_published']]);
            $created->replaceTranslations(['en' => ['title' => $article['title'], 'content' => $article['content']], 'ja' => ['title' => $article['legacy_title'], 'content' => $article['ja_content'] ?? $article['legacy_content']]]);
        }

        if (config('app.demo_mode')) {
            $this->call(ShowcaseSeeder::class);
        }
    }
}
