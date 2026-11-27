import assert from 'node:assert/strict';
import { test } from 'node:test';
import { validateBackupFiles } from './backup-files.ts';

await test('accepts a single ZIP up to the size limit, regardless of extension case', () => {
    assert.equal(
        validateBackupFiles([{ name: 'backup.ZIP', size: 100 * 1024 * 1024 }]),
        null,
    );
    assert.equal(validateBackupFiles([]), null);
});

await test('rejects multiple files, non-ZIP files and oversized files', () => {
    const zip = { name: 'backup.zip', size: 1 };
    assert.equal(
        validateBackupFiles([zip, zip]),
        'Choose one backup ZIP at a time.',
    );
    assert.equal(
        validateBackupFiles([{ name: 'a.txt', size: 1 }]),
        'Choose a ZIP file.',
    );
    assert.equal(
        validateBackupFiles([{ ...zip, size: 100 * 1024 * 1024 + 1 }]),
        'The backup must be 100 MiB or smaller.',
    );
});
