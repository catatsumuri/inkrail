import { Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { lang } from '@erag/lang-sync-inertia/react';
import { Download, Files, LoaderCircle } from 'lucide-react';
import { useRef, useState } from 'react';
import { index } from '@/actions/App/Http/Controllers/ArticleController';
import SandboxImportController from '@/actions/App/Http/Controllers/SandboxImportController';
import ZennImportController from '@/actions/App/Http/Controllers/ZennImportController';
import ArticleBackupsCard, {
    type ArticleBackups,
} from '@/components/article-backups';
import LinkedArticles, {
    type LinkedArticlePage,
} from '@/components/linked-articles';
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
} from '@/components/ui/dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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
    articles: LinkedArticlePage;
    sandboxArticles: LinkedArticlePage;
    result: ImportResult | null;
};

const resultLabels: Record<ImportStatus, string> = {
    created: 'Imported',
    updated: 'Updated',
    unchanged: 'GitHub unchanged',
    conflict: 'Conflict — kept local article',
    invalid: 'Invalid file — skipped',
};

export default function Dashboard({
    zenn,
    backups,
}: {
    zenn: Zenn | null;
    backups: ArticleBackups | null;
}) {
    const { __ } = lang();
    const [importDialogOpen, setImportDialogOpen] = useState(false);
    const [importTarget, setImportTarget] = useState<'articles' | 'sandbox'>(
        'articles',
    );
    const [resultTarget, setResultTarget] = useState<'articles' | 'sandbox'>(
        'articles',
    );
    const [importing, setImporting] = useState(false);
    const [importSucceeded, setImportSucceeded] = useState(false);
    const [importLogs, setImportLogs] = useState<string[]>([]);
    const [importResult, setImportResult] = useState<ImportResult | null>(null);
    const cancelImportRef = useRef<HTMLButtonElement>(null);

    function openImport(target: 'articles' | 'sandbox') {
        setImportTarget(target);
        setImportLogs([]);
        setImportSucceeded(false);
        setImportDialogOpen(true);
    }

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
            const response = await fetch(
                importTarget === 'sandbox'
                    ? SandboxImportController.url()
                    : ZennImportController.url(),
                {
                    method: 'POST',
                    headers: {
                        Accept: 'text/event-stream',
                        'X-XSRF-TOKEN': decodeURIComponent(token ?? ''),
                    },
                },
            );
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
                            setResultTarget(importTarget);
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
                            <CardContent className="space-y-6">
                                {zenn.configured ? (
                                    <dl className="grid gap-4 rounded-lg bg-muted/50 p-4 sm:grid-cols-[minmax(0,1fr)_auto]">
                                        <div className="min-w-0 space-y-1">
                                            <dt className="text-xs text-muted-foreground">
                                                {__('Repository')}
                                            </dt>
                                            <dd className="text-sm font-medium break-all">
                                                {zenn.repository}
                                            </dd>
                                        </div>
                                        <div className="space-y-1">
                                            <dt className="text-xs text-muted-foreground">
                                                {__('Branch')}
                                            </dt>
                                            <dd className="font-mono text-sm">
                                                {zenn.branch}
                                            </dd>
                                        </div>
                                    </dl>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {__('GitHub sync is not configured.')}
                                    </p>
                                )}
                                <div className="grid gap-4 md:grid-cols-2">
                                    <section className="flex min-w-0 flex-col gap-4 rounded-lg border p-5">
                                        <div className="space-y-2">
                                            <h2 className="font-semibold">
                                                {__('Articles')}
                                            </h2>
                                            <p className="text-sm text-muted-foreground">
                                                {__(
                                                    'Import Japanese articles from articles/ and public images from images/. Published articles will also be public in InkRail.',
                                                )}
                                            </p>
                                        </div>
                                        <div className="mt-auto">
                                            <Button
                                                type="button"
                                                disabled={
                                                    !zenn.configured ||
                                                    importing
                                                }
                                                onClick={() =>
                                                    openImport('articles')
                                                }
                                            >
                                                <Download aria-hidden="true" />
                                                {__('Import articles')}
                                            </Button>
                                        </div>
                                    </section>
                                    <section className="flex min-w-0 flex-col gap-4 rounded-lg border border-violet-200 bg-violet-50 p-5 dark:border-violet-800 dark:bg-violet-950/40">
                                        <div className="space-y-2">
                                            <h2 className="font-semibold text-violet-950 dark:text-violet-100">
                                                sandbox/
                                            </h2>
                                            <p className="text-sm text-muted-foreground">
                                                {__(
                                                    'Import Markdown files from sandbox/ and public images from images/.',
                                                )}
                                            </p>
                                        </div>
                                        <div className="mt-auto flex flex-wrap gap-2">
                                            <Button
                                                disabled={
                                                    !zenn.configured ||
                                                    importing
                                                }
                                                onClick={() =>
                                                    openImport('sandbox')
                                                }
                                            >
                                                <Download aria-hidden="true" />
                                                {__('Import into sandbox')}
                                            </Button>
                                            <Button asChild variant="outline">
                                                <Link
                                                    href={index({
                                                        query: {
                                                            namespace:
                                                                'sandbox',
                                                        },
                                                    })}
                                                >
                                                    <Files aria-hidden="true" />
                                                    {__(
                                                        'View sandbox articles',
                                                    )}
                                                </Link>
                                            </Button>
                                        </div>
                                    </section>
                                </div>
                            </CardContent>
                        </Card>
                        <Dialog
                            open={importDialogOpen}
                            onOpenChange={(open) => {
                                if (importing) return;
                                setImportDialogOpen(open);
                                if (!open) setImportLogs([]);
                            }}
                        >
                            <DialogContent
                                onEscapeKeyDown={(event) => {
                                    if (importing) event.preventDefault();
                                }}
                                onInteractOutside={(event) => {
                                    if (importing) event.preventDefault();
                                }}
                                onOpenAutoFocus={(event) => {
                                    event.preventDefault();
                                    cancelImportRef.current?.focus();
                                }}
                            >
                                <DialogTitle>
                                    {__(
                                        importTarget === 'sandbox'
                                            ? 'Import sandbox documents from this repository?'
                                            : 'Import articles from this repository?',
                                    )}
                                </DialogTitle>
                                <DialogDescription asChild>
                                    <div className="space-y-3">
                                        <p className="break-all">
                                            {zenn.repository} · {zenn.branch}
                                        </p>
                                        <p>
                                            {__(
                                                importTarget === 'sandbox'
                                                    ? 'Markdown files under sandbox/ and images under images/ will be imported. Existing documents without local changes may be updated. Conflicting documents will be skipped.'
                                                    : 'New articles will be added to your account. Linked articles without local changes may be updated. Conflicting articles will be skipped.',
                                            )}
                                        </p>
                                        <p>
                                            {__(
                                                importTarget === 'sandbox'
                                                    ? 'Imported sandbox documents stay private in InkRail. Images are publicly accessible. This operation does not write to GitHub.'
                                                    : 'Published articles and imported images are public in InkRail. This operation does not write to GitHub.',
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
                                        disabled={importing || importSucceeded}
                                        onClick={() => void importFromGitHub()}
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
                        {result && (
                            <section
                                className="space-y-4 rounded-xl border p-6"
                                aria-live="polite"
                            >
                                <h2 className="font-semibold">
                                    {__(
                                        resultTarget === 'sandbox'
                                            ? 'Sandbox import results'
                                            : 'Import results',
                                    )}
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
                        <LinkedArticles articles={zenn.articles} />
                        <LinkedArticles
                            articles={zenn.sandboxArticles}
                            sandbox
                        />
                    </>
                ) : (
                    <Card>
                        <CardHeader>
                            <CardTitle>{__('GitHub sync')}</CardTitle>
                            <CardDescription>
                                {__(
                                    'GitHub sync is disabled for this account.',
                                )}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col items-start gap-4">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <button
                                        type="button"
                                        className="cursor-help rounded-sm text-left text-sm text-muted-foreground underline decoration-dotted underline-offset-4 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        {__(
                                            'GitHub sync requires a verified, non-sandbox account authorized by an administrator.',
                                        )}
                                    </button>
                                </TooltipTrigger>
                                <TooltipContent className="max-w-sm space-y-2">
                                    <ul className="list-disc space-y-2 pl-4">
                                        {[
                                            'The user record must have a non-null email_verified_at value.',
                                            'The user record must have is_sandbox set to false.',
                                            'Set ZENN_PUBLISHERS in .env to a comma-separated list containing your username, not your email address. Matching is case-insensitive.',
                                            'GitHub sync also requires ZENN_GITHUB_TOKEN, ZENN_GITHUB_REPOSITORY (owner/repository), and ZENN_GITHUB_BRANCH (defaults to main) in .env.',
                                            'Refresh the configuration cache after changing environment settings if configuration caching is enabled.',
                                        ].map((detail) => (
                                            <li key={detail}>{__(detail)}</li>
                                        ))}
                                    </ul>
                                </TooltipContent>
                            </Tooltip>
                            <Button type="button" disabled>
                                <Download aria-hidden="true" />
                                {__('Import from GitHub')}
                            </Button>
                        </CardContent>
                    </Card>
                )}
                {backups && <ArticleBackupsCard backups={backups} />}
            </div>
        </>
    );
}
