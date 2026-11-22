export type DiffLine = { type: 'same' | 'add' | 'remove'; text: string };

export function lineDiff(previous: string, next: string): DiffLine[] | null {
    if (previous.length + next.length > 200_000) return null;
    const before = previous.split('\n');
    const after = next.split('\n');
    if (before.length * after.length > 1_000_000) return null;
    const rows = Array.from(
        { length: before.length + 1 },
        () => new Uint32Array(after.length + 1),
    );
    for (let i = 1; i <= before.length; i++) {
        for (let j = 1; j <= after.length; j++) {
            rows[i][j] =
                before[i - 1] === after[j - 1]
                    ? rows[i - 1][j - 1] + 1
                    : Math.max(rows[i - 1][j], rows[i][j - 1]);
        }
    }
    const result: DiffLine[] = [];
    let i = before.length,
        j = after.length;
    while (i || j) {
        if (i && j && before[i - 1] === after[j - 1]) {
            result.push({ type: 'same', text: before[--i] });
            j--;
        } else if (i && (!j || rows[i - 1][j] >= rows[i][j - 1])) {
            result.push({ type: 'remove', text: before[--i] });
        } else {
            result.push({ type: 'add', text: after[--j] });
        }
    }
    return result.reverse();
}
