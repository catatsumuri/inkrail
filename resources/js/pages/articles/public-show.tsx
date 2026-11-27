import { markdown } from '@/actions/App/Http/Controllers/PublicArticleController';
import { Button } from '@/components/ui/button';
import ArticleLanguageNotice from '@/components/article-language-notice';
import { lang } from '@erag/lang-sync-inertia/react';
import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { index } from '@/actions/App/Http/Controllers/PublicArticleController';
import ArticleContent from '@/components/article-content';
import ArticleTitleHeader from '@/components/article-title-header';
import PublicArticleHeader from '@/components/public-article-header';
import SocialHead, { type SocialMetadata } from '@/components/social-head';
import type { Article } from '@/types/article';

export default function PublicShow({
    article,
    editableArticleId,
    publicIndexEnabled,
    markdownDownloadEnabled,
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
    markdownDownloadEnabled: boolean;
    social: SocialMetadata;
}) {
    const { __ } = lang();
    const { locale } = usePage().props;

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
                        <header className="space-y-5">
                            <ArticleTitleHeader
                                title={article.title}
                                src={article.eyecatch_url}
                            />
                            {(markdownDownloadEnabled ||
                                (article.topics &&
                                    article.topics.length > 0)) && (
                                <div className="flex flex-wrap items-center gap-2">
                                    {article.topics?.map((topic) => (
                                        <span
                                            key={topic}
                                            className="rounded-full border bg-background/70 text-xs text-muted-foreground"
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
                                    {markdownDownloadEnabled && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <a
                                                href={markdown.url(
                                                    article.slug,
                                                    {
                                                        query: {
                                                            lang:
                                                                article.resolvedLocale ??
                                                                locale,
                                                        },
                                                    },
                                                )}
                                            >
                                                {__('View source')}
                                            </a>
                                        </Button>
                                    )}
                                </div>
                            )}
                        </header>
                        <div className="mx-auto max-w-3xl">
                            {article.published_at && (
                                <p className="text-sm text-muted-foreground">
                                    {new Intl.DateTimeFormat(locale, {
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
