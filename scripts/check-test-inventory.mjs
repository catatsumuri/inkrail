import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
// Use the Git index so staged new tests are included, while local scratch tests are not.
const paths = execFileSync('git', ['ls-files', '-z', '--', 'tests'], {
    cwd: root,
    encoding: 'utf8',
})
    .split('\0')
    .filter((path) => path.endsWith('.php'));
const source = new Map();
for (const path of paths) {
    const methods = [
        ...readFileSync(
            new URL(`../${path}`, import.meta.url),
            'utf8',
        ).matchAll(/public\s+function\s+(test_\w+)\s*\(/g),
    ].map((match) => match[1]);
    if (methods.length) source.set(path.slice('tests/'.length), methods);
}
const total = [...source.values()].reduce(
    (count, methods) => count + methods.length,
    0,
);
const unit = [...source]
    .filter(([path]) => path.startsWith('Unit/'))
    .reduce((count, [, methods]) => count + methods.length, 0);
const errors = [];
for (const locale of ['', 'ja/']) {
    const path = `docs/src/content/docs/${locale}guides/tests.md`;
    const content = readFileSync(
        new URL(`../${path}`, import.meta.url),
        'utf8',
    );
    const sections = content.split(/^## /m).slice(1);
    const documented = new Map();
    for (const section of sections) {
        const match = section.match(
            /\[([^\]]+\.php)\]\(https:\/\/github\.com\/catatsumuri\/laravel-react-secure-starter-kit\/blob\/main\/tests\/([^\s)]+)\) — (\d+)(?:項目| methods)/,
        );
        if (!match || match[1] !== match[2]) {
            errors.push(
                `${path}: section has no valid test file reference: ${section.split('\n')[0]}`,
            );
            continue;
        }
        const file = match[1];
        if (documented.has(file))
            errors.push(`${path}: duplicate file ${file}`);
        const rows = [...section.matchAll(/^\| `(test_\w+)` \|(.*)$/gm)];
        const keys = rows.map((row) => row[1]);
        documented.set(file, keys);
        if (Number(match[3]) !== keys.length)
            errors.push(`${path}: incorrect method count for ${file}`);
        if (new Set(keys).size !== keys.length)
            errors.push(`${path}: duplicate keys in ${file}`);
        if (
            locale === 'ja/' &&
            rows.some((row) => !/^\s*\S.*\|\s*$/.test(row[2]))
        ) {
            errors.push(`${path}: missing Japanese description in ${file}`);
        }
    }
    for (const [file, keys] of source) {
        const listed = documented.get(file) ?? [];
        for (const key of keys)
            if (!listed.includes(key))
                errors.push(`${path}: missing ${file}::${key}`);
    }
    for (const [file, keys] of documented) {
        for (const key of keys)
            if (!source.get(file)?.includes(key))
                errors.push(`${path}: stale ${file}::${key}`);
    }
    const summary =
        locale === 'ja/'
            ? `**${source.size}ファイル・${total}項目**（Feature ${total - unit}項目、Unit ${unit}項目）`
            : `**${total} test methods in ${source.size} committed files** (${total - unit} Feature methods and ${unit} Unit methods)`;
    if (!content.includes(summary))
        errors.push(`${path}: update the summary to ${summary}`);
}
if (errors.length) {
    console.error(errors.join('\n'));
    process.exitCode = 1;
} else {
    console.log(
        `Test inventories match ${total} tracked methods across ${source.size} files in both languages.`,
    );
}
