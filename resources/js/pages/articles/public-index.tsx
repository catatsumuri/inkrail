import { lang } from '@erag/lang-sync-inertia/react';
import { Head, Link } from '@inertiajs/react';
import { show } from '@/actions/App/Http/Controllers/PublicArticleController';
import PublicArticleHeader from '@/components/public-article-header';
import type { Article } from '@/types/article';

type Articles = {
    data: Pick<Article, 'title' | 'slug'>[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

export default function PublicIndex({ articles }: { articles: Articles }) {
    const { __ } = lang();

    return (
        <>
            <Head title={__('Public articles')} />
            <div className="min-h-screen bg-background text-foreground">
                <PublicArticleHeader />
                <main className="mx-auto max-w-5xl space-y-8 px-6 py-10">
                    <h1 className="text-3xl font-semibold">
                        {__('Public articles')}
                    </h1>
                    {articles.data.length === 0 ? (
                        <p className="text-muted-foreground">
                            {__('No published articles yet.')}
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {articles.data.map((article) => (
                                <li key={article.slug} className="py-4">
                                    <Link
                                        href={show(article.slug)}
                                        className="text-lg font-medium break-words underline underline-offset-4"
                                    >
                                        {article.title}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                    <nav aria-label={__('Pagination')} className="flex gap-4">
                        {articles.prev_page_url && (
                            <Link href={articles.prev_page_url}>
                                {__('Previous page')}
                            </Link>
                        )}
                        {articles.next_page_url && (
                            <Link href={articles.next_page_url}>
                                {__('Next page')}
                            </Link>
                        )}
                    </nav>
                </main>
            </div>
        </>
    );
}
