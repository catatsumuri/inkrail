const templates: Record<string, string> = {
    en: `## Heading

Write your introduction here. Use **bold**, *italic*, and \`inline code\`.

### List

- First item
- Second item

### Code

\`\`\`php
echo 'Hello, InkRail!';
\`\`\`

> Delete this template and write your own article.
`,
    ja: `## 見出し

ここに導入を書きます。**太字**、*斜体*、\`インラインコード\` が使えます。

### リスト

- 1つ目
- 2つ目

### コード

\`\`\`php
echo 'Hello, InkRail!';
\`\`\`

> このテンプレートは消して、自分の記事を書いてください。
`,
};

export function articleTemplate(language: string): string {
    return templates[language] ?? templates.en;
}
