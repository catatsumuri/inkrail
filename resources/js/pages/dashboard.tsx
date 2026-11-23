import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { lang } from '@erag/lang-sync-inertia/react';
import { CircleCheck, Download, LoaderCircle, PencilLine } from 'lucide-react';
import { useRef, useState } from 'react';
import { index, show } from '@/actions/App/Http/Controllers/ArticleController';
import ZennImportController from '@/actions/App/Http/Controllers/ZennImportController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { dashboard } from '@/routes';

type ImportStatus =
    | 'created'
    | 'updated'
    | 'unchanged'
    | 'conflict'
    | 'invalid';
type ImportResult = Record<ImportStatus, number> & {
    items: { path: string; status: ImportStatus }[];
};
type Zenn = {
    repository: string | null;
    branch: string;
    configured: boolean;
    articles: {
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
    result: ImportResult | null;
};

const resultLabels: Record<ImportStatus, string> = {
    created: 'Imported',
    updated: 'Updated',
    unchanged: 'GitHub unchanged',
    conflict: 'Conflict — kept local article',
    invalid: 'Invalid article — skipped',
};

export default function Dashboard({ zenn }: { zenn: Zenn | null }) {
    const { __ } = lang();
    const [importDialogOpen, setImportDialogOpen] = useState(false);
    const [importing, setImporting] = useState(false);
    const [importSucceeded, setImportSucceeded] = useState(false);
    const [importLogs, setImportLogs] = useState<string[]>([]);
    const [importResult, setImportResult] = useState<ImportResult | null>(null);
    const cancelImportRef = useRef<HTMLButtonElement>(null);

    async function importFromGitHub() {
        if (importing) return;
        setImporting(true);
        setImportLogs([]);
        setImportSucceeded(false);
        let complete = false;
        let imported = false;
        try {
            const token = document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
                ?.slice(11);
            const response = await fetch(ZennImportController.url(), {
                method: 'POST',
                headers: {
                    Accept: 'text/event-stream',
                    'X-XSRF-TOKEN': decodeURIComponent(token ?? ''),
                },
            });
            if (
                !response.ok ||
                !response.body ||
                !response.headers
                    .get('content-type')
                    ?.includes('text/event-stream')
            )
                throw new Error();
            const reader = response.body
                .pipeThrough(new TextDecoderStream())
                .getReader();
            let buffer = '';
            read: for (;;) {
                const { value, done } = await reader.read();
                if (done) break;
                buffer += value;
                let boundary;
                while ((boundary = buffer.indexOf('\n\n')) >= 0) {
                    const frame = buffer.slice(0, boundary);
                    buffer = buffer.slice(boundary + 2);
                    const data = frame
                        .split('\n')
                        .find((line) => line.startsWith('data: '));
                    if (!data) continue;
                    const entry = JSON.parse(data.slice(6)) as {
                        message: string;
                        time: string;
                        replace?: Record<string, string | number>;
                        status?: string;
                        result?: ImportResult;
                    };
                    setImportLogs((logs) => [
                        ...logs,
                        `${new Date(entry.time).toLocaleTimeString()} ${__(entry.message, entry.replace)}`,
                    ]);
                    if (frame.startsWith('event: result')) {
                        complete = true;
                        if (entry.status === 'imported' && entry.result) {
                            imported = true;
                            setImportResult(entry.result);
                            setImportSucceeded(true);
                        }
                        void reader.cancel();
                        break read;
                    }
                }
            }
            if (!complete) throw new Error();
        } catch {
            setImportLogs((logs) => [
                ...logs,
                __('Could not confirm the import result.'),
            ]);
        } finally {
            setImporting(false);
            if (imported) {
                router.reload({ only: ['zenn'] });
            }
        }
    }

    const result = importResult ?? zenn?.result ?? null;

    setLayoutProps({
        breadcrumbs: [
            {
                title: __('Dashboard'),
                href: dashboard(),
            },
        ],
    });

    return (
        <>
            <Head title={__('Dashboard')} />
            <div className="mx-auto flex w-full max-w-5xl flex-col gap-8 px-4 pt-8 pb-16 sm:px-8 sm:pt-12">
                <header className="flex flex-wrap items-center justify-between gap-4">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {__('Dashboard')}
                    </h1>
                    <Button asChild variant="outline">
                        <Link href={index()}>{__('My articles')}</Link>
                    </Button>
                </header>
                {zenn ? (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>{__('GitHub sync')}</CardTitle>
                                <CardDescription>
                                    {__(
                                        'Import Japanese articles from GitHub. Articles edited locally are kept when changes conflict. Importing never writes to GitHub.',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col items-start gap-4">
                                {zenn.configured ? (
                                    <p className="text-sm break-all text-muted-foreground">
                                        {zenn.repository} · {zenn.branch}
                                    </p>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {__('GitHub sync is not configured.')}
                                    </p>
                                )}
                                <Dialog
                                    open={importDialogOpen}
                                    onOpenChange={(open) => {
                                        if (importing) return;
                                        setImportDialogOpen(open);
                                        if (!open) setImportLogs([]);
                                    }}
                                >
                                    <DialogTrigger asChild>
                                        <Button
                                            type="button"
                                            disabled={
                                                !zenn.configured || importing
                                            }
                                        >
                                            <Download aria-hidden="true" />
                                            {__('Import from GitHub')}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent
                                        onEscapeKeyDown={(event) => {
                                            if (importing)
                                                event.preventDefault();
                                        }}
                                        onInteractOutside={(event) => {
                                            if (importing)
                                                event.preventDefault();
                                        }}
                                        onOpenAutoFocus={(event) => {
                                            event.preventDefault();
                                            cancelImportRef.current?.focus();
                                        }}
                                    >
                                        <DialogTitle>
                                            {__(
                                                'Import articles from this repository?',
                                            )}
                                        </DialogTitle>
                                        <DialogDescription asChild>
                                            <div className="space-y-3">
                                                <p className="break-all">
                                                    {zenn.repository} ·{' '}
                                                    {zenn.branch}
                                                </p>
                                                <p>
                                                    {__(
                                                        'New articles will be added to your account. Linked articles without local changes may be updated. Conflicting articles will be skipped.',
                                                    )}
                                                </p>
                                                <p>
                                                    {__(
                                                        'Published articles will also be public in InkRail. This operation does not write to GitHub.',
                                                    )}
                                                </p>
                                            </div>
                                        </DialogDescription>
                                        <pre
                                            role="log"
                                            aria-live="polite"
                                            className="max-h-72 overflow-auto text-xs whitespace-pre-wrap"
                                        >
                                            {importLogs.join('\n')}
                                        </pre>
                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button
                                                    ref={cancelImportRef}
                                                    type="button"
                                                    variant="secondary"
                                                    disabled={importing}
                                                >
                                                    {__(
                                                        importLogs.length > 0
                                                            ? 'Close'
                                                            : 'Cancel',
                                                    )}
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                type="button"
                                                disabled={
                                                    importing || importSucceeded
                                                }
                                                onClick={() =>
                                                    void importFromGitHub()
                                                }
                                            >
                                                {importing && (
                                                    <LoaderCircle
                                                        className="animate-spin"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                                {__(
                                                    importing
                                                        ? 'Importing articles…'
                                                        : importLogs.length > 0
                                                          ? 'Retry importing'
                                                          : 'Start importing',
                                                )}
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </CardContent>
                        </Card>
                        {result && (
                            <section
                                className="space-y-4 rounded-xl border p-6"
                                aria-live="polite"
                            >
                                <h2 className="font-semibold">
                                    {__('Import results')}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {__(
                                        'Imported: :created · Updated: :updated · GitHub unchanged: :unchanged · Conflicts: :conflict · Invalid: :invalid',
                                        {
                                            created: result.created,
                                            updated: result.updated,
                                            unchanged: result.unchanged,
                                            conflict: result.conflict,
                                            invalid: result.invalid,
                                        },
                                    )}
                                </p>
                                {(result.conflict > 0 ||
                                    result.invalid > 0) && (
                                    <p className="text-sm">
                                        {__(
                                            'Conflicting and invalid articles were skipped. Review them before retrying.',
                                        )}
                                    </p>
                                )}
                                <ul className="max-h-72 divide-y overflow-auto text-sm">
                                    {result.items.map((item) => (
                                        <li
                                            key={item.path}
                                            className="flex flex-wrap justify-between gap-2 py-3"
                                        >
                                            <span className="break-all">
                                                {item.path}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {__(resultLabels[item.status])}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}
                        <section className="space-y-4">
                            <h2 className="text-lg font-semibold">
                                {__('Linked articles')}{' '}
                                <span className="text-sm font-normal text-muted-foreground">
                                    ({zenn.articles.total})
                                </span>
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {__(
                                    'Status shows local changes since the last import or send. Import again to check for GitHub updates.',
                                )}
                            </p>
                            {zenn.articles.data.length === 0 ? (
                                <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                    {__(
                                        'No articles are linked to this repository yet.',
                                    )}
                                </p>
                            ) : (
                                <ul className="divide-y border-y">
                                    {zenn.articles.data.map((article) => (
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
                            {(zenn.articles.prev_page_url ||
                                zenn.articles.next_page_url) && (
                                <nav
                                    aria-label={__('Pagination')}
                                    className="flex justify-between gap-4"
                                >
                                    <div>
                                        {zenn.articles.prev_page_url && (
                                            <Button asChild variant="ghost">
                                                <Link
                                                    href={
                                                        zenn.articles
                                                            .prev_page_url
                                                    }
                                                >
                                                    {__('Previous page')}
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                    <div>
                                        {zenn.articles.next_page_url && (
                                            <Button asChild variant="ghost">
                                                <Link
                                                    href={
                                                        zenn.articles
                                                            .next_page_url
                                                    }
                                                >
                                                    {__('Next page')}
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                </nav>
                            )}
                        </section>
                    </>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        {__('Open your articles to keep writing and editing.')}
                    </p>
                )}
            </div>
        </>
    );
}
