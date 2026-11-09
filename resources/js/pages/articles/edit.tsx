import { Head, setLayoutProps } from '@inertiajs/react';
import { edit, index } from '@/actions/App/Http/Controllers/ArticleController';
import ArticleForm from '@/components/article-form';
import type { Article } from '@/types/article';

export default function Edit({ article }: { article: Article }) {
    setLayoutProps({
        breadcrumbs: [
            { title: '記事', href: index() },
            { title: '編集', href: edit(article.id) },
        ],
    });
    return (
        <>
            <Head title="記事の編集" />
            <main className="space-y-6 p-4">
                <h1 className="text-xl font-semibold">記事の編集</h1>
                <ArticleForm article={article} />
            </main>
        </>
    );
}
