/**
 * What the Markdown editor needs from whichever text editor is mounted, so
 * the textarea and CodeMirror implementations stay interchangeable.
 */
export type EditorHandle = {
    getValue: () => string;
    insertAtCursor: (text: string) => void;
    scroller: () => HTMLElement;
    /** Vertical scroll position of each character offset in the source. */
    tops: (offsets: number[]) => number[];
    /** Changes whenever the source layout (wrapping, folding) changes. */
    layoutKey: () => string;
    jumpTo: (offset: number) => void;
};
