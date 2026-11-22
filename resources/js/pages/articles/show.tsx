import ArticleLanguageNotice from '@/components/article-language-notice';
import { lang } from '@erag/lang-sync-inertia/react';
import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, ExternalLink, Link2, Pencil, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import ArticleContent, {
    type EditHeadingPayload,
} from '@/components/article-content';
import TranslationStatus from '@/components/translation-status';
import { show as publicShow } from '@/actions/App/Http/Controllers/PublicArticleController';
import {
    destroy,
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
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { findHeadingOffset } from '@/lib/heading-offset';
import type { Article } from '@/types/article';
import { useClipboard } from '@/hooks/use-clipboard';

export default function Show({ article }: { article: Article }) {
    const { __ } = lang();
    const [, copy] = useClipboard();

    async function copyPublicUrl() {
        const url = new URL(
            publicShow.url(article.slug),
            window.location.origin,
        );
        if (await copy(url.href)) {
            toast.success(__('Public URL copied.'));
        } else {
            toast.error(
                __(
                    'Could not copy the URL. Open the public page and copy its address.',
                ),
            );
        }
    }
    function editHeading({ level, text }: EditHeadingPayload) {
        const jump = findHeadingOffset(article.content, text, level);

        router.visit(
            edit(article.id, {
                query: {
                    ...(article.resolvedLocale
                        ? { locale: article.resolvedLocale }
                        : {}),
                    ...(jump === null ? {} : { jump }),
                },
            }),
        );
    }
    setLayoutProps({
        breadcrumbs: [
            { title: '記事', href: index() },
            { title: article.title, href: show(article.id) },
        ],
    });
    return (
        <>
            <Head title={article.title} />
            <div className="mx-auto w-full max-w-4xl px-4 pt-6 pb-16 sm:px-8 sm:pt-10">
                <div className="mb-10 flex items-center justify-between gap-3">
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button asChild variant="ghost" size="icon">
                                <Link
                                    href={index()}
                                    aria-label={__('Back to my articles')}
                                >
                                    <ArrowLeft aria-hidden="true" />
                                </Link>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            {__('Back to my articles')}
                        </TooltipContent>
                    </Tooltip>
                    <div className="flex items-center gap-1 text-muted-foreground">
                        {article.is_published && (
                            <>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="icon"
                                        >
                                            <Link
                                                href={publicShow(article.slug)}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                aria-label={__(
                                                    'View public page',
                                                )}
                                            >
                                                <ExternalLink aria-hidden="true" />
                                            </Link>
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {__('View public page')}
                                    </TooltipContent>
                                </Tooltip>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label={__('Copy public URL')}
                                            onClick={() => void copyPublicUrl()}
                                        >
                                            <Link2 aria-hidden="true" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        {__('Copy public URL')}
                                    </TooltipContent>
                                </Tooltip>
                                <span
                                    aria-hidden="true"
                                    className="mx-2 h-4 border-l"
                                />
                            </>
                        )}
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button asChild variant="ghost" size="icon">
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
                        <Dialog>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <DialogTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="hover:bg-destructive/10 hover:text-destructive"
                                            aria-label={__('Delete article')}
                                        >
                                            <Trash2 aria-hidden="true" />
                                        </Button>
                                    </DialogTrigger>
                                </TooltipTrigger>
                                <TooltipContent>
                                    {__('Delete article')}
                                </TooltipContent>
                            </Tooltip>
                            <DialogContent>
                                <DialogTitle>
                                    {__('Delete this article?')}
                                </DialogTitle>
                                <DialogDescription>
                                    {__(
                                        'This article will be permanently deleted. This cannot be undone.',
                                    )}
                                </DialogDescription>
                                <Form {...destroy.form(article.id)}>
                                    {({ processing }) => (
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                >
                                                    {__('Cancel')}
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                disabled={processing}
                                            >
                                                {__('Delete article')}
                                            </Button>
                                        </DialogFooter>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>
                <article className="min-w-0">
                    <header className="space-y-5 border-b pb-8">
                        <div className="flex min-w-0 items-center gap-3">
                            <Badge
                                variant="secondary"
                                className="shrink-0 gap-1.5 font-normal"
                            >
                                <span
                                    aria-hidden="true"
                                    className={`size-1.5 rounded-full ${article.is_published ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-muted-foreground/60'}`}
                                />
                                {__(
                                    article.is_published
                                        ? 'Published'
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
                        <h1 className="text-3xl leading-tight font-semibold tracking-tight break-words sm:text-4xl">
                            {article.title}
                        </h1>
                        <ArticleLanguageNotice
                            resolvedLocale={article.resolvedLocale}
                        />
                        <TranslationStatus
                            articleId={article.id}
                            states={article.translationStatus}
                        />
                        {!article.is_published && (
                            <p className="text-sm text-muted-foreground">
                                {__(
                                    'This draft is only visible to you. Publish it to share its URL.',
                                )}
                            </p>
                        )}
                    </header>
                    <div className="pt-8">
                        <ArticleContent
                            content={article.content}
                            onEditHeading={editHeading}
                            syntax={
                                article.slug === 'showcase-zenn'
                                    ? 'zenn'
                                    : 'all'
                            }
                        />
                    </div>
                </article>
            </div>
        </>
    );
}
