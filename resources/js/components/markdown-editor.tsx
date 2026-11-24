import { lang } from '@erag/lang-sync-inertia/react';
import { useHttp } from '@inertiajs/react';
import {
    Code2,
    Columns2,
    Eye,
    ImagePlus,
    Maximize2,
    Minimize2,
    X,
} from 'lucide-react';
import { toast } from 'sonner';
import { slugify } from '@catatsumuri/inkstream/advanced';
import {
    useDeferredValue,
    useEffect,
    useRef,
    useState,
    type CSSProperties,
} from 'react';
import { store as storeImage } from '@/actions/App/Http/Controllers/ArticleImageController';
import ArticleContent from '@/components/article-content';
import { Button } from '@/components/ui/button';
import { listFencedBlocks, listHeadings } from '@/lib/heading-offset';
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

    const [layout, setLayout] = useState<'both' | 'write' | 'preview'>('both');
    const [maximized, setMaximized] = useState<
        'both' | 'write' | 'preview' | null
    >(null);
    const editorRef = useRef<HTMLDivElement>(null);
    const splitRef = useRef<HTMLDivElement>(null);
    const [split, setSplit] = useState(50);
    const [resizing, setResizing] = useState(false);

    function resizeSplit(value: number) {
        setSplit(Math.min(80, Math.max(20, value)));
        anchorCache.current = null;
    }

    function resizeAt(clientX: number) {
        const bounds = splitRef.current?.getBoundingClientRect();

        if (bounds && bounds.width > 0) {
            resizeSplit(((clientX - bounds.left) / bounds.width) * 100);
        }
    }

    const visiblePanel = maximized ?? layout;

    useEffect(() => {
        const editor = editorRef.current;

        function handleFullscreenChange() {
            if (document.fullscreenElement !== editor) {
                setMaximized(null);
            }
            anchorCache.current = null;
        }

        document.addEventListener('fullscreenchange', handleFullscreenChange);

        return () => {
            document.removeEventListener(
                'fullscreenchange',
                handleFullscreenChange,
            );
        };
    }, []);

    async function maximize() {
        try {
            if (document.fullscreenElement !== editorRef.current) {
                await editorRef.current?.requestFullscreen();
            }
            setMaximized(layout);
        } catch {
            toast.error(__('Could not enter full screen.'));
        }
    }

    function selectPanel(panel: 'both' | 'write' | 'preview') {
        if (maximized) {
            setMaximized(panel);
        } else {
            setLayout(panel);
        }
        anchorCache.current = null;
    }

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
        const embeds = listFencedBlocks(textarea.value, 'html-embed');
        const renderedEmbeds = pane.querySelectorAll('.ink-html-embed');
        const useHeadings =
            source.length > 0 && source.length === rendered.length;
        const useEmbeds =
            embeds.length > 0 && embeds.length === renderedEmbeds.length;
        let headings: ScrollAnchor[] = [];

        if (useHeadings || useEmbeds) {
            const style = window.getComputedStyle(textarea);
            const mirror = document.createElement('div');
            mirror.style.cssText = `position:fixed;visibility:hidden;left:-200%;top:0;height:auto;white-space:pre-wrap;overflow-wrap:break-word;box-sizing:${style.boxSizing};font:${style.font};line-height:${style.lineHeight};padding:${style.padding};width:${textarea.clientWidth}px`;
            const paddingBottom = Number.parseFloat(style.paddingBottom) || 0;
            const paneTop = pane.getBoundingClientRect().top;
            const sourceTop = (offset: number): number => {
                mirror.textContent = textarea.value.slice(0, offset);

                return mirror.scrollHeight - paddingBottom;
            };
            const renderedTop = (element: Element, edge: 'top' | 'bottom') =>
                pane.scrollTop +
                element.getBoundingClientRect()[edge] -
                paneTop;
            document.body.appendChild(mirror);

            if (useHeadings) {
                headings = source.map((heading, index) => ({
                    from: sourceTop(heading.offset),
                    to: renderedTop(rendered[index], 'top'),
                }));
            }

            if (useEmbeds) {
                for (const [index, embed] of embeds.entries()) {
                    headings.push(
                        {
                            from: sourceTop(embed.start),
                            to: renderedTop(renderedEmbeds[index], 'top'),
                        },
                        {
                            from: sourceTop(embed.end),
                            to: renderedTop(renderedEmbeds[index], 'bottom'),
                        },
                    );
                }

                headings.sort((left, right) => left.from - right.from);
            }

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
            visiblePanel !== 'both' ||
            (!maximized && !window.matchMedia('(min-width: 1024px)').matches) ||
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
        <div
            ref={editorRef}
            className={cn(
                'overflow-hidden rounded-xl border bg-background',
                maximized && 'flex h-dvh w-screen flex-col rounded-none',
            )}
        >
            <div className="flex shrink-0 flex-wrap items-center gap-1 border-b p-2">
                {(['both', 'write', 'preview'] as const).map((panel) => {
                    const Icon =
                        panel === 'write'
                            ? Code2
                            : panel === 'preview'
                              ? Eye
                              : Columns2;
                    const label =
                        panel === 'write'
                            ? __('Markdown')
                            : panel === 'preview'
                              ? __('Preview')
                              : __('Side by side');

                    return (
                        <Button
                            key={panel}
                            type="button"
                            variant={
                                visiblePanel === panel ? 'secondary' : 'ghost'
                            }
                            size="sm"
                            aria-pressed={visiblePanel === panel}
                            aria-controls={
                                panel === 'both'
                                    ? `${id}-write ${id}-preview`
                                    : `${id}-${panel}`
                            }
                            onClick={() => selectPanel(panel)}
                        >
                            <Icon aria-hidden="true" />
                            {label}
                        </Button>
                    );
                })}
                {!maximized && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="ml-auto"
                        onClick={() => void maximize()}
                    >
                        <Maximize2 aria-hidden="true" />
                        {__('Full screen')}
                    </Button>
                )}
                {maximized && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="ml-auto"
                        onClick={() => void document.exitFullscreen()}
                    >
                        <Minimize2 aria-hidden="true" />
                        {__('Exit full screen')}
                    </Button>
                )}
            </div>
            <div
                ref={splitRef}
                style={
                    {
                        '--editor-left': `${split}fr`,
                        '--editor-right': `${100 - split}fr`,
                    } as CSSProperties
                }
                className={cn(
                    'grid min-h-0 min-w-0',
                    visiblePanel === 'both' &&
                        (maximized
                            ? 'grid-cols-[minmax(0,var(--editor-left))_0.375rem_minmax(0,var(--editor-right))]'
                            : 'lg:grid-cols-[minmax(0,var(--editor-left))_0.375rem_minmax(0,var(--editor-right))]'),
                    maximized && 'flex-1',
                    resizing && 'cursor-col-resize select-none',
                )}
            >
                <div
                    id={`${id}-write`}
                    className={cn(
                        'min-h-0 min-w-0 flex-col',
                        visiblePanel === 'preview'
                            ? 'hidden'
                            : visiblePanel === 'write'
                              ? 'flex'
                              : 'flex',
                    )}
                >
                    <div className="flex shrink-0 items-center gap-2 border-b bg-muted/30 px-5 py-2 text-xs font-medium text-muted-foreground">
                        <Code2 aria-hidden="true" className="size-3.5" />
                        {__('Markdown')}
                        <span className="ml-auto hidden items-center gap-1.5 font-normal xl:flex">
                            <ImagePlus
                                aria-hidden="true"
                                className="size-3.5"
                            />
                            {uploading > 0
                                ? __('Uploading image…')
                                : __('Drop or paste an image to upload')}
                        </span>

                        <div className="ml-auto flex items-center gap-1">
                            {visiblePanel === 'both' && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={__('Hide Markdown')}
                                    title={__('Hide Markdown')}
                                    onClick={() => {
                                        if (maximized) {
                                            setMaximized('preview');
                                        } else {
                                            setLayout('preview');
                                        }
                                    }}
                                >
                                    <X aria-hidden="true" />
                                </Button>
                            )}
                        </div>
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
                            maximized && 'h-auto min-h-0 flex-1',
                            dragging &&
                                'bg-muted/50 ring-2 ring-ring ring-inset',
                        )}
                    />
                </div>
                <div
                    role="separator"
                    aria-label={__('Resize Markdown and preview')}
                    aria-orientation="vertical"
                    aria-valuemin={20}
                    aria-valuemax={80}
                    aria-valuenow={Math.round(split)}
                    aria-controls={`${id}-write ${id}-preview`}
                    tabIndex={0}
                    title={__('Drag to resize. Double-click to reset.')}
                    className={cn(
                        'cursor-col-resize touch-none bg-border transition-colors hover:bg-primary/50 focus-visible:bg-primary/50 focus-visible:outline-none',
                        visiblePanel !== 'both'
                            ? 'hidden'
                            : maximized
                              ? 'block'
                              : 'hidden lg:block',
                        resizing && 'bg-primary/50',
                    )}
                    onPointerDown={(event) => {
                        if (event.button !== 0) {
                            return;
                        }
                        event.preventDefault();
                        event.currentTarget.focus();
                        event.currentTarget.setPointerCapture(event.pointerId);
                        setResizing(true);
                    }}
                    onPointerMove={(event) => {
                        if (
                            event.currentTarget.hasPointerCapture(
                                event.pointerId,
                            )
                        ) {
                            resizeAt(event.clientX);
                        }
                    }}
                    onPointerUp={(event) => {
                        if (
                            event.currentTarget.hasPointerCapture(
                                event.pointerId,
                            )
                        ) {
                            event.currentTarget.releasePointerCapture(
                                event.pointerId,
                            );
                        }
                        setResizing(false);
                    }}
                    onLostPointerCapture={() => setResizing(false)}
                    onDoubleClick={() => resizeSplit(50)}
                    onKeyDown={(event) => {
                        const value = {
                            ArrowLeft: split - 2,
                            ArrowRight: split + 2,
                            Home: 20,
                            End: 80,
                        }[event.key];

                        if (value !== undefined) {
                            event.preventDefault();
                            resizeSplit(value);
                        }
                    }}
                />
                <section
                    id={`${id}-preview`}
                    aria-label={__('Preview')}
                    className={cn(
                        'min-h-0 min-w-0 flex-col',
                        visiblePanel === 'write'
                            ? 'hidden'
                            : visiblePanel === 'preview'
                              ? 'flex'
                              : 'flex',
                    )}
                >
                    <div className="flex shrink-0 items-center gap-2 border-b bg-muted/30 px-5 py-2 text-xs font-medium text-muted-foreground">
                        <Eye aria-hidden="true" className="size-3.5" />
                        {__('Preview')}

                        <div className="ml-auto flex items-center gap-1">
                            {visiblePanel === 'both' && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={__('Hide Preview')}
                                    title={__('Hide Preview')}
                                    onClick={() => {
                                        if (maximized) {
                                            setMaximized('write');
                                        } else {
                                            setLayout('write');
                                        }
                                    }}
                                >
                                    <X aria-hidden="true" />
                                </Button>
                            )}
                        </div>
                    </div>
                    <div
                        ref={previewRef}
                        tabIndex={0}
                        className={cn(
                            'h-[60vh] min-h-80 overflow-auto p-5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset sm:p-6',
                            maximized && 'h-auto min-h-0 flex-1',
                        )}
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
