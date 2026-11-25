import assert from 'node:assert/strict';
import { test } from 'node:test';
import { handleSaveShortcut } from './save-shortcut.ts';

function press(overrides = {}, disabled = false) {
    let prevented = false;
    let submittedWith;
    const button = {
        disabled,
        form: {
            requestSubmit(submitter) {
                submittedWith = submitter;
            },
        },
    };
    handleSaveShortcut(
        {
            key: 's',
            ctrlKey: true,
            preventDefault() {
                prevented = true;
            },
            ...overrides,
        },
        button,
    );

    return { prevented, submitted: submittedWith === button };
}

await test('Ctrl+S and Command+S submit through the save button and prevent browser saving', () => {
    for (const modifiers of [
        { ctrlKey: true },
        { ctrlKey: false, metaKey: true, key: 'S' },
    ]) {
        assert.deepEqual(press(modifiers), {
            prevented: true,
            submitted: true,
        });
    }
});

await test('disabled saving and held keys prevent browser saving without submitting', () => {
    assert.deepEqual(press({}, true), { prevented: true, submitted: false });
    assert.deepEqual(press({ repeat: true }), {
        prevented: true,
        submitted: false,
    });
});

await test('typing, composition, and other shortcuts remain untouched', () => {
    for (const overrides of [
        { ctrlKey: false },
        { key: 'a' },
        { altKey: true },
        { shiftKey: true },
        { isComposing: true },
    ]) {
        assert.deepEqual(press(overrides), {
            prevented: false,
            submitted: false,
        });
    }
});
