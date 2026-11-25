import assert from 'node:assert/strict';
import { test } from 'node:test';
import { listBlockOffsets, listHeadings } from './heading-offset.ts';
import { buildAnchors, mapScroll } from './scroll-sync.ts';

await test('interpolates between anchors and clamps at the ends', () => {
    const anchors = buildAnchors([{ from: 100, to: 300 }], 200, 400);

    assert.equal(mapScroll(anchors, 0), 0);
    assert.equal(mapScroll(anchors, 50), 150);
    assert.equal(mapScroll(anchors, 150), 350);
    assert.equal(mapScroll(anchors, 999), 400);
});

await test('drops anchors that go backwards or exceed the scroll range', () => {
    const anchors = buildAnchors(
        [
            { from: 100, to: 100 },
            { from: 80, to: 200 },
            { from: 900, to: 900 },
        ],
        300,
        300,
    );

    assert.deepEqual(anchors, [
        { from: 0, to: 0 },
        { from: 100, to: 100 },
        { from: 300, to: 300 },
    ]);
});

await test('lists headings outside fenced code in order', () => {
    const list = listHeadings('# A\n```\n## no\n```\n### B\n');

    assert.deepEqual(
        list.map((heading) => [heading.level, heading.text]),
        [
            [1, 'A'],
            [3, 'B'],
        ],
    );
});

await test('lists top-level block offsets, keeping fences and containers whole', () => {
    const text =
        '# A\ntext\n\n```\na\n\nb\n```\n\n:::message\nx\n\ny\n:::\n\nlast\n';

    assert.deepEqual(
        listBlockOffsets(text).map(
            (offset) => text.slice(offset).split('\n')[0],
        ),
        ['# A', '```', ':::message', 'last'],
    );
});
