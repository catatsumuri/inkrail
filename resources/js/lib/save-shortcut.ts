export function handleSaveShortcut(
    event: KeyboardEvent,
    button: HTMLButtonElement | null,
): void {
    if (
        !(event.ctrlKey || event.metaKey) ||
        event.key.toLowerCase() !== 's' ||
        event.altKey ||
        event.shiftKey ||
        event.isComposing
    ) {
        return;
    }

    event.preventDefault();

    if (!event.repeat && button && !button.disabled) {
        button.form?.requestSubmit(button);
    }
}
