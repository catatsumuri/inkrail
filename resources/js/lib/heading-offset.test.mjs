import assert from 'node:assert/strict';
import { test } from 'node:test';
import { findHeadingOffset } from './heading-offset.ts';

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
