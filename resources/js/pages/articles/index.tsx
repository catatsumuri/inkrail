import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/ArticleController';
import { Button } from '@/components/ui/button';
import type { Article } from '@/types/article';

type Articles = {
    data: Pick<Article, 'id' | 'title'>[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

export default function Index({ articles }: { articles: Articles }) {
    setLayoutProps({ breadcrumbs: [{ title: '記事', href: index() }] });
    return (
        <>
            <Head title="記事" />
            <main className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-xl font-semibold">記事</h1>
                    <Button asChild>
                        <Link href={create()}>新規作成</Link>
                    </Button>
                </div>
                {articles.data.length === 0 ? (
                    <p>記事はまだありません。</p>
                ) : (
                    <ul className="divide-y">
                        {articles.data.map((article) => (
                            <li key={article.id} className="py-3">
                                <Link
                                    href={show(article.id)}
                                    className="break-all underline"
                                >
                                    {article.title}
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
                <nav aria-label="ページ切り替え" className="flex gap-4">
                    {articles.prev_page_url && (
                        <Link href={articles.prev_page_url}>前へ</Link>
                    )}
                    {articles.next_page_url && (
                        <Link href={articles.next_page_url}>次へ</Link>
                    )}
                </nav>
            </main>
        </>
    );
}
