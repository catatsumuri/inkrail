import { lang } from '@erag/lang-sync-inertia/react';
import { useHttp } from '@inertiajs/react';
import { Code2, Eye, ImagePlus } from 'lucide-react';
import { toast } from 'sonner';
import { slugify } from '@catatsumuri/inkstream/advanced';
import { useDeferredValue, useEffect, useRef, useState } from 'react';
import { store as storeImage } from '@/actions/App/Http/Controllers/ArticleImageController';
import ArticleContent from '@/components/article-content';
import { Button } from '@/components/ui/button';
import { listHeadings } from '@/lib/heading-offset';
import { buildAnchors, mapScroll, type ScrollAnchor } from '@/lib/scroll-sync';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    name: string;
    defaultValue: string;
    invalid?: boolean;
    describedBy?: string;
    syntax?: 'all' | 'zenn';
    jumpTo?: number;
};

export default function MarkdownEditor({
    id,
    name,
    defaultValue,
    invalid = false,
    describedBy,
    syntax = 'all',
    jumpTo,
}: Props) {
    const { __ } = lang();
    const [content, setContent] = useState(defaultValue);
    const preview = useDeferredValue(content);
    const [activePanel, setActivePanel] = useState<'write' | 'preview'>(
        'write',
    );

    const [uploading, setUploading] = useState(0);
    const [dragging, setDragging] = useState(false);
    const imageUpload = useHttp<{ image: File | null }, { url: string }>({
        image: null,
    });

    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const previewRef = useRef<HTMLDivElement>(null);

    const anchorCache = useRef<{
        key: string;
        anchors: ScrollAnchor[];
    } | null>(null);

    useEffect(() => {
        anchorCache.current = null;
    }, [preview]);

    function imageFrom(files: FileList | null | undefined): File | undefined {
        return Array.from(files ?? []).find((file) =>
            file.type.startsWith('image/'),
        );
    }

    async function uploadImage(file: File) {
        let reported = false;

        setUploading((count) => count + 1);
        imageUpload.transform(() => ({ image: file }));

        try {
            const { url } = await imageUpload.post(storeImage.url(), {
                onError: (errors) => {
                    reported = true;
                    toast.error(
                        errors.image ?? __('Could not upload the image.'),
                    );
                },
            });
            const textarea = textareaRef.current;

            if (textarea) {
                const alt =
                    file.name
                        .replace(/\.[^.]+$/, '')
                        .replace(/[[\]]/g, '')
                        .trim() || 'image';
                const markdown = `![${alt}](${url})\n`;

                textarea.setRangeText(
                    markdown,
                    textarea.selectionStart,
                    textarea.selectionEnd,
                    'end',
                );
                setContent(textarea.value);
                textarea.focus();
            }
        } catch {
            if (!reported) {
                toast.error(__('Could not upload the image.'));
            }
        } finally {
            setUploading((count) => count - 1);
        }
    }

    function handleDrop(event: React.DragEvent<HTMLTextAreaElement>) {
        setDragging(false);

        const file = imageFrom(event.dataTransfer.files);

        if (file) {
            event.preventDefault();
            void uploadImage(file);
        }
    }

    function handlePaste(event: React.ClipboardEvent<HTMLTextAreaElement>) {
        const file = imageFrom(event.clipboardData.files);

        if (file) {
            event.preventDefault();
            void uploadImage(file);
        }
    }

    function measureAnchors(
        textarea: HTMLTextAreaElement,
        pane: HTMLDivElement,
    ): ScrollAnchor[] {
        const maxFrom = textarea.scrollHeight - textarea.clientHeight;
        const maxTo = pane.scrollHeight - pane.clientHeight;
        const key = `${textarea.value.length}:${textarea.clientWidth}:${pane.scrollHeight}`;

        if (anchorCache.current?.key === key) {
            return anchorCache.current.anchors;
        }

        const source = listHeadings(textarea.value).filter(
            (heading) => heading.level <= 4,
        );
        const rendered = pane.querySelectorAll('.ink-heading');
        let headings: ScrollAnchor[] = [];

        if (source.length > 0 && source.length === rendered.length) {
            const style = window.getComputedStyle(textarea);
            const mirror = document.createElement('div');
            mirror.style.cssText = `position:fixed;visibility:hidden;left:-200%;top:0;height:auto;white-space:pre-wrap;overflow-wrap:break-word;box-sizing:${style.boxSizing};font:${style.font};line-height:${style.lineHeight};padding:${style.padding};width:${textarea.clientWidth}px`;
            const paddingBottom = Number.parseFloat(style.paddingBottom) || 0;
            const paneTop = pane.getBoundingClientRect().top;
            document.body.appendChild(mirror);
            headings = source.map((heading, index) => {
                mirror.textContent = textarea.value.slice(0, heading.offset);

                return {
                    from: mirror.scrollHeight - paddingBottom,
                    to:
                        pane.scrollTop +
                        rendered[index].getBoundingClientRect().top -
                        paneTop,
                };
            });
            document.body.removeChild(mirror);
        }

        const anchors = buildAnchors(headings, maxFrom, maxTo);
        anchorCache.current = { key, anchors };

        return anchors;
    }

    function syncPreview() {
        const textarea = textareaRef.current;
        const pane = previewRef.current;

        if (
            !textarea ||
            !pane ||
            !window.matchMedia('(min-width: 1024px)').matches ||
            textarea.scrollHeight <= textarea.clientHeight
        ) {
            return;
        }

        const anchors = measureAnchors(textarea, pane);

        pane.scrollTop =
            anchors.length > 1
                ? mapScroll(anchors, textarea.scrollTop)
                : (textarea.scrollTop /
                      (textarea.scrollHeight - textarea.clientHeight)) *
                  (pane.scrollHeight - pane.clientHeight);
    }

    useEffect(() => {
        const textarea = textareaRef.current;

        if (!textarea || jumpTo === undefined || !Number.isFinite(jumpTo)) {
            return;
        }

        const start = Math.max(0, Math.min(jumpTo, textarea.value.length));
        const lineEnd = textarea.value.indexOf('\n', start);
        const end = lineEnd === -1 ? textarea.value.length : lineEnd;

        textarea.focus({ preventScroll: true });
        textarea.setSelectionRange(start, end);

        const style = window.getComputedStyle(textarea);
        const lineHeight = Number.parseFloat(style.lineHeight) || 24;
        const mirror = document.createElement('div');
        mirror.style.cssText = `position:fixed;visibility:hidden;left:-200%;top:0;height:auto;white-space:pre-wrap;overflow-wrap:break-word;box-sizing:${style.boxSizing};font:${style.font};line-height:${style.lineHeight};padding:${style.padding};width:${textarea.clientWidth}px`;
        mirror.textContent = textarea.value.slice(0, start);
        document.body.appendChild(mirror);
        const top = mirror.scrollHeight;
        document.body.removeChild(mirror);
        textarea.scrollTo({ top: Math.max(0, top - lineHeight * 2) });

        const heading = /^#{1,6}\s+(.+?)(?:\s+#+\s*)?$/.exec(
            textarea.value.slice(start, end),
        );
        const preview = previewRef.current;
        const target = heading
            ? preview?.querySelector(`#${CSS.escape(slugify(heading[1]))}`)
            : null;

        if (preview && target) {
            preview.scrollTo({
                top:
                    preview.scrollTop +
                    target.getBoundingClientRect().top -
                    preview.getBoundingClientRect().top -
                    16,
            });
        }
    }, [jumpTo]);

    return (
        <div className="overflow-hidden rounded-xl border bg-background">
            <div className="flex gap-1 border-b p-2 lg:hidden">
                <Button
                    type="button"
                    variant={activePanel === 'write' ? 'secondary' : 'ghost'}
                    size="sm"
                    aria-pressed={activePanel === 'write'}
                    aria-controls={`${id}-write`}
                    onClick={() => setActivePanel('write')}
                >
                    <Code2 aria-hidden="true" />
                    {__('Markdown')}
                </Button>
                <Button
                    type="button"
                    variant={activePanel === 'preview' ? 'secondary' : 'ghost'}
                    size="sm"
                    aria-pressed={activePanel === 'preview'}
                    aria-controls={`${id}-preview`}
                    onClick={() => setActivePanel('preview')}
                >
                    <Eye aria-hidden="true" />
                    {__('Preview')}
                </Button>
            </div>
            <div className="grid min-w-0 lg:grid-cols-2">
                <div
                    id={`${id}-write`}
                    className={cn(
                        'min-w-0 lg:border-r',
                        activePanel !== 'write' && 'hidden lg:block',
                    )}
                >
                    <div className="hidden items-center gap-2 border-b bg-muted/30 px-5 py-3 text-xs font-medium text-muted-foreground lg:flex">
                        <Code2 aria-hidden="true" className="size-3.5" />
                        {__('Markdown')}
                        <span className="ml-auto flex items-center gap-1.5 font-normal">
                            <ImagePlus
                                aria-hidden="true"
                                className="size-3.5"
                            />
                            {uploading > 0
                                ? __('Uploading image…')
                                : __('Drop or paste an image to upload')}
                        </span>
                    </div>
                    <textarea
                        id={id}
                        ref={textareaRef}
                        onScroll={syncPreview}
                        name={name}
                        defaultValue={defaultValue}
                        onChange={(event) => setContent(event.target.value)}
                        onDrop={handleDrop}
                        onDragOver={(event) => {
                            if (event.dataTransfer.types.includes('Files')) {
                                event.preventDefault();
                                setDragging(true);
                            }
                        }}
                        onDragLeave={() => setDragging(false)}
                        onPaste={handlePaste}
                        aria-invalid={invalid}
                        aria-describedby={describedBy}
                        spellCheck={false}
                        className={cn(
                            'block h-[60vh] min-h-80 w-full resize-none bg-transparent p-5 font-mono text-sm leading-7 outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset sm:p-6',
                            dragging &&
                                'bg-muted/50 ring-2 ring-ring ring-inset',
                        )}
                    />
                </div>
                <section
                    id={`${id}-preview`}
                    aria-label={__('Preview')}
                    className={cn(
                        'min-w-0',
                        activePanel !== 'preview' && 'hidden lg:block',
                    )}
                >
                    <div className="hidden items-center gap-2 border-b bg-muted/30 px-5 py-3 text-xs font-medium text-muted-foreground lg:flex">
                        <Eye aria-hidden="true" className="size-3.5" />
                        {__('Preview')}
                    </div>
                    <div
                        ref={previewRef}
                        tabIndex={0}
                        className="h-[60vh] min-h-80 overflow-auto p-5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset sm:p-6"
                    >
                        {content.trim() ? (
                            <ArticleContent content={preview} syntax={syntax} />
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {__('Your Markdown preview will appear here.')}
                            </p>
                        )}
                    </div>
                </section>
            </div>
        </div>
    );
}
