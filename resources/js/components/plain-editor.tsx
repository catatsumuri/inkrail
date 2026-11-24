import { useEffect, useRef, type RefObject } from 'react';
import type { EditorHandle } from '@/components/editor-handle';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    name: string;
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

function createMirror(textarea: HTMLTextAreaElement): HTMLDivElement {
    const style = window.getComputedStyle(textarea);
    const mirror = document.createElement('div');

    mirror.style.cssText = `position:fixed;visibility:hidden;left:-200%;top:0;height:auto;white-space:pre-wrap;overflow-wrap:break-word;box-sizing:${style.boxSizing};font:${style.font};line-height:${style.lineHeight};padding:${style.padding};width:${textarea.clientWidth}px`;

    return mirror;
}

export default function PlainEditor({
    id,
    name,
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
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const onChangeRef = useRef(onChange);

    useEffect(() => {
        onChangeRef.current = onChange;
    });

    useEffect(() => {
        const textarea = textareaRef.current;

        if (!textarea) {
            return;
        }

        handleRef.current = {
            getValue: () => textarea.value,
            insertAtCursor: (text) => {
                textarea.setRangeText(
                    text,
                    textarea.selectionStart,
                    textarea.selectionEnd,
                    'end',
                );
                onChangeRef.current(textarea.value);
                textarea.focus();
            },
            scroller: () => textarea,
            tops: (offsets) => {
                const mirror = createMirror(textarea);
                const paddingBottom =
                    Number.parseFloat(
                        window.getComputedStyle(textarea).paddingBottom,
                    ) || 0;

                document.body.appendChild(mirror);
                const tops = offsets.map((offset) => {
                    mirror.textContent = textarea.value.slice(0, offset);

                    return mirror.scrollHeight - paddingBottom;
                });
                document.body.removeChild(mirror);

                return tops;
            },
            layoutKey: () => `${textarea.value.length}:${textarea.clientWidth}`,
            jumpTo: (offset) => {
                const start = Math.max(
                    0,
                    Math.min(offset, textarea.value.length),
                );
                const lineEnd = textarea.value.indexOf('\n', start);
                const end = lineEnd === -1 ? textarea.value.length : lineEnd;
                const lineHeight =
                    Number.parseFloat(
                        window.getComputedStyle(textarea).lineHeight,
                    ) || 24;
                const mirror = createMirror(textarea);

                textarea.focus({ preventScroll: true });
                textarea.setSelectionRange(start, end);
                mirror.textContent = textarea.value.slice(0, start);
                document.body.appendChild(mirror);
                const top = mirror.scrollHeight;
                document.body.removeChild(mirror);
                textarea.scrollTo({ top: Math.max(0, top - lineHeight * 2) });
            },
        };

        return () => {
            handleRef.current = null;
        };
    }, [handleRef]);

    function imageIn(files: FileList | null | undefined): File | undefined {
        return Array.from(files ?? []).find((file) =>
            file.type.startsWith('image/'),
        );
    }

    return (
        <textarea
            id={id}
            ref={textareaRef}
            name={name}
            defaultValue={defaultValue}
            onScroll={onScroll}
            onChange={(event) => onChange(event.target.value)}
            onDrop={(event) => {
                onDraggingChange(false);

                const file = imageIn(event.dataTransfer.files);

                if (file) {
                    event.preventDefault();
                    onImageFile(file);
                }
            }}
            onDragOver={(event) => {
                if (event.dataTransfer.types.includes('Files')) {
                    event.preventDefault();
                    onDraggingChange(true);
                }
            }}
            onDragLeave={() => onDraggingChange(false)}
            onPaste={(event) => {
                const file = imageIn(event.clipboardData.files);

                if (file) {
                    event.preventDefault();
                    onImageFile(file);
                }
            }}
            aria-invalid={invalid}
            aria-describedby={describedBy}
            spellCheck={false}
            className={cn(
                'block w-full resize-none bg-transparent p-5 font-mono text-sm leading-7 outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset sm:p-6',
                className,
            )}
        />
    );
}
