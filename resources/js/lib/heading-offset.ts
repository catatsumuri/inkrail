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
