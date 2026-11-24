import ArticlePublicationTime from '@/components/article-publication-time';
import ArticleLanguageNotice from '@/components/article-language-notice';
import TranslationStatus from '@/components/translation-status';
import { lang } from '@erag/lang-sync-inertia/react';
import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, FileText, Pencil, Plus } from 'lucide-react';
import {
    create,
    edit,
    index,
    show,
} from '@/actions/App/Http/Controllers/ArticleController';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { Article } from '@/types/article';

type Articles = {
    data: Pick<
        Article,
        | 'id'
        | 'title'
        | 'slug'
        | 'resolvedLocale'
        | 'is_published'
        | 'publication_status'
        | 'published_at'
        | 'translationStatus'
    >[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

export default function Index({ articles }: { articles: Articles }) {
    const { __ } = lang();
    setLayoutProps({ breadcrumbs: [{ title: __('Articles'), href: index() }] });
    return (
        <>
            <Head title={__('Articles')} />
            <div className="mx-auto w-full max-w-5xl space-y-8 px-4 pt-8 pb-16 sm:px-8 sm:pt-12">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {__('Articles')}
                        </h1>
                        <p className="max-w-xl text-sm leading-relaxed text-muted-foreground">
                            {__(
                                'Write at your own pace, keep drafts close, and share articles when they are ready.',
                            )}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={create()}>
                            <Plus aria-hidden="true" />
                            {__('Start writing')}
                        </Link>
                    </Button>
                </header>
                {articles.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-4 rounded-xl border border-dashed px-6 py-16 text-center">
                        <FileText
                            aria-hidden="true"
                            className="size-8 text-muted-foreground/60"
                        />
                        <h2 className="font-medium">
                            {__('A space for your drafts')}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {__(
                                'Save your thoughts and return to edit them whenever you like.',
                            )}
                        </p>
                        <Button asChild variant="outline">
                            <Link href={create()}>
                                <Plus aria-hidden="true" />
                                {__('Start writing')}
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <ul className="divide-y border-y">
                        {articles.data.map((article) => (
                            <li
                                key={article.id}
                                className="group flex items-start justify-between gap-4 py-6 sm:py-7"
                            >
                                <div className="min-w-0 flex-1 space-y-3">
                                    <Link
                                        href={show(article.id)}
                                        className="block w-fit rounded-sm text-lg leading-snug font-medium tracking-tight break-words transition-colors hover:text-muted-foreground focus-visible:outline-2 focus-visible:outline-ring sm:text-xl"
                                    >
                                        {article.title}
                                    </Link>
                                    {article.published_at && (
                                        <div className="flex flex-wrap items-baseline gap-x-2 text-sm text-muted-foreground">
                                            <span>
                                                {__(
                                                    article.publication_status ===
                                                        'scheduled'
                                                        ? 'Scheduled publication:'
                                                        : 'Published at:',
                                                )}
                                            </span>
                                            <ArticlePublicationTime
                                                date={article.published_at}
                                                scheduled={
                                                    article.publication_status ===
                                                    'scheduled'
                                                }
                                            />
                                        </div>
                                    )}
                                    <ArticleLanguageNotice
                                        resolvedLocale={article.resolvedLocale}
                                    />
                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                                        <Badge
                                            variant="secondary"
                                            className="gap-1.5 font-normal"
                                        >
                                            <span
                                                aria-hidden="true"
                                                className={`size-1.5 rounded-full ${article.is_published ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-muted-foreground/60'}`}
                                            />
                                            {__(
                                                article.is_published
                                                    ? article.publication_status ===
                                                      'scheduled'
                                                        ? 'Scheduled'
                                                        : 'Published'
                                                    : 'Draft',
                                            )}
                                        </Badge>
                                        <span
                                            className="truncate text-xs text-muted-foreground"
                                            title={article.slug}
                                        >
                                            {article.slug}
                                        </span>
                                    </div>
                                    <TranslationStatus
                                        articleId={article.id}
                                        states={article.translationStatus}
                                        compact
                                    />
                                </div>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                            className="shrink-0 text-muted-foreground"
                                        >
                                            <Link
                                                href={edit(article.id)}
                                                aria-label={__('Edit article')}
                                            >
                                                <Pencil aria-hidden="true" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {__('Edit article')}
                                    </TooltipContent>
                                </Tooltip>
                            </li>
                        ))}
                    </ul>
                )}
                {(articles.prev_page_url || articles.next_page_url) && (
                    <nav
                        aria-label={__('Pagination')}
                        className="flex items-center justify-between gap-4"
                    >
                        <div>
                            {articles.prev_page_url && (
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={articles.prev_page_url}>
                                        <ArrowLeft aria-hidden="true" />
                                        {__('Previous page')}
                                    </Link>
                                </Button>
                            )}
                        </div>
                        <div>
                            {articles.next_page_url && (
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={articles.next_page_url}>
                                        {__('Next page')}
                                        <ArrowRight aria-hidden="true" />
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </nav>
                )}
            </div>
        </>
    );
}
