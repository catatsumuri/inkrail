import { useCallback, useSyncExternalStore } from 'react';

export type EditorMode = 'codemirror' | 'plain';

const storageKey = 'inkrail.editor-mode';
const changeEvent = 'inkrail:editor-mode';

function readMode(): EditorMode {
    try {
        return localStorage.getItem(storageKey) === 'plain'
            ? 'plain'
            : 'codemirror';
    } catch {
        return 'codemirror';
    }
}

function subscribe(callback: () => void): () => void {
    window.addEventListener('storage', callback);
    window.addEventListener(changeEvent, callback);

    return () => {
        window.removeEventListener('storage', callback);
        window.removeEventListener(changeEvent, callback);
    };
}

/**
 * Remembers which Markdown editor the writer prefers, in this browser only.
 */
export function useEditorMode(): [EditorMode, (mode: EditorMode) => void] {
    const mode = useSyncExternalStore<EditorMode>(
        subscribe,
        readMode,
        () => 'codemirror',
    );
    const setMode = useCallback((next: EditorMode) => {
        try {
            localStorage.setItem(storageKey, next);
        } catch {
            // Storage can be blocked; the choice then lasts until reload.
        }
        window.dispatchEvent(new Event(changeEvent));
    }, []);

    return [mode, setMode];
}
