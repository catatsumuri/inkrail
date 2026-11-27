import { lang } from '@erag/lang-sync-inertia/react';
import { Form, Head, Link, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import {
    index,
    restore,
    destroy,
} from '@/actions/App/Http/Controllers/ArticleHistoryController';
import {
    index as articlesIndex,
    show,
} from '@/actions/App/Http/Controllers/ArticleController';
import InputError from '@/components/input-error';
import LanguageToggle from '@/components/language-toggle';
import TranslationStatus from '@/components/translation-status';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogTrigger,
    DialogContent,
    DialogTitle,
    DialogDescription,
    DialogFooter,
    DialogClose,
} from '@/components/ui/dialog';
import { lineDiff } from '@/lib/line-diff';
import type { Article } from '@/types/article';

type Revision = {
    id: number;
    title: string;
    content: string;
    is_deleted: boolean;
    created_at: string;
    user_id?: number;
    restored_from_id?: number;
};
type Props = {
    article: Article;
    locale: string;
    revisions: {
        data: Revision[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    selection: { from: Revision | null; to: Revision | null };
    translations: Record<string, { title: string; content: string }>;
};

function Operation({
    label,
    form,
    version,
    destructive = false,
}: {
    label: string;
    form: { action: string; method: 'post' | 'get' };
    version: number;
    destructive?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const { __ } = lang();
    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant={destructive ? 'outline' : 'secondary'}
                    size="sm"
                >
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{label}</DialogTitle>
                <DialogDescription>
                    {__(
                        'This action applies to the saved versions. Unsaved edits are not included.',
                    )}
                </DialogDescription>
                <Form {...form} onSuccess={() => setOpen(false)}>
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="edit_version"
                                value={version}
                            />
                            <InputError
                                message={errors.history ?? errors.edit_version}
                            />
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        disabled={processing}
                                    >
                                        {__('Cancel')}
                                    </Button>
                                </DialogClose>
                                <Button
                                    disabled={processing}
                                    variant={
                                        destructive ? 'destructive' : 'default'
                                    }
                                >
                                    {label}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function History({
    article,
    locale,
    revisions,
    selection,
    translations,
}: Props) {
    const { __ } = lang();
    const [from, setFrom] = useState(selection.from?.id.toString() ?? '');
    setLayoutProps({
        breadcrumbs: [
            {
                title: __(article.is_sandbox ? 'Sandbox' : 'My articles'),
                href: article.is_sandbox
                    ? articlesIndex({ query: { namespace: 'sandbox' } })
                    : articlesIndex(),
            },
            { title: article.title, href: show(article.id) },
            {
                title: __('History and correspondence'),
                href: index({ article: article.id }, { query: { locale } }),
            },
        ],
    });
    const [to, setTo] = useState(selection.to?.id.toString() ?? '');
    const older = selection.from;
    const newer = selection.to;
    const diff =
        older && newer
            ? lineDiff(
                  older.is_deleted ? '' : `${older.title}\n\n${older.content}`,
                  newer.is_deleted ? '' : `${newer.title}\n\n${newer.content}`,
              )
            : null;
    const version = article.edit_version ?? 0;
    function paginationHref(url: string) {
        return index(
            { article: article.id },
            {
                query: {
                    page: new URL(url).searchParams.get('page'),
                    locale,
                    from: from || undefined,
                    to: to || undefined,
                },
            },
        );
    }
    const options = [
        ...new Map(
            [
                ...revisions.data,
                ...[older, newer].filter(
                    (item): item is Revision => item !== null,
                ),
            ].map((item) => [item.id, item]),
        ).values(),
    ];
    return (
        <>
            <Head title={__('History and correspondence')} />
            <div className="mx-auto w-full max-w-6xl space-y-8 px-4 py-8 sm:px-8">
                <Link
                    href={show(article.id)}
                    className="text-sm text-muted-foreground hover:text-foreground"
                >
                    {__('Back to article')}
                </Link>
                <header className="space-y-4">
                    <h1 className="text-2xl font-semibold">{article.title}</h1>
                    {!article.is_sandbox && (
                        <TranslationStatus
                            articleId={article.id}
                            states={article.translationStatus}
                        />
                    )}
                </header>
                <section className="grid gap-4 sm:grid-cols-2">
                    {(article.is_sandbox ? ['ja'] : ['en', 'ja']).map(
                        (language) => (
                            <div
                                key={language}
                                className="min-w-0 rounded-lg border p-4"
                            >
                                <h2 className="mb-3 text-sm font-medium">
                                    {article.is_sandbox
                                        ? __('Content')
                                        : language === 'ja'
                                          ? '日本語'
                                          : 'English'}
                                </h2>
                                {translations[language] ? (
                                    <>
                                        <h3 className="font-medium">
                                            {translations[language].title}
                                        </h3>
                                        <pre className="max-h-64 overflow-auto py-3 text-sm break-words whitespace-pre-wrap">
                                            {translations[language].content}
                                        </pre>
                                        {!article.is_sandbox && (
                                            <Operation
                                                label={__(
                                                    'Delete language version',
                                                )}
                                                form={destroy.form({
                                                    article: article.id,
                                                    translationLocale: language,
                                                })}
                                                version={version}
                                                destructive
                                            />
                                        )}
                                    </>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {article.translationStatus?.[language]
                                            ?.state === 'deleted'
                                            ? __('Deleted')
                                            : __('Not created')}
                                    </p>
                                )}
                            </div>
                        ),
                    )}
                </section>
                {!article.is_sandbox && (
                    <LanguageToggle
                        locales={['en', 'ja']}
                        value={locale}
                        onValueChange={(language) =>
                            router.get(index.url({ article: article.id }), {
                                locale: language,
                            })
                        }
                        label={__('Article language')}
                    />
                )}
                <div className="flex flex-wrap items-end gap-3">
                    {[
                        { label: __('Before'), value: from, set: setFrom },
                        { label: __('After'), value: to, set: setTo },
                    ].map((field) => (
                        <label key={field.label} className="grid gap-2 text-sm">
                            {field.label}
                            <select
                                value={field.value}
                                onChange={(event) =>
                                    field.set(event.target.value)
                                }
                                className="max-w-72 rounded-md border bg-background p-2"
                            >
                                <option value="">
                                    {__('Select a version')}
                                </option>
                                {options.map((revision) => (
                                    <option
                                        key={revision.id}
                                        value={revision.id}
                                    >
                                        #{revision.id} · {revision.title}
                                    </option>
                                ))}
                            </select>
                        </label>
                    ))}
                    <Button
                        type="button"
                        disabled={!from || !to}
                        onClick={() =>
                            router.get(
                                index.url({ article: article.id }),
                                { locale, from, to },
                                { preserveScroll: true },
                            )
                        }
                    >
                        {__('Compare')}
                    </Button>
                </div>
                {older && newer && (
                    <section className="overflow-hidden rounded-lg border">
                        <h2 className="border-b p-4 text-sm font-medium">
                            #{older.id} → #{newer.id}
                            {older.is_deleted || newer.is_deleted
                                ? ` · ${__('Includes deletion')}`
                                : ''}
                        </h2>
                        {diff ? (
                            <pre className="overflow-auto py-3 text-sm">
                                {diff.map((line, i) => (
                                    <div
                                        key={i}
                                        className={`min-w-fit px-4 ${line.type === 'add' ? 'bg-emerald-500/10' : line.type === 'remove' ? 'bg-red-500/10' : ''}`}
                                    >
                                        <span className="mr-3 select-none">
                                            {line.type === 'add'
                                                ? '+'
                                                : line.type === 'remove'
                                                  ? '−'
                                                  : ' '}
                                        </span>
                                        {line.text || ' '}
                                    </div>
                                ))}
                            </pre>
                        ) : (
                            <>
                                <p className="p-4 text-sm">
                                    {__(
                                        'This comparison is too large for an inline diff. Showing both versions.',
                                    )}
                                </p>
                                <div className="grid gap-4 p-4 sm:grid-cols-2">
                                    {[older, newer].map((revision) => (
                                        <pre
                                            key={revision.id}
                                            className="max-h-96 overflow-auto text-sm break-words whitespace-pre-wrap"
                                        >
                                            {revision.title}
                                            {'\n\n'}
                                            {revision.content}
                                        </pre>
                                    ))}
                                </div>
                            </>
                        )}
                    </section>
                )}
                <section className="divide-y rounded-lg border">
                    {revisions.data.map((revision) => (
                        <div
                            key={revision.id}
                            className="flex flex-wrap items-center justify-between gap-3 p-4"
                        >
                            <div>
                                <p className="text-sm font-medium">
                                    #{revision.id} · {revision.title}
                                    {revision.is_deleted
                                        ? ` · ${__('Deleted')}`
                                        : ''}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {new Date(
                                        revision.created_at,
                                    ).toLocaleString()}{' '}
                                    ·{' '}
                                    {revision.user_id
                                        ? `#${revision.user_id}`
                                        : __('System')}
                                    {revision.restored_from_id
                                        ? ` · ${__('Restored from')} #${revision.restored_from_id}`
                                        : ''}
                                </p>
                            </div>
                            {!revision.is_deleted && (
                                <Operation
                                    label={__('Restore this version')}
                                    form={restore.form({
                                        article: article.id,
                                        revision: revision.id,
                                    })}
                                    version={version}
                                />
                            )}
                        </div>
                    ))}
                </section>
                <nav className="flex justify-between text-sm">
                    {revisions.prev_page_url && (
                        <Link href={paginationHref(revisions.prev_page_url)}>
                            {__('Previous page')}
                        </Link>
                    )}
                    {revisions.next_page_url && (
                        <Link href={paginationHref(revisions.next_page_url)}>
                            {__('Next page')}
                        </Link>
                    )}
                </nav>
            </div>
        </>
    );
}
