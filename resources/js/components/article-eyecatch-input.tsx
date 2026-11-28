import { lang } from '@erag/lang-sync-inertia/react';
import { useHttp } from '@inertiajs/react';
import { ImagePlus, LoaderCircle } from 'lucide-react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { store } from '@/actions/App/Http/Controllers/ArticleImageController';
import ArticleCover from '@/components/article-cover';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function ArticleEyecatchInput({
    defaultValue,
    error,
    onUploadingChange,
}: {
    defaultValue?: string | null;
    error?: string;
    onUploadingChange: (uploading: boolean) => void;
}) {
    const { __ } = lang();
    const [value, setValue] = useState(defaultValue ?? '');
    const fileInput = useRef<HTMLInputElement>(null);
    const dragDepth = useRef(0);
    const [dragging, setDragging] = useState(false);
    const uploading = useRef(false);
    const upload = useHttp<{ image: File | null }, { url: string }>({
        image: null,
    });
    const filename = value.startsWith('/images/') ? value.slice(8) : value;
    const validPreview =
        /^(?:[a-zA-Z0-9_-]+\/)*[a-zA-Z0-9][a-zA-Z0-9._-]*\.(png|jpg|jpeg|gif|webp)$/.test(
            filename,
        );

    async function uploadImage(file: File) {
        if (uploading.current) return;
        uploading.current = true;
        onUploadingChange(true);
        upload.transform(() => ({ image: file }));
        try {
            const { url } = await upload.post(store.url());
            setValue(url.replace(/^\/images\//, ''));
        } catch {
            toast.error(
                upload.errors.image ?? __('Could not upload the image.'),
            );
        } finally {
            uploading.current = false;
            onUploadingChange(false);
        }
    }

    return (
        <div className="grid max-w-3xl gap-3">
            <Label htmlFor="eyecatch">{__('Cover image')}</Label>
            <Input
                id="eyecatch"
                name="eyecatch"
                value={value}
                onChange={(event) => setValue(event.target.value)}
                placeholder="image.png"
                aria-invalid={Boolean(error)}
                aria-describedby="eyecatch-help eyecatch-error"
                className="min-w-48 flex-1"
            />
            <div
                className={`flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-5 py-4 text-center transition-colors ${dragging ? 'border-primary bg-primary/10 ring-4 ring-primary/10' : 'border-muted-foreground/35 bg-muted/20 hover:border-primary/60 hover:bg-muted/40'}`}
                onDragEnter={(event) => {
                    event.preventDefault();
                    if (
                        uploading.current ||
                        !event.dataTransfer.types.includes('Files')
                    )
                        return;
                    dragDepth.current++;
                    setDragging(true);
                }}
                onDragOver={(event) => event.preventDefault()}
                onDragLeave={() => {
                    dragDepth.current = Math.max(0, dragDepth.current - 1);
                    if (dragDepth.current === 0) setDragging(false);
                }}
                onDrop={(event) => {
                    event.preventDefault();
                    dragDepth.current = 0;
                    setDragging(false);
                    const file = event.dataTransfer.files[0];
                    if (file) void uploadImage(file);
                }}
            >
                <p className="text-sm font-medium">
                    {__('Drop a cover image here or choose a file.')}
                </p>
                <Button
                    type="button"
                    variant="outline"
                    disabled={upload.processing}
                    onClick={() => fileInput.current?.click()}
                >
                    {upload.processing ? (
                        <LoaderCircle className="size-4 animate-spin" />
                    ) : (
                        <ImagePlus className="size-4" />
                    )}
                    {__('Upload image')}
                </Button>
                <input
                    ref={fileInput}
                    type="file"
                    accept="image/png,image/jpeg,image/gif,image/webp"
                    aria-label={__('Upload image')}
                    className="hidden"
                    disabled={upload.processing}
                    onChange={(event) => {
                        const file = event.target.files?.[0];
                        if (file) void uploadImage(file);
                        event.target.value = '';
                    }}
                />
            </div>
            <p
                id="eyecatch-help"
                className="text-sm leading-relaxed text-muted-foreground"
            >
                {__(
                    'Upload a cover or enter an image filename. Leave empty for a typographic cover.',
                )}
            </p>
            {validPreview && (
                <ArticleCover
                    src={`/images/${filename}`}
                    title={__('Cover image')}
                    className="aspect-video w-full max-w-sm rounded-xl border"
                />
            )}
            <InputError
                id="eyecatch-error"
                message={error ?? upload.errors.image}
            />
        </div>
    );
}
