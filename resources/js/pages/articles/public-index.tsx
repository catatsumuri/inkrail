import { lang } from '@erag/lang-sync-inertia/react';
import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
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
                <main className="mx-auto max-w-5xl px-6 pt-12 pb-20 sm:pt-20">
                    <div className="flex flex-col gap-4 border-b pb-10 sm:pb-14">
                        <p className="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase">
                            InkRail
                        </p>
                        <h1 className="text-4xl font-semibold tracking-tight text-balance sm:text-6xl">
                            {__('Public articles')}
                        </h1>
                    </div>
                    {articles.data.length === 0 ? (
                        <p className="py-12 text-muted-foreground">
                            {__('No published articles yet.')}
                        </p>
                    ) : (
                        <ul className="divide-y border-b">
                            {articles.data.map((article, index) => (
                                <li key={article.slug}>
                                    <Link
                                        href={show(article.slug)}
                                        className="group flex items-center gap-5 py-7 transition-colors hover:bg-muted/40 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring sm:gap-8 sm:py-9"
                                    >
                                        <span
                                            aria-hidden="true"
                                            className="self-start pt-1.5 font-mono text-xs text-muted-foreground/60"
                                        >
                                            {String(index + 1).padStart(2, '0')}
                                        </span>
                                        <span className="flex min-w-0 flex-1 flex-col gap-2">
                                            <span className="text-xl leading-snug font-medium tracking-tight break-words sm:text-2xl">
                                                {article.title.split(' — ')[0]}
                                            </span>
                                            {article.title.includes(' — ') && (
                                                <span className="text-sm leading-relaxed text-muted-foreground sm:text-base">
                                                    {article.title
                                                        .split(' — ')
                                                        .slice(1)
                                                        .join(' — ')}
                                                </span>
                                            )}
                                        </span>
                                        <ArrowUpRight
                                            aria-hidden="true"
                                            className="size-5 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-foreground"
                                        />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                    <nav
                        aria-label={__('Pagination')}
                        className="mt-8 flex justify-between gap-4 text-sm font-medium"
                    >
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
