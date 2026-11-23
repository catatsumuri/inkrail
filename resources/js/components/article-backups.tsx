import { Form } from '@inertiajs/react';
import { lang } from '@erag/lang-sync-inertia/react';
import { Download, RotateCcw, Trash2, Upload } from 'lucide-react';
import ArticleBackupController from '@/actions/App/Http/Controllers/ArticleBackupController';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export type ArticleBackups = {
    disk: string;
    items: { filename: string; size: number; created_at: string }[];
};

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function RestoreDialog({ filename }: { filename: string }) {
    const { __ } = lang();

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                    <RotateCcw aria-hidden="true" />
                    {__('Restore')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{__('Restore this backup?')}</DialogTitle>
                <DialogDescription asChild>
                    <div className="space-y-3">
                        <p className="break-all">{filename}</p>
                        <p>
                            {__(
                                'Articles in the backup overwrite articles with the same ID, including their translations and history. Nothing is deleted, and articles not in the backup are kept.',
                            )}
                        </p>
                    </div>
                </DialogDescription>
                <Form
                    {...ArticleBackupController.restore.form(filename)}
                    options={{ preserveScroll: true }}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor={`restore-${filename}`}>
                                    {__('Type "restore" to confirm')}
                                </Label>
                                <Input
                                    id={`restore-${filename}`}
                                    name="confirmation"
                                    autoComplete="off"
                                    required
                                />
                                <InputError message={errors.confirmation} />
                                <InputError message={errors.backup} />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        {__('Cancel')}
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {__('Restore backup')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteDialog({ filename }: { filename: string }) {
    const { __ } = lang();

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                >
                    <Trash2 aria-hidden="true" />
                    <span className="sr-only">{__('Delete backup')}</span>
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{__('Delete this backup?')}</DialogTitle>
                <DialogDescription>
                    {__(
                        'The backup file :filename will be permanently removed.',
                        {
                            filename,
                        },
                    )}
                </DialogDescription>
                <Form
                    {...ArticleBackupController.destroy.form(filename)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button type="button" variant="secondary">
                                    {__('Cancel')}
                                </Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                {__('Delete backup')}
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function ArticleBackupsCard({
    backups,
}: {
    backups: ArticleBackups;
}) {
    const { __ } = lang();

    return (
        <Card>
            <CardHeader>
                <CardTitle>{__('Backups')}</CardTitle>
                <CardDescription>
                    {__(
                        'Back up every article with its translations, history and images. Backups are stored on the ":disk" disk, so they survive migrate:fresh and redeploys.',
                        { disk: backups.disk },
                    )}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="flex flex-wrap items-start gap-4">
                    <Form
                        {...ArticleBackupController.store.form()}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {__('Create backup')}
                            </Button>
                        )}
                    </Form>
                    <Form
                        {...ArticleBackupController.upload.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="flex flex-wrap items-start gap-2"
                    >
                        {({ processing, errors }) => (
                            <div className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Input
                                        type="file"
                                        name="backup"
                                        accept=".zip"
                                        required
                                        aria-label={__('Backup file')}
                                        className="w-auto"
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <Upload aria-hidden="true" />
                                        )}
                                        {__('Upload backup')}
                                    </Button>
                                </div>
                                <InputError message={errors.backup} />
                            </div>
                        )}
                    </Form>
                </div>

                {backups.items.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        {__('No backups yet.')}
                    </p>
                ) : (
                    <ul className="divide-y border-y">
                        {backups.items.map((backup) => (
                            <li
                                key={backup.filename}
                                className="flex flex-wrap items-center justify-between gap-4 py-4"
                            >
                                <div className="min-w-0 space-y-1">
                                    <p className="font-medium break-all">
                                        {backup.filename}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {new Intl.DateTimeFormat(undefined, {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                        }).format(
                                            new Date(backup.created_at),
                                        )}{' '}
                                        · {formatSize(backup.size)}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button asChild variant="outline" size="sm">
                                        <a
                                            href={ArticleBackupController.download.url(
                                                backup.filename,
                                            )}
                                        >
                                            <Download aria-hidden="true" />
                                            {__('Download')}
                                        </a>
                                    </Button>
                                    <RestoreDialog filename={backup.filename} />
                                    <DeleteDialog filename={backup.filename} />
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
