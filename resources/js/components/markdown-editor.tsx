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
import CodeEditor from '@/components/code-editor';
import type { EditorHandle } from '@/components/editor-handle';
import PillToggle from '@/components/pill-toggle';
import PlainEditor from '@/components/plain-editor';
import { Button } from '@/components/ui/button';
import { useEditorMode } from '@/hooks/use-editor-mode';
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
    allowImages?: boolean;
};

export default function MarkdownEditor({
    id,
    name,
    defaultValue,
    invalid = false,
    describedBy,
    syntax = 'all',
    jumpTo,
    allowImages = true,
}: Props) {
    const { __ } = lang();
    const [editorMode, setEditorMode] = useEditorMode();
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

    const handleRef = useRef<EditorHandle | null>(null);
    const previewRef = useRef<HTMLDivElement>(null);

    const anchorCache = useRef<{
        key: string;
        anchors: ScrollAnchor[];
    } | null>(null);

    useEffect(() => {
        anchorCache.current = null;
    }, [preview]);

    async function uploadImage(file: File) {
        if (!allowImages) return;
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
            const alt =
                file.name
                    .replace(/\.[^.]+$/, '')
                    .replace(/[[\]]/g, '')
                    .trim() || 'image';

            handleRef.current?.insertAtCursor(`![${alt}](${url})\n`);
        } catch {
            if (!reported) {
                toast.error(__('Could not upload the image.'));
            }
        } finally {
            setUploading((count) => count - 1);
        }
    }

    function measureAnchors(
        handle: EditorHandle,
        pane: HTMLDivElement,
    ): ScrollAnchor[] {
        const scroller = handle.scroller();
        const maxFrom = scroller.scrollHeight - scroller.clientHeight;
        const maxTo = pane.scrollHeight - pane.clientHeight;
        const value = handle.getValue();
        const key = `${editorMode}:${value.length}:${handle.layoutKey()}:${pane.scrollHeight}`;

        if (anchorCache.current?.key === key) {
            return anchorCache.current.anchors;
        }

        const source = listHeadings(value).filter(
            (heading) => heading.level <= 4,
        );
        const rendered = pane.querySelectorAll('.ink-heading');
        const embeds = listFencedBlocks(value, 'html-embed');
        const renderedEmbeds = pane.querySelectorAll('.ink-html-embed');
        const useHeadings =
            source.length > 0 && source.length === rendered.length;
        const useEmbeds =
            embeds.length > 0 && embeds.length === renderedEmbeds.length;
        let headings: ScrollAnchor[] = [];

        if (useHeadings || useEmbeds) {
            const paneTop = pane.getBoundingClientRect().top;
            const offsets = [
                ...(useHeadings ? source.map((heading) => heading.offset) : []),
                ...(useEmbeds
                    ? embeds.flatMap((embed) => [embed.start, embed.end])
                    : []),
            ];
            const tops = handle.tops(offsets);
            let next = 0;
            const renderedTop = (element: Element, edge: 'top' | 'bottom') =>
                pane.scrollTop +
                element.getBoundingClientRect()[edge] -
                paneTop;

            if (useHeadings) {
                headings = source.map((_, index) => ({
                    from: tops[next++],
                    to: renderedTop(rendered[index], 'top'),
                }));
            }

            if (useEmbeds) {
                for (const index of embeds.keys()) {
                    headings.push(
                        {
                            from: tops[next++],
                            to: renderedTop(renderedEmbeds[index], 'top'),
                        },
                        {
                            from: tops[next++],
                            to: renderedTop(renderedEmbeds[index], 'bottom'),
                        },
                    );
                }

                headings.sort((left, right) => left.from - right.from);
            }
        }

        const anchors = buildAnchors(headings, maxFrom, maxTo);
        anchorCache.current = { key, anchors };

        return anchors;
    }

    function syncPreview() {
        const handle = handleRef.current;
        const pane = previewRef.current;

        if (
            !handle ||
            !pane ||
            visiblePanel !== 'both' ||
            (!maximized && !window.matchMedia('(min-width: 1024px)').matches) ||
            handle.scroller().scrollHeight <= handle.scroller().clientHeight
        ) {
            return;
        }

        const scroller = handle.scroller();
        const anchors = measureAnchors(handle, pane);

        pane.scrollTop =
            anchors.length > 1
                ? mapScroll(anchors, scroller.scrollTop)
                : (scroller.scrollTop /
                      (scroller.scrollHeight - scroller.clientHeight)) *
                  (pane.scrollHeight - pane.clientHeight);
    }

    useEffect(() => {
        const handle = handleRef.current;

        if (!handle || jumpTo === undefined || !Number.isFinite(jumpTo)) {
            return;
        }

        handle.jumpTo(jumpTo);

        const value = handle.getValue();
        const start = Math.max(0, Math.min(jumpTo, value.length));
        const lineEnd = value.indexOf('\n', start);
        const line = value.slice(
            value.lastIndexOf('\n', start - 1) + 1,
            lineEnd === -1 ? value.length : lineEnd,
        );
        const heading = /^#{1,6}\s+(.+?)(?:\s+#+\s*)?$/.exec(line);
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

    const editorClassName = cn(
        'h-[60vh] min-h-80 w-full',
        maximized && 'h-auto min-h-0 flex-1',
        dragging && 'bg-muted/50 ring-2 ring-ring ring-inset',
    );

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
                <PillToggle
                    className="ml-auto"
                    label={__('Editor type')}
                    value={editorMode}
                    options={[
                        {
                            value: 'codemirror',
                            label: __('Code editor'),
                            title: __(
                                'Syntax highlighting and folding for long embedded HTML',
                            ),
                        },
                        {
                            value: 'plain',
                            label: __('Standard editor'),
                            title: __(
                                'A plain text box, like a normal form field',
                            ),
                        },
                    ]}
                    onValueChange={(mode) => {
                        anchorCache.current = null;
                        setEditorMode(mode);
                    }}
                />
                {!maximized && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
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
                        {allowImages && (
                            <span className="ml-auto hidden items-center gap-1.5 font-normal xl:flex">
                                <ImagePlus
                                    aria-hidden="true"
                                    className="size-3.5"
                                />
                                {uploading > 0
                                    ? __('Uploading image…')
                                    : __('Drop or paste an image to upload')}
                            </span>
                        )}

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
                    {editorMode === 'plain' ? (
                        <PlainEditor
                            key="plain"
                            id={id}
                            name={name}
                            defaultValue={content}
                            handleRef={handleRef}
                            onChange={setContent}
                            onScroll={syncPreview}
                            onImageFile={(file) => void uploadImage(file)}
                            onDraggingChange={setDragging}
                            invalid={invalid}
                            describedBy={describedBy}
                            className={editorClassName}
                        />
                    ) : (
                        <>
                            <input type="hidden" name={name} value={content} />
                            <CodeEditor
                                key="codemirror"
                                id={id}
                                defaultValue={content}
                                handleRef={handleRef}
                                onChange={setContent}
                                onScroll={syncPreview}
                                onImageFile={(file) => void uploadImage(file)}
                                onDraggingChange={setDragging}
                                invalid={invalid}
                                describedBy={describedBy}
                                className={editorClassName}
                            />
                        </>
                    )}
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
