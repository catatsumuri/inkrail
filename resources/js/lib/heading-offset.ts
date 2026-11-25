import { normalizeMarkdownHeadingText } from '@catatsumuri/inkstream/advanced';

const headingPattern = /^(#{1,6})\s+(.+?)(?:\s+#+\s*)?$/;

export type SourceHeading = { level: number; text: string; offset: number };

/**
 * Lists ATX headings with their character offsets, skipping fenced code.
 */
export function listHeadings(content: string): SourceHeading[] {
    const headings: SourceHeading[] = [];
    let offset = 0;
    let activeFence: string | null = null;

    for (const line of content.replace(/\r\n/g, '\n').split('\n')) {
        const trimmed = line.trimStart();
        const fence = /^(`{3,}|~{3,})/.exec(trimmed)?.[1];

        if (fence) {
            if (activeFence === null) {
                activeFence = fence;
            } else if (
                fence[0] === activeFence[0] &&
                fence.length >= activeFence.length &&
                trimmed.slice(fence.length).trim() === ''
            ) {
                activeFence = null;
            }
        } else if (activeFence === null) {
            const match = headingPattern.exec(trimmed);

            if (match) {
                headings.push({
                    level: match[1].length,
                    text: match[2],
                    offset,
                });
            }
        }

        offset += line.length + 1;
    }

    return headings;
}

/**
 * Finds the character offset of the Markdown heading line that matches the
 * rendered heading, skipping fenced code blocks. Returns null when absent.
 */
export function findHeadingOffset(
    content: string,
    headingText: string,
    level: number,
): number | null {
    const target = normalizeMarkdownHeadingText(headingText);

    if (!target) {
        return null;
    }

    const found = listHeadings(content).find(
        (heading) =>
            heading.level === level &&
            normalizeMarkdownHeadingText(heading.text) === target,
    );

    return found?.offset ?? null;
}

export type FencedBlock = { start: number; end: number };

/**
 * Lists fenced code blocks whose info string starts with the given language,
 * as character offsets: `start` is the opening fence line, `end` is just past
 * the closing fence line (or the end of content when it is never closed).
 */
export function listFencedBlocks(
    content: string,
    language: string,
): FencedBlock[] {
    const blocks: FencedBlock[] = [];
    const text = content.replace(/\r\n/g, '\n');
    let offset = 0;
    let activeFence: string | null = null;
    let current: FencedBlock | null = null;

    for (const line of text.split('\n')) {
        const trimmed = line.trimStart();
        const fence = /^(`{3,}|~{3,})/.exec(trimmed)?.[1];

        if (fence) {
            if (activeFence === null) {
                activeFence = fence;
                const info = trimmed.slice(fence.length).trim().split(/\s+/)[0];

                current =
                    info === language
                        ? { start: offset, end: text.length }
                        : null;
            } else if (
                fence[0] === activeFence[0] &&
                fence.length >= activeFence.length &&
                trimmed.slice(fence.length).trim() === ''
            ) {
                activeFence = null;

                if (current) {
                    current.end = offset + line.length + 1;
                    blocks.push(current);
                    current = null;
                }
            }
        }

        offset += line.length + 1;
    }

    if (current) {
        blocks.push(current);
    }

    return blocks;
}

/**
 * Lists the character offset where each top-level block starts. Blocks are
 * separated by blank lines, except inside fenced code and `:::` containers.
 */
export function listBlockOffsets(content: string): number[] {
    const offsets: number[] = [];
    let offset = 0;
    let activeFence: string | null = null;
    let depth = 0;
    let open = false;

    for (const line of content.replace(/\r\n/g, '\n').split('\n')) {
        const trimmed = line.trimStart();
        const fence = /^(`{3,}|~{3,})/.exec(trimmed)?.[1];

        if (activeFence !== null) {
            if (
                fence &&
                fence[0] === activeFence[0] &&
                fence.length >= activeFence.length &&
                trimmed.slice(fence.length).trim() === ''
            ) {
                activeFence = null;
            }
        } else if (trimmed === '') {
            open = open && depth > 0;
        } else {
            if (!open) {
                offsets.push(offset);
                open = true;
            }

            if (fence) {
                activeFence = fence;
            } else if (/^:{3,}\S/.test(trimmed)) {
                depth++;
            } else if (/^:{3,}$/.test(trimmed) && depth > 0) {
                depth--;
            }
        }

        offset += line.length + 1;
    }

    return offsets;
}
