import { Head, setLayoutProps } from '@inertiajs/react';
import {
    edit,
    index,
    show,
} from '@/actions/App/Http/Controllers/ArticleController';
import ArticleForm from '@/components/article-form';
import type { Article } from '@/types/article';

export default function Edit({ article }: { article: Article }) {
    setLayoutProps({
        breadcrumbs: [
            { title: '記事', href: index() },
            { title: article.title, href: show(article.id) },
            { title: '編集', href: edit(article.id) },
        ],
    });
    return (
        <>
            <Head title="記事の編集" />
            <div className="w-full space-y-6 px-4 py-6 sm:px-8 sm:py-10">
                <h1 className="text-xl font-semibold">記事の編集</h1>
                <ArticleForm key={article.id} article={article} />
            </div>
        </>
    );
}
