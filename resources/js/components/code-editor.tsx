import { defaultKeymap, history, historyKeymap } from '@codemirror/commands';
import { html } from '@codemirror/lang-html';
import { markdown } from '@codemirror/lang-markdown';
import {
    HighlightStyle,
    foldEffect,
    foldGutter,
    foldKeymap,
    foldService,
    syntaxHighlighting,
} from '@codemirror/language';
import { search, searchKeymap } from '@codemirror/search';
import { EditorState, type Text } from '@codemirror/state';
import { EditorView, keymap } from '@codemirror/view';
import { lang } from '@erag/lang-sync-inertia/react';
import { tags } from '@lezer/highlight';
import { useEffect, useRef, type RefObject } from 'react';
import type { EditorHandle } from '@/components/editor-handle';

type Props = {
    id: string;
    defaultValue: string;
    handleRef: RefObject<EditorHandle | null>;
    onChange: (value: string) => void;
    onScroll: () => void;
    onImageFile: (file: File) => void;
    onDraggingChange: (dragging: boolean) => void;
    invalid?: boolean;
    describedBy?: string;
    className?: string;
};

const fencePattern = /^\s*(`{3,}|~{3,})\s*(\S*)/;

type FencedBody = { from: number; to: number; info: string };

const fencedBodies = new WeakMap<Text, Map<number, FencedBody>>();

/**
 * Pairs opening and closing fences in one pass, keyed by the opening line
 * number, so a closing fence is never mistaken for an opening one. The range
 * covers the body only, leaving both fence lines visible when folded.
 */
function fencedBodiesOf(doc: Text): Map<number, FencedBody> {
    const cached = fencedBodies.get(doc);

    if (cached) {
        return cached;
    }

    const bodies = new Map<number, FencedBody>();
    let open: { number: number; marker: string; info: string } | null = null;

    for (let number = 1; number <= doc.lines; number++) {
        const line = doc.line(number);
        const match = fencePattern.exec(line.text);

        if (!match) {
            continue;
        }

        if (open === null) {
            open = { number, marker: match[1], info: match[2] };
        } else if (
            match[1][0] === open.marker[0] &&
            match[1].length >= open.marker.length &&
            line.text.trim() === match[1]
        ) {
            if (number > open.number + 1) {
                bodies.set(open.number, {
                    from: doc.line(open.number).to,
                    to: doc.line(number - 1).to,
                    info: open.info,
                });
            }
            open = null;
        }
    }

    fencedBodies.set(doc, bodies);

    return bodies;
}

const fenceFolding = foldService.of((state, lineStart) => {
    const line = state.doc.lineAt(lineStart);
    const body = fencedBodiesOf(state.doc).get(line.number);

    return body ? { from: body.from, to: body.to } : null;
});

const highlightStyle = HighlightStyle.define([
    {
        tag: [tags.heading, tags.strong],
        color: 'var(--cm-heading)',
        fontWeight: 'bold',
    },
    { tag: tags.emphasis, fontStyle: 'italic' },
    { tag: [tags.link, tags.url], color: 'var(--cm-link)' },
    {
        tag: [tags.keyword, tags.tagName, tags.monospace],
        color: 'var(--cm-keyword)',
    },
    {
        tag: [tags.string, tags.attributeValue],
        color: 'var(--cm-string)',
    },
    { tag: [tags.comment, tags.quote], color: 'var(--cm-comment)' },
    {
        tag: [tags.processingInstruction, tags.meta, tags.attributeName],
        color: 'var(--cm-meta)',
    },
]);

const theme = EditorView.theme({
    '&': { height: '100%', backgroundColor: 'transparent' },
    '&.cm-focused': { outline: 'none' },
    '.cm-scroller': {
        fontFamily: 'var(--font-mono, ui-monospace, monospace)',
        fontSize: '0.875rem',
        lineHeight: '1.75rem',
        overflow: 'auto',
    },
    '.cm-content': { padding: '1.25rem 0', caretColor: 'currentColor' },
    '.cm-line': { padding: '0 1.25rem 0 0.5rem' },
    '.cm-gutters': {
        backgroundColor: 'transparent',
        border: 'none',
        color: 'var(--muted-foreground)',
    },
    '.cm-foldGutter span': { cursor: 'pointer', padding: '0 0.25rem' },
    '.cm-foldPlaceholder': {
        backgroundColor: 'var(--muted)',
        border: '1px solid var(--border)',
        color: 'var(--muted-foreground)',
        borderRadius: '0.25rem',
        padding: '0 0.4rem',
        margin: '0 0.25rem',
    },
    '.cm-activeLine': { backgroundColor: 'transparent' },
    '.cm-selectionBackground, &.cm-focused .cm-selectionBackground': {
        backgroundColor: 'color-mix(in oklab, var(--ring) 35%, transparent)',
    },
});

export default function CodeEditor({
    id,
    defaultValue,
    handleRef,
    onChange,
    onScroll,
    onImageFile,
    onDraggingChange,
    invalid = false,
    describedBy,
    className,
}: Props) {
    const { __ } = lang();
    const hostRef = useRef<HTMLDivElement>(null);
    const handlers = useRef({
        onChange,
        onScroll,
        onImageFile,
        onDraggingChange,
    });

    useEffect(() => {
        handlers.current = {
            onChange,
            onScroll,
            onImageFile,
            onDraggingChange,
        };
    });

    useEffect(() => {
        const host = hostRef.current;

        if (!host) {
            return;
        }

        const imageIn = (files: FileList | null | undefined) =>
            Array.from(files ?? []).find((file) =>
                file.type.startsWith('image/'),
            );

        const view = new EditorView({
            parent: host,
            state: EditorState.create({
                doc: defaultValue,
                extensions: [
                    history(),
                    keymap.of([
                        ...defaultKeymap,
                        ...historyKeymap,
                        ...foldKeymap,
                        ...searchKeymap,
                    ]),
                    search({ top: true }),
                    EditorState.phrases.of({
                        Find: __('Find'),
                        Replace: __('Replace'),
                        next: __('Next match'),
                        previous: __('Previous match'),
                        all: __('Select all matches'),
                        'match case': __('Match case'),
                        regexp: __('Regular expression'),
                        'by word': __('Whole word'),
                        replace: __('Replace'),
                        'replace all': __('Replace all'),
                        close: __('Close'),
                    }),
                    markdown({
                        codeLanguages: (info) =>
                            info === 'html-embed' || info === 'html'
                                ? html().language
                                : null,
                    }),
                    fenceFolding,
                    foldGutter(),
                    syntaxHighlighting(highlightStyle),
                    EditorView.lineWrapping,
                    EditorView.contentAttributes.of({
                        id,
                        spellcheck: 'false',
                        'aria-invalid': String(invalid),
                        ...(describedBy
                            ? { 'aria-describedby': describedBy }
                            : {}),
                    }),
                    theme,
                    EditorView.updateListener.of((update) => {
                        if (update.docChanged) {
                            handlers.current.onChange(
                                update.state.doc.toString(),
                            );
                        }
                    }),
                    EditorView.domEventHandlers({
                        scroll: () => handlers.current.onScroll(),
                        paste: (event) => {
                            const file = imageIn(event.clipboardData?.files);

                            if (file) {
                                event.preventDefault();
                                handlers.current.onImageFile(file);

                                return true;
                            }

                            return false;
                        },
                        drop: (event) => {
                            handlers.current.onDraggingChange(false);
                            const file = imageIn(event.dataTransfer?.files);

                            if (file) {
                                event.preventDefault();
                                handlers.current.onImageFile(file);

                                return true;
                            }

                            return false;
                        },
                        dragover: (event) => {
                            if (event.dataTransfer?.types.includes('Files')) {
                                event.preventDefault();
                                handlers.current.onDraggingChange(true);
                            }

                            return false;
                        },
                        dragleave: () => {
                            handlers.current.onDraggingChange(false);

                            return false;
                        },
                    }),
                ],
            }),
        });

        handleRef.current = {
            getValue: () => view.state.doc.toString(),
            insertAtCursor: (text) => {
                const { from, to } = view.state.selection.main;

                view.dispatch({
                    changes: { from, to, insert: text },
                    selection: { anchor: from + text.length },
                });
                view.focus();
            },
            scroller: () => view.scrollDOM,
            tops: (offsets) =>
                offsets.map(
                    (offset) =>
                        view.lineBlockAt(
                            Math.min(offset, view.state.doc.length),
                        ).top,
                ),
            layoutKey: () =>
                `${view.contentHeight}:${view.scrollDOM.clientWidth}`,
            jumpTo: (offset) => {
                const line = view.state.doc.lineAt(
                    Math.max(0, Math.min(offset, view.state.doc.length)),
                );

                view.dispatch({
                    selection: { anchor: line.from, head: line.to },
                    effects: EditorView.scrollIntoView(line.from, {
                        y: 'start',
                        yMargin: view.defaultLineHeight * 2,
                    }),
                });
                view.focus();
            },
        };

        const folds = [];

        for (const body of fencedBodiesOf(view.state.doc).values()) {
            if (body.info === 'html-embed') {
                folds.push(foldEffect.of({ from: body.from, to: body.to }));
            }
        }

        if (folds.length > 0) {
            view.dispatch({ effects: folds });
        }

        return () => {
            view.destroy();
            handleRef.current = null;
        };
        // The editor owns its document after mount; props are read via refs.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return <div ref={hostRef} className={className} />;
}
