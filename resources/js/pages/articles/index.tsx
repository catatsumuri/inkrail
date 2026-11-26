import ArticleCover from '@/components/article-cover';
import { show as publicShow } from '@/actions/App/Http/Controllers/PublicArticleController';
import ArticlePublicationTime from '@/components/article-publication-time';
import ArticleLanguageNotice from '@/components/article-language-notice';
import ArticleTopicFilter from '@/components/article-topic-filter';
import TranslationStatus from '@/components/translation-status';
import { lang } from '@erag/lang-sync-inertia/react';
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    FileText,
    Files,
    FlaskConical,
    LockKeyhole,
    Pencil,
    Plus,
} from 'lucide-react';
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
        | 'eyecatch_url'
        | 'id'
        | 'is_sandbox'
        | 'title'
        | 'topics'
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

export default function Index({
    articles,
    topics,
    selectedTopic,
    sandboxImport,
    selectedNamespace,
}: {
    articles: Articles;
    topics: string[];
    selectedTopic: string | null;
    selectedNamespace?: string | null;
    sandboxImport?: { items: { path: string; status: string }[] } | null;
}) {
    const { __ } = lang();
    const isSandbox = selectedNamespace === 'sandbox';
    const pageTitle = isSandbox ? __('Sandbox') : __('All articles');
    const createLabel = isSandbox
        ? __('Write a sandbox note')
        : __('Start writing');
    const currentUrl = usePage().url;
    const topicUrl = (topic: string | null) =>
        index.url({ query: { topic, namespace: selectedNamespace } });
    setLayoutProps({
        breadcrumbs: [
            {
                title: pageTitle,
                href: index({ query: { namespace: selectedNamespace } }),
            },
        ],
    });
    return (
        <>
            <Head title={pageTitle} />
            {sandboxImport && (
                <pre
                    role="status"
                    className="mx-auto w-full max-w-5xl px-4 py-4 text-sm whitespace-pre-wrap"
                >
                    {sandboxImport.items
                        .map(
                            (item) =>
                                `${__(({ created: 'Imported', updated: 'Updated', unchanged: 'Unchanged', conflict: 'Conflict — kept local article', invalid: 'Invalid article — skipped' } as Record<string, string>)[item.status] ?? item.status)}: ${item.path}`,
                        )
                        .join('\n')}
                </pre>
            )}
            <div className="mx-auto w-full max-w-5xl space-y-8 px-4 pt-8 pb-16 sm:px-8 sm:pt-12">
                <nav
                    aria-label={__('Writing spaces')}
                    className="flex w-fit max-w-full gap-1 rounded-xl border bg-muted/50 p-1"
                >
                    {[
                        {
                            sandbox: false,
                            title: __('All articles'),
                            icon: Files,
                        },
                        {
                            sandbox: true,
                            title: __('Sandbox'),
                            icon: FlaskConical,
                        },
                    ].map(({ sandbox, title, icon: Icon }) => (
                        <Link
                            key={title}
                            href={
                                sandbox
                                    ? index({ query: { namespace: 'sandbox' } })
                                    : index()
                            }
                            aria-current={
                                isSandbox === sandbox ? 'page' : undefined
                            }
                            className={`flex items-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring ${isSandbox === sandbox ? 'border-border bg-background text-foreground shadow-sm' : 'border-transparent text-muted-foreground hover:bg-background/60 hover:text-foreground'}`}
                        >
                            <Icon className="size-4" aria-hidden="true" />
                            {title}
                        </Link>
                    ))}
                </nav>
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            {pageTitle}
                        </h1>
                        <p className="max-w-xl text-sm leading-relaxed text-muted-foreground">
                            {__(
                                isSandbox
                                    ? 'A workspace for notes and experiments. Sandbox notes cannot be published to InkRail or Zenn.'
                                    : 'All your articles and sandbox notes in one place.',
                            )}
                        </p>
                    </div>
                    {
                        <Button asChild>
                            <Link
                                href={create({
                                    query: { namespace: selectedNamespace },
                                })}
                            >
                                <Plus aria-hidden="true" />
                                {createLabel}
                            </Link>
                        </Button>
                    }
                </header>
                {isSandbox && (
                    <div className="flex items-center gap-3 rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-950 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-100">
                        <LockKeyhole
                            className="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <span>
                            {__(
                                'Only you can view these notes. Publishing is disabled.',
                            )}
                        </span>
                    </div>
                )}
                <ArticleTopicFilter
                    topics={topics}
                    selectedTopic={selectedTopic}
                    topicUrl={topicUrl}
                />
                {articles.data.length === 0 && selectedTopic !== null ? (
                    <div className="rounded-xl border border-dashed px-6 py-16 text-center text-sm text-muted-foreground">
                        {__('No articles match this topic.')}
                    </div>
                ) : articles.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-4 rounded-xl border border-dashed px-6 py-16 text-center">
                        <div
                            className={
                                isSandbox
                                    ? 'rounded-2xl bg-violet-100 p-4 text-violet-700 dark:bg-violet-950 dark:text-violet-300'
                                    : 'rounded-2xl bg-muted p-4 text-muted-foreground'
                            }
                        >
                            {isSandbox ? (
                                <FlaskConical
                                    aria-hidden="true"
                                    className="size-8"
                                />
                            ) : (
                                <FileText
                                    aria-hidden="true"
                                    className="size-8"
                                />
                            )}
                        </div>
                        <h2 className="font-medium">
                            {__(
                                isSandbox
                                    ? 'Your sandbox is ready'
                                    : 'A space for your drafts',
                            )}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {__(
                                isSandbox
                                    ? 'Try an idea, keep a rough note, or explore Markdown. Everything here stays private in InkRail.'
                                    : 'Save your thoughts and return to edit them whenever you like.',
                            )}
                        </p>
                        <Button asChild variant="outline">
                            <Link
                                href={create({
                                    query: { namespace: selectedNamespace },
                                })}
                            >
                                <Plus aria-hidden="true" />
                                {createLabel}
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
                                        aria-label={article.title}
                                    >
                                        <ArticleCover
                                            src={article.eyecatch_url}
                                            title={article.title}
                                            className="mb-3 aspect-video w-40 rounded-lg"
                                        />
                                    </Link>
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
                                    {!article.is_sandbox && (
                                        <ArticleLanguageNotice
                                            resolvedLocale={
                                                article.resolvedLocale
                                            }
                                        />
                                    )}
                                    {article.topics &&
                                        article.topics.length > 0 && (
                                            <div className="flex flex-wrap gap-2">
                                                {article.topics.map((topic) => (
                                                    <Link
                                                        key={topic}
                                                        href={topicUrl(topic)}
                                                        preserveScroll
                                                        className="rounded-full border px-2.5 py-1 text-xs break-all text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-ring"
                                                    >
                                                        {topic}
                                                    </Link>
                                                ))}
                                            </div>
                                        )}
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
                                                article.is_sandbox
                                                    ? 'Sandbox · Private'
                                                    : article.is_published
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
                                    {article.publication_status ===
                                        'published' && (
                                        <Button
                                            asChild
                                            variant="outline"
                                            size="sm"
                                        >
                                            <a
                                                href={publicShow.url(
                                                    article.slug,
                                                )}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                {__('View public page')}
                                            </a>
                                        </Button>
                                    )}
                                    {!article.is_sandbox && (
                                        <TranslationStatus
                                            articleId={article.id}
                                            states={article.translationStatus}
                                            compact
                                        />
                                    )}
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
                                                href={edit(article.id, {
                                                    query: {
                                                        return_to: currentUrl,
                                                    },
                                                })}
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
