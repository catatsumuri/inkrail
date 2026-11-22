export type ScrollAnchor = { from: number; to: number };

/**
 * Maps a scroll position of the source pane onto the target pane by
 * interpolating linearly between matched anchors (both axes non-decreasing).
 */
export function mapScroll(anchors: ScrollAnchor[], value: number): number {
    if (anchors.length === 0) {
        return 0;
    }

    if (value <= anchors[0].from) {
        return anchors[0].to;
    }

    for (let index = 1; index < anchors.length; index++) {
        const next = anchors[index];

        if (value <= next.from) {
            const previous = anchors[index - 1];
            const span = next.from - previous.from;

            return span <= 0
                ? next.to
                : previous.to +
                      ((value - previous.from) / span) *
                          (next.to - previous.to);
        }
    }

    return anchors[anchors.length - 1].to;
}

/**
 * Builds the anchor list from heading positions plus the pane ends, clamping
 * to each pane's scroll range and dropping points that would go backwards.
 */
export function buildAnchors(
    headings: ScrollAnchor[],
    maxFrom: number,
    maxTo: number,
): ScrollAnchor[] {
    const anchors: ScrollAnchor[] = [{ from: 0, to: 0 }];

    for (const heading of headings) {
        const from = Math.min(Math.max(heading.from, 0), maxFrom);
        const to = Math.min(Math.max(heading.to, 0), maxTo);
        const last = anchors[anchors.length - 1];

        if (from > last.from && to >= last.to) {
            anchors.push({ from, to });
        }
    }

    const last = anchors[anchors.length - 1];

    if (maxFrom > last.from) {
        anchors.push({ from: maxFrom, to: Math.max(maxTo, last.to) });
    }

    return anchors;
}
