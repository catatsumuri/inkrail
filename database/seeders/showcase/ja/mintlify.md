Mintlify風のタグを使うと、文章の中にカード・タブ・手順・APIの説明などを配置できます。inkstream独自の対応構文であり、MDXやMintlify全体との完全互換ではありません。

属性は `title="説明"` のようにダブルクォートで書き、開始タグを複数行に分けない形を使います。

## コールアウト

```markdown
<Note>覚えておきたい補足です。</Note>
<Tip>便利な使い方です。</Tip>
<Info>関連する情報です。</Info>
<Warning>操作前に確認してください。</Warning>
<Check>確認が完了しました。</Check>
```

<Note>覚えておきたい補足です。本文には **強調** や `code` も使えます。</Note>

<Tip>短い説明には単独行のタグ、長い説明には開始・終了を別の行にしたタグが便利です。</Tip>

<Info>基本Markdown・Zenn・GitHub風アラートを同じ記事の中で使うこともできます。</Info>

<Warning>タグ内でJavaScriptの式やイベントハンドラーを実行する仕組みではありません。</Warning>

<Check>このページの例は公開記事としてそのまま試せます。</Check>

## カードとカードグループ

```markdown
<CardGroup cols="2">
<Card title="基本Markdown" icon="book-open" href="./showcase-markdown">
文章・見出し・リストの基本を確認します。
</Card>

<Card title="GitHubフレーバー" icon="table" href="./showcase-gfm">
表・タスク・アラートを確認します。
</Card>
</CardGroup>
```

<CardGroup cols="2">
<Card title="基本Markdown" icon="book-open" href="./showcase-markdown">
文章・見出し・リストの基本を確認します。
</Card>

<Card title="GitHubフレーバー" icon="table" href="./showcase-gfm">
表・タスク・アラートを確認します。
</Card>
</CardGroup>

## 手順

```markdown
<Steps>
<Step title="書く">
タイトルと本文を入力します。
</Step>

<Step title="確認する">
表示とリンクを確かめます。
</Step>

<Step title="公開する">
公開状態で保存して、URLを共有します。
</Step>
</Steps>
```

<Steps>
<Step title="書く">
タイトルと本文を入力します。まだ見せたくない間は下書きにしておけます。
</Step>

<Step title="確認する">
見出しの階層、リンク先、画像の説明などを確かめます。
</Step>

<Step title="公開する">
公開状態で保存して、URLを共有します。
</Step>
</Steps>

## タブ

タブを切り替えて、同じ作業の別の例を表示できます。

````markdown
<Tabs>
<Tab title="TypeScript">
```typescript
const published: boolean = true;
```
</Tab>

<Tab title="PHP">
```php
$published = true;
```
</Tab>
</Tabs>
````

<Tabs>
<Tab title="TypeScript">
```typescript
const published: boolean = true;
```
</Tab>

<Tab title="PHP">
```php
$published = true;
```
</Tab>
</Tabs>

## アコーディオン

```markdown
<AccordionGroup>
<Accordion title="公開した記事を下書きに戻せますか？" icon="file-text">
はい。編集画面で公開のチェックを外して保存します。
</Accordion>

<Accordion title="記事のURLは変えられますか？" icon="link">
slugを編集できます。変更前のURLは使えなくなります。
</Accordion>
</AccordionGroup>
```

<AccordionGroup>
<Accordion title="公開した記事を下書きに戻せますか？" icon="file-text">
はい。編集画面で公開のチェックを外して保存します。
</Accordion>

<Accordion title="記事のURLは変えられますか？" icon="link">
slugを編集できます。変更前のURLは使えなくなります。
</Accordion>
</AccordionGroup>

## インラインのバッジとツールチップ

```markdown
現在の状態は <Badge color="green">公開</Badge> です。
<Tooltip tip="記事を識別するURLの一部です">slug</Tooltip> は英小文字・数字・ハイフンで指定します。
```

現在の状態は <Badge color="green">公開</Badge> です。

<Tooltip tip="記事を識別するURLの一部です">slug</Tooltip> は英小文字・数字・ハイフンで指定します。

## APIフィールドの説明

以下はAPIドキュメントの見た目のサンプルです。このページが新しいAPIを提供するわけではありません。

```markdown
<ParamField body="title" type="string" required="true">
記事のタイトル。
</ParamField>

<ResponseField name="slug" type="string">
公開URLで使う識別子。
</ResponseField>
```

<ParamField body="title" type="string" required="true">
記事のタイトル。255文字までの文字列を指定します。
</ParamField>

<ParamField body="options" type="object">
公開に関するオプション。

<Expandable title="オプションの詳細">
`is_published` は公開状態を示す真偽値です。
</Expandable>
</ParamField>

<ResponseField name="slug" type="string">
公開URLで使う識別子。例：`showcase-mintlify`。
</ResponseField>

## 更新情報

```markdown
<Update label="ショーケース" description="構文別サンプル">
基本Markdown・Zenn・Mintlify・GFM・独自拡張のページを用意しました。
</Update>
```

<Update label="ショーケース" description="構文別サンプル">
基本Markdown・Zenn・Mintlify・GFM・独自拡張のページを用意しました。
</Update>

## 次に読む

[基本Markdown](./showcase-markdown) · [Zennフレーバー](./showcase-zenn) · [GitHubフレーバー](./showcase-gfm) · [inkstream拡張](./showcase-extensions)
