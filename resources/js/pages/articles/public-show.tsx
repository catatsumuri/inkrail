import ArticleLanguageNotice from '@/components/article-language-notice';
import { lang } from '@erag/lang-sync-inertia/react';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { index } from '@/actions/App/Http/Controllers/PublicArticleController';
import ArticleContent from '@/components/article-content';
import ArticleCover from '@/components/article-cover';
import PublicArticleHeader from '@/components/public-article-header';
import SocialHead, { type SocialMetadata } from '@/components/social-head';
import { cn } from '@/lib/utils';
import type { Article } from '@/types/article';

export default function PublicShow({
    article,
    editableArticleId,
    publicIndexEnabled,
    social,
}: {
    article: Pick<
        Article,
        | 'published_at'
        | 'title'
        | 'content'
        | 'slug'
        | 'resolvedLocale'
        | 'eyecatch_url'
        | 'topics'
    >;
    editableArticleId: number | null;
    publicIndexEnabled: boolean;
    social: SocialMetadata;
}) {
    const { __ } = lang();

    return (
        <>
            <SocialHead social={social} />
            <ArticleLanguageNotice resolvedLocale={article.resolvedLocale} />
            <div className="min-h-screen bg-background text-foreground">
                <PublicArticleHeader editableArticleId={editableArticleId} />
                <main className="mx-auto max-w-5xl space-y-8 px-6 pt-8 pb-20 sm:pt-12">
                    {publicIndexEnabled && (
                        <Link
                            href={index()}
                            className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft aria-hidden="true" className="size-4" />
                            {__('Public articles')}
                        </Link>
                    )}
                    <article className="space-y-10 sm:space-y-14">
                        <header className="relative isolate overflow-hidden rounded-2xl border bg-muted/25">
                            {article.eyecatch_url && (
                                <>
                                    <ArticleCover
                                        src={article.eyecatch_url}
                                        title={article.title}
                                        presentation="backdrop"
                                        className="absolute inset-0"
                                    />
                                    <div
                                        aria-hidden="true"
                                        className="absolute inset-0 bg-linear-to-t from-black/85 via-black/35 to-black/5"
                                    />
                                </>
                            )}
                            <div className="relative flex min-h-60 flex-col justify-end gap-6 p-6 sm:min-h-80 sm:p-12">
                                <p
                                    className={cn(
                                        'flex items-center gap-3 font-mono text-[10px] tracking-[0.2em] uppercase',
                                        article.eyecatch_url
                                            ? 'text-white/80'
                                            : 'text-muted-foreground',
                                    )}
                                >
                                    <span
                                        aria-hidden="true"
                                        className="h-px w-8 bg-current"
                                    />
                                    InkRail
                                </p>
                                <h1
                                    className={cn(
                                        'max-w-3xl text-3xl leading-tight font-semibold tracking-tight text-balance break-words sm:text-5xl',
                                        article.eyecatch_url && 'text-white',
                                    )}
                                >
                                    {article.title}
                                </h1>
                                {article.topics &&
                                    article.topics.length > 0 && (
                                        <div className="flex flex-wrap gap-2">
                                            {article.topics.map((topic) => (
                                                <span
                                                    key={topic}
                                                    className={cn(
                                                        'rounded-full border text-xs',
                                                        article.eyecatch_url
                                                            ? 'border-white/25 bg-black/30 text-white'
                                                            : 'bg-background/70 text-muted-foreground',
                                                    )}
                                                >
                                                    {publicIndexEnabled ? (
                                                        <Link
                                                            href={index({
                                                                query: {
                                                                    topic,
                                                                },
                                                            })}
                                                            className="block rounded-full px-3 py-1 transition-colors hover:bg-foreground/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                                        >
                                                            {topic}
                                                        </Link>
                                                    ) : (
                                                        <span className="block px-3 py-1">
                                                            {topic}
                                                        </span>
                                                    )}
                                                </span>
                                            ))}
                                        </div>
                                    )}
                            </div>
                        </header>
                        <div className="mx-auto max-w-3xl">
                            {article.published_at && (
                                <p className="text-sm text-muted-foreground">
                                    {new Intl.DateTimeFormat('ja-JP', {
                                        timeZone: 'Asia/Tokyo',
                                        dateStyle: 'medium',
                                    }).format(
                                        new Date(article.published_at),
                                    )}{' '}
                                    JST
                                </p>
                            )}
                            <ArticleContent
                                content={article.content}
                                syntax={
                                    article.slug === 'showcase-zenn'
                                        ? 'zenn'
                                        : 'all'
                                }
                            />
                        </div>
                    </article>
                </main>
            </div>
        </>
    );
}
