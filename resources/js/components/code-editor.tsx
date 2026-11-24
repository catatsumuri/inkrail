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
import { EditorState } from '@codemirror/state';
import { EditorView, keymap } from '@codemirror/view';
import { tags } from '@lezer/highlight';
import { useEffect, useRef, type RefObject } from 'react';

type Props = {
    id: string;
    defaultValue: string;
    viewRef: RefObject<EditorView | null>;
    onChange: (value: string) => void;
    onScroll: () => void;
    onImageFile: (file: File) => void;
    onDraggingChange: (dragging: boolean) => void;
    invalid?: boolean;
    describedBy?: string;
    className?: string;
};

const fencePattern = /^\s*(`{3,}|~{3,})\s*(\S*)/;

/**
 * Finds the range between a fenced block's opening line and its closing
 * fence so the body can be folded while both fence lines stay visible.
 */
function fencedBodyRange(
    state: EditorState,
    lineNumber: number,
): { from: number; to: number; info: string } | null {
    const open = state.doc.line(lineNumber);
    const match = fencePattern.exec(open.text);

    if (!match) {
        return null;
    }

    for (let next = lineNumber + 1; next <= state.doc.lines; next++) {
        const line = state.doc.line(next);
        const close = fencePattern.exec(line.text);

        if (
            close &&
            close[1][0] === match[1][0] &&
            close[1].length >= match[1].length &&
            line.text.trim() === close[1]
        ) {
            return next > lineNumber + 1
                ? {
                      from: open.to,
                      to: state.doc.line(next - 1).to,
                      info: match[2],
                  }
                : null;
        }
    }

    return null;
}

const fenceFolding = foldService.of((state, lineStart) => {
    const line = state.doc.lineAt(lineStart);
    const range = fencedBodyRange(state, line.number);

    return range ? { from: range.from, to: range.to } : null;
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
    viewRef,
    onChange,
    onScroll,
    onImageFile,
    onDraggingChange,
    invalid = false,
    describedBy,
    className,
}: Props) {
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
                    ]),
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

        viewRef.current = view;

        const folds = [];

        for (let line = 1; line <= view.state.doc.lines; line++) {
            const range = fencedBodyRange(view.state, line);

            if (range?.info === 'html-embed') {
                folds.push(foldEffect.of({ from: range.from, to: range.to }));
            }
        }

        if (folds.length > 0) {
            view.dispatch({ effects: folds });
        }

        return () => {
            view.destroy();
            viewRef.current = null;
        };
        // The editor owns its document after mount; props are read via refs.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return <div ref={hostRef} className={className} />;
}
