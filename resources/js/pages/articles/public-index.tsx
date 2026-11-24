import ArticleLanguageNotice from '@/components/article-language-notice';
import { lang } from '@erag/lang-sync-inertia/react';
import { Link } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight } from 'lucide-react';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/PublicArticleController';
import ArticleCover from '@/components/article-cover';
import ArticleTopicFilter from '@/components/article-topic-filter';
import PublicArticleHeader from '@/components/public-article-header';
import SocialHead, { type SocialMetadata } from '@/components/social-head';
import { cn } from '@/lib/utils';
import type { Article } from '@/types/article';

type Articles = {
    data: Pick<
        Article,
        | 'published_at'
        | 'title'
        | 'slug'
        | 'resolvedLocale'
        | 'topics'
        | 'eyecatch_url'
    >[];
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export default function PublicIndex({
    articles,
    social,
    topics,
    selectedTopic,
}: {
    articles: Articles;
    social: SocialMetadata;
    topics: string[];
    selectedTopic: string | null;
}) {
    const { __ } = lang();

    return (
        <>
            <SocialHead social={social} />
            <div className="min-h-screen bg-background text-foreground">
                <PublicArticleHeader />
                <main className="mx-auto max-w-5xl px-6 pt-14 pb-20 sm:pt-20">
                    <div className="mb-10 flex flex-col gap-5 sm:mb-14">
                        <p className="flex items-center gap-3 text-[10px] font-medium tracking-[0.25em] text-muted-foreground uppercase">
                            <span
                                className="h-px w-8 bg-current"
                                aria-hidden="true"
                            />
                            InkRail
                        </p>
                        <h1 className="font-serif text-5xl leading-[1.1] tracking-tight text-balance sm:text-7xl">
                            {__('Public articles')}
                            <span className="text-amber-600 dark:text-amber-400">
                                .
                            </span>
                        </h1>
                        <p className="max-w-md text-sm leading-relaxed text-muted-foreground sm:text-base">
                            {__('Ideas, notes, and things worth sharing.')}
                        </p>
                    </div>
                    <div className="mb-6">
                        <ArticleTopicFilter
                            topics={topics}
                            selectedTopic={selectedTopic}
                            topicUrl={(topic) =>
                                index.url({ query: { topic } })
                            }
                        />
                    </div>
                    <div className="mb-6 flex items-center justify-between border-t pt-5 text-xs text-muted-foreground">
                        <span>{__('All stories')}</span>
                        <span className="font-mono tabular-nums">
                            {String(articles.total).padStart(2, '0')}
                        </span>
                    </div>
                    {articles.data.length === 0 ? (
                        <div className="rounded-2xl border border-dashed px-6 py-20 text-center text-sm text-muted-foreground">
                            {__(
                                selectedTopic !== null
                                    ? 'No articles match this topic.'
                                    : 'No published articles yet.',
                            )}
                        </div>
                    ) : (
                        <ul className="grid gap-x-8 gap-y-10 sm:grid-cols-2">
                            {articles.data.map((article, position) => (
                                <li
                                    key={article.slug}
                                    className={cn(
                                        'min-w-0',
                                        position === 0 && 'sm:col-span-2',
                                    )}
                                >
                                    <Link
                                        href={show(article.slug)}
                                        className={cn(
                                            'group block rounded-2xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-ring',
                                            position === 0 &&
                                                'overflow-hidden border bg-muted/15 sm:grid sm:grid-cols-2',
                                        )}
                                    >
                                        <ArticleCover
                                            src={article.eyecatch_url}
                                            title={article.title}
                                            className={cn(
                                                'aspect-[16/10] rounded-xl',
                                                position === 0 &&
                                                    'rounded-none sm:aspect-auto sm:min-h-80',
                                            )}
                                        />
                                        <div
                                            className={cn(
                                                'flex flex-col gap-4 pt-5',
                                                position === 0 &&
                                                    'justify-center p-6 sm:p-9',
                                            )}
                                        >
                                            <div className="flex min-h-5 flex-wrap items-center gap-2 text-[11px] text-muted-foreground">
                                                <span className="mr-1 font-mono tabular-nums">
                                                    {String(
                                                        position + 1,
                                                    ).padStart(2, '0')}
                                                </span>
                                                {article.topics?.map(
                                                    (topic) => (
                                                        <span
                                                            key={topic}
                                                            className="rounded-full border px-2.5 py-0.5"
                                                        >
                                                            {topic}
                                                        </span>
                                                    ),
                                                )}
                                            </div>
                                            <h2
                                                className={cn(
                                                    'text-xl leading-snug font-medium tracking-tight text-balance break-words transition-colors group-hover:text-amber-700 dark:group-hover:text-amber-400',
                                                    position === 0 &&
                                                        'sm:text-3xl',
                                                )}
                                            >
                                                {article.title.split(' — ')[0]}
                                            </h2>
                                            {article.published_at && (
                                                <p className="text-xs text-muted-foreground">
                                                    {new Intl.DateTimeFormat(
                                                        'ja-JP',
                                                        {
                                                            timeZone:
                                                                'Asia/Tokyo',
                                                            dateStyle: 'medium',
                                                        },
                                                    ).format(
                                                        new Date(
                                                            article.published_at,
                                                        ),
                                                    )}{' '}
                                                    JST
                                                </p>
                                            )}
                                            {article.title.includes(' — ') && (
                                                <p className="line-clamp-3 text-sm leading-relaxed text-muted-foreground">
                                                    {article.title
                                                        .split(' — ')
                                                        .slice(1)
                                                        .join(' — ')}
                                                </p>
                                            )}
                                            <span className="mt-1 flex items-center gap-2 text-xs font-medium">
                                                {__('Read article')}
                                                <ArrowUpRight
                                                    aria-hidden="true"
                                                    className="size-3.5 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                                                />
                                            </span>
                                        </div>
                                    </Link>
                                    <ArticleLanguageNotice
                                        resolvedLocale={article.resolvedLocale}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                    <nav
                        aria-label={__('Pagination')}
                        className="mt-14 flex justify-between gap-4 border-t pt-6 text-sm font-medium"
                    >
                        {articles.prev_page_url ? (
                            <Link href={articles.prev_page_url}>
                                {__('Previous page')}
                            </Link>
                        ) : (
                            <span />
                        )}
                        {articles.next_page_url && (
                            <Link
                                href={articles.next_page_url}
                                className="inline-flex items-center gap-2"
                            >
                                {__('Next page')}
                                <ArrowRight
                                    aria-hidden="true"
                                    className="size-4"
                                />
                            </Link>
                        )}
                    </nav>
                </main>
            </div>
        </>
    );
}
