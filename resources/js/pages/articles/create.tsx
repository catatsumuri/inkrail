import { Head, setLayoutProps } from '@inertiajs/react';
import {
    create,
    index,
} from '@/actions/App/Http/Controllers/ArticleController';
import ArticleForm from '@/components/article-form';

export default function Create() {
    setLayoutProps({
        breadcrumbs: [
            { title: '記事', href: index() },
            { title: '新規作成', href: create() },
        ],
    });
    return (
        <>
            <Head title="記事の新規作成" />
            <main className="space-y-6 p-4">
                <h1 className="text-xl font-semibold">記事の新規作成</h1>
                <ArticleForm />
            </main>
        </>
    );
}
