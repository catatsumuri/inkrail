export function validateBackupFiles(
    files: { name: string; size: number }[],
): string | null {
    if (files.length === 0) return null;
    if (files.length !== 1) return 'Choose one backup ZIP at a time.';
    if (!files[0].name.toLowerCase().endsWith('.zip'))
        return 'Choose a ZIP file.';
    if (files[0].size > 100 * 1024 * 1024)
        return 'The backup must be 100 MiB or smaller.';
    return null;
}
