import assert from 'node:assert/strict';
import { test } from 'node:test';
import { findHeadingOffset, listFencedBlocks } from './heading-offset.ts';

await test('finds the offset of a heading at the given level', () => {
    const content = '# Title\n\n## One\ntext\n### Two\n';

    assert.equal(findHeadingOffset(content, 'One', 2), 9);
    assert.equal(findHeadingOffset(content, 'Two', 3), 21);
});

await test('ignores headings inside fenced code and mismatched levels', () => {
    const content = '```md\n## One\n```\n\n## One\n';

    assert.equal(findHeadingOffset(content, 'One', 2), 18);
    assert.equal(findHeadingOffset(content, 'One', 3), null);
    assert.equal(findHeadingOffset(content, 'Missing', 2), null);
});

await test('lists fenced blocks of the given language with start and end offsets', () => {
    const content =
        'a\n```html-embed\n<div></div>\n```\nb\n```bash\nls\n```\n```html-embed\n<p></p>\n```\n';
    const blocks = listFencedBlocks(content, 'html-embed');

    assert.equal(blocks.length, 2);
    assert.equal(
        content.slice(blocks[0].start, blocks[0].end),
        '```html-embed\n<div></div>\n```\n',
    );
    assert.equal(
        content.slice(blocks[1].start, blocks[1].end),
        '```html-embed\n<p></p>\n```\n',
    );
});

await test('keeps nested shorter fences inside a longer fence and handles unclosed blocks', () => {
    const nested = '````html-embed\n```\nx\n```\n````\n';

    assert.deepEqual(listFencedBlocks(nested, 'html-embed'), [
        { start: 0, end: nested.length },
    ]);
    assert.deepEqual(listFencedBlocks('```html-embed\n<div>', 'html-embed'), [
        { start: 0, end: 19 },
    ]);
    assert.deepEqual(listFencedBlocks('```bash\nls\n```\n', 'html-embed'), []);
});
