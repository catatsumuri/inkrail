import assert from 'node:assert/strict';
import { test } from 'node:test';
import { lineDiff } from './line-diff.ts';

await test('shows inserted and deleted lines in document order', () => {
    assert.deepEqual(lineDiff('Heading\nOld\nEnd', 'Heading\nNew\nEnd'), [
        { type: 'same', text: 'Heading' },
        { type: 'add', text: 'New' },
        { type: 'remove', text: 'Old' },
        { type: 'same', text: 'End' },
    ]);
});

await test('preserves blank lines and literal HTML without interpreting either', () => {
    assert.deepEqual(lineDiff('\n<script>x</script>', '\n<script>x</script>'), [
        { type: 'same', text: '' },
        { type: 'same', text: '<script>x</script>' },
    ]);
});

await test('bounds text size and the line comparison matrix', () => {
    assert.equal(lineDiff('x'.repeat(200001), ''), null);
    assert.equal(lineDiff('x\n'.repeat(1100), 'y\n'.repeat(1100)), null);
});
