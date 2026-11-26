import { Link } from '@inertiajs/react';
import { lang } from '@erag/lang-sync-inertia/react';
import { CircleCheck, PencilLine } from 'lucide-react';
import { show } from '@/actions/App/Http/Controllers/ArticleController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export type LinkedArticlePage = {
    data: {
        id: number;
        title: string;
        path: string;
        locallyChanged: boolean;
    }[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

export default function LinkedArticles({
    articles,
    sandbox = false,
}: {
    articles: LinkedArticlePage;
    sandbox?: boolean;
}) {
    const { __ } = lang();

    return (
        <section
            className={
                sandbox
                    ? 'space-y-4 rounded-xl border border-violet-200 bg-violet-50/50 p-5 dark:border-violet-800 dark:bg-violet-950/20'
                    : 'space-y-4'
            }
        >
            <h2 className="text-lg font-semibold">
                {__(sandbox ? 'Linked sandbox documents' : 'Linked articles')}{' '}
                <code className="text-sm font-normal text-muted-foreground">
                    {sandbox ? 'sandbox/' : 'articles/'}
                </code>{' '}
                <span className="text-sm font-normal text-muted-foreground">
                    ({articles.total})
                </span>
            </h2>
            <p className="text-sm text-muted-foreground">
                {__(
                    sandbox
                        ? 'Status shows local changes since the last sandbox import. Import again to check for GitHub updates. Shared images under images/ are public.'
                        : 'Status shows local changes since the last import or send. Import again to check for GitHub updates.',
                )}
            </p>
            {articles.data.length === 0 ? (
                <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                    {__(
                        sandbox
                            ? 'No sandbox documents are linked to this repository yet.'
                            : 'No articles are linked to this repository yet.',
                    )}
                </p>
            ) : (
                <ul className="divide-y border-y">
                    {articles.data.map((article) => (
                        <li
                            key={article.id}
                            className="flex flex-wrap items-center justify-between gap-4 py-5"
                        >
                            <div className="min-w-0 space-y-1">
                                <Link
                                    href={show(article.id)}
                                    className="font-medium hover:underline"
                                >
                                    {article.title}
                                </Link>
                                <p className="text-xs break-all text-muted-foreground">
                                    {article.path}
                                </p>
                            </div>
                            <Badge
                                variant="outline"
                                className={
                                    article.locallyChanged
                                        ? 'gap-1.5 border-amber-300 bg-amber-50 px-3 py-1 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200'
                                        : 'gap-1.5 border-emerald-300 bg-emerald-50 px-3 py-1 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                }
                            >
                                {article.locallyChanged ? (
                                    <PencilLine aria-hidden="true" />
                                ) : (
                                    <CircleCheck aria-hidden="true" />
                                )}
                                {__(
                                    article.locallyChanged
                                        ? 'Local changes'
                                        : 'Unchanged locally',
                                )}
                            </Badge>
                        </li>
                    ))}
                </ul>
            )}
            {(articles.prev_page_url || articles.next_page_url) && (
                <nav
                    aria-label={__('Pagination')}
                    className="flex justify-between gap-4"
                >
                    <div>
                        {articles.prev_page_url && (
                            <Button asChild variant="ghost">
                                <Link
                                    preserveScroll
                                    href={articles.prev_page_url}
                                >
                                    {__('Previous page')}
                                </Link>
                            </Button>
                        )}
                    </div>
                    <div>
                        {articles.next_page_url && (
                            <Button asChild variant="ghost">
                                <Link
                                    preserveScroll
                                    href={articles.next_page_url}
                                >
                                    {__('Next page')}
                                </Link>
                            </Button>
                        )}
                    </div>
                </nav>
            )}
        </section>
    );
}
