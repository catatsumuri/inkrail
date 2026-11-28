import { Form } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { lang } from '@erag/lang-sync-inertia/react';
import { Download, RotateCcw, Trash2, Upload } from 'lucide-react';
import ArticleBackupController from '@/actions/App/Http/Controllers/ArticleBackupController';
import { validateBackupFiles } from '@/lib/backup-files';
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
    items: {
        filename: string;
        size: number;
        created_at: string;
        description: string | null;
    }[];
};

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function RestoreDialog({ filename }: { filename: string }) {
    const { __ } = lang();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
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
                                'Articles in the backup overwrite the articles they were saved from, matched by their sync ID rather than the database ID, including translations and history. Nothing is deleted, and articles not in the backup are kept.',
                            )}
                        </p>
                        <p>
                            {__(
                                'Sandbox GitHub links are restored only in the environment that created the backup. Backups from another environment restore content, history and images without those links. Conflicting links or repository images stop the entire restore.',
                            )}
                        </p>
                    </div>
                </DialogDescription>
                <Form
                    {...ArticleBackupController.restore.form(filename)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
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

function CreateDialog() {
    const { __ } = lang();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button">{__('Create backup')}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{__('Create a backup?')}</DialogTitle>
                <DialogDescription>
                    {__(
                        'Every article with its translations, history and images will be saved.',
                    )}
                </DialogDescription>
                <Form
                    {...ArticleBackupController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor="backup-description">
                                    {__('Description (optional)')}
                                </Label>
                                <Input
                                    id="backup-description"
                                    name="description"
                                    maxLength={2000}
                                    autoComplete="off"
                                />
                                <InputError message={errors.description} />
                            </div>
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button type="button" variant="secondary">
                                        {__('Cancel')}
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    {__('Create backup')}
                                </Button>
                            </DialogFooter>
                        </>
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

    const inputRef = useRef<HTMLInputElement>(null);
    const dragDepth = useRef(0);
    const [dragging, setDragging] = useState(false);
    const [selection, setSelection] = useState<File | null>(null);
    const [fileError, setFileError] = useState<string | null>(null);

    function selectFiles(files: File[]) {
        const error = validateBackupFiles(files);
        setFileError(error);
        if (error || files.length === 0) return;
        const transfer = new DataTransfer();
        transfer.items.add(files[0]);
        if (inputRef.current) inputRef.current.files = transfer.files;
        setSelection(files[0]);
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>{__('Backups')}</CardTitle>
                <CardDescription>
                    {__(
                        'Back up every article with its translations, history and images. Backups are stored on the ":disk" disk, outside the database. They survive migrate:fresh, but surviving redeploys requires persistent storage such as R2. Local files may be lost on redeploy.',
                        { disk: backups.disk },
                    )}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="flex flex-col items-start gap-4">
                    <CreateDialog />
                    <Form
                        {...ArticleBackupController.upload.form()}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        onSuccess={() => {
                            setSelection(null);
                            setFileError(null);
                        }}
                        className="w-full"
                    >
                        {({ processing, errors }) => (
                            <div className="space-y-3">
                                <div
                                    className={`flex min-h-0 flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-5 py-4 text-center transition-colors ${dragging ? 'border-primary bg-primary/10 ring-4 ring-primary/10' : 'border-muted-foreground/35 bg-muted/20 hover:border-primary/60 hover:bg-muted/40'}`}
                                    onDragEnter={(event) => {
                                        event.preventDefault();
                                        if (
                                            processing ||
                                            !event.dataTransfer.types.includes(
                                                'Files',
                                            )
                                        )
                                            return;
                                        dragDepth.current++;
                                        setDragging(true);
                                    }}
                                    onDragOver={(event) =>
                                        event.preventDefault()
                                    }
                                    onDragLeave={() => {
                                        dragDepth.current = Math.max(
                                            0,
                                            dragDepth.current - 1,
                                        );
                                        if (dragDepth.current === 0)
                                            setDragging(false);
                                    }}
                                    onDrop={(event) => {
                                        event.preventDefault();
                                        dragDepth.current = 0;
                                        setDragging(false);
                                        if (!processing)
                                            selectFiles(
                                                Array.from(
                                                    event.dataTransfer.files,
                                                ),
                                            );
                                    }}
                                >
                                    <div className="flex size-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <Upload
                                            aria-hidden="true"
                                            className="size-5"
                                        />
                                    </div>
                                    <p className="max-w-lg text-sm font-medium">
                                        {__(
                                            'Drop a backup ZIP here or choose a file (up to 100 MiB).',
                                        )}
                                    </p>
                                    <Input
                                        ref={inputRef}
                                        disabled={processing}
                                        onChange={(event) => {
                                            const files = Array.from(
                                                event.target.files ?? [],
                                            );
                                            selectFiles(files);
                                            if (validateBackupFiles(files)) {
                                                event.target.value = '';
                                                setSelection(null);
                                            }
                                        }}
                                        type="file"
                                        name="backup"
                                        accept=".zip"
                                        required
                                        aria-label={__('Backup file')}
                                        className="max-w-xs cursor-pointer bg-background text-left"
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={
                                            processing ||
                                            !selection ||
                                            !!fileError
                                        }
                                    >
                                        {processing ? (
                                            <Spinner />
                                        ) : (
                                            <Upload aria-hidden="true" />
                                        )}
                                        {__('Upload backup')}
                                    </Button>
                                </div>
                                <p aria-live="polite" className="text-sm">
                                    {selection &&
                                        `${selection.name} · ${formatSize(selection.size)}`}
                                </p>
                                <InputError
                                    message={
                                        fileError
                                            ? __(fileError)
                                            : errors.backup
                                    }
                                />
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
                                    {backup.description && (
                                        <p className="text-sm break-words">
                                            {backup.description}
                                        </p>
                                    )}
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
