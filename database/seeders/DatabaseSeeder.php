<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first() ?? User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        foreach ([
            [
                'title' => 'InkRailへようこそ',
                'is_published' => true,
                'content' => "InkRailは、考えやアイデアを書き留めるための場所です。\n\nまずは新しい記事を作成して、今日の気づきを残してみましょう。",
            ],
            [
                'title' => '記事の編集と管理',
                'is_published' => false,
                'content' => "作成した記事は、詳細画面から編集できます。\n\n不要になった記事は削除できます。確認ダイアログで内容を確認してから削除してください。",
            ],
        ] as $article) {
            $seededArticle = $user->articles()->firstOrCreate(
                ['title' => $article['title']],
                ['content' => $article['content'], 'slug' => Str::lower((string) Str::ulid()), 'is_published' => $article['is_published']],
            );
            $seededArticle->update(['is_published' => $article['is_published']]);
        }
    }
}
