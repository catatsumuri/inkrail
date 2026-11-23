import ArticleLanguageNotice from '@/components/article-language-notice';
import { lang } from '@erag/lang-sync-inertia/react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { index } from '@/actions/App/Http/Controllers/PublicArticleController';
import ArticleContent from '@/components/article-content';
import ArticleCover from '@/components/article-cover';
import PublicArticleHeader from '@/components/public-article-header';
import type { Article } from '@/types/article';

export default function PublicShow({
    article,
    editableArticleId,
    publicIndexEnabled,
}: {
    article: Pick<
        Article,
        | 'title'
        | 'content'
        | 'slug'
        | 'resolvedLocale'
        | 'eyecatch_url'
        | 'topics'
    >;
    editableArticleId: number | null;
    publicIndexEnabled: boolean;
}) {
    const { __ } = lang();

    return (
        <>
            <Head title={article.title} />
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
                                        className="absolute inset-0 bg-linear-to-r from-background/95 via-background/70 to-background/10"
                                    />
                                </>
                            )}
                            <div className="relative flex min-h-60 flex-col justify-end gap-6 p-6 sm:min-h-80 sm:p-12">
                                <p className="flex items-center gap-3 font-mono text-[10px] tracking-[0.2em] text-muted-foreground uppercase">
                                    <span
                                        aria-hidden="true"
                                        className="h-px w-8 bg-current"
                                    />
                                    InkRail
                                </p>
                                <h1 className="max-w-3xl text-3xl leading-tight font-semibold tracking-tight text-balance break-words sm:text-5xl">
                                    {article.title}
                                </h1>
                                {article.topics &&
                                    article.topics.length > 0 && (
                                        <div className="flex flex-wrap gap-2">
                                            {article.topics.map((topic) => (
                                                <span
                                                    key={topic}
                                                    className="rounded-full border bg-background/70 px-3 py-1 text-xs text-muted-foreground"
                                                >
                                                    {topic}
                                                </span>
                                            ))}
                                        </div>
                                    )}
                            </div>
                        </header>
                        <div className="mx-auto max-w-3xl">
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
