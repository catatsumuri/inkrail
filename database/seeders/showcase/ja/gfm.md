GitHub Flavored Markdown（GFM）は、基本Markdownに表・タスクリスト・打ち消し線・自動リンクなどを加えた拡張です。このページでは、GitHub風アラートもあわせて紹介します。

## 表

コロンの位置で、左寄せ・中央寄せ・右寄せを指定できます。

```markdown
| 記法 | 用途 | サンプル数 |
| :--- | :---: | ---: |
| 基本Markdown | 文章 | 8 |
| Zenn | 補足 | 6 |
| Mintlify | 構成 | 10 |
```

| 記法 | 用途 | サンプル数 |
| :--- | :---: | ---: |
| 基本Markdown | 文章 | 8 |
| Zenn | 補足 | 6 |
| Mintlify | 構成 | 10 |

セルの中でも **強調**、`code`、[リンク](./showcase-markdown)が使えます。

## タスクリスト

```markdown
- [x] 記事を書く
- [x] 描画結果を確認する
- [ ] レビューを依頼する
- [ ] 公開する
```

- [x] 記事を書く
- [x] 描画結果を確認する
- [ ] レビューを依頼する
- [ ] 公開する

ここでのチェック状態はMarkdownの内容を表すもので、記事を編集・保存する操作ではありません。

## 打ち消し線

```markdown
~~旧手順~~ → **現在の手順**
```

~~旧手順~~ → **現在の手順**

`~~` を2つ重ねる書き方を使います。`~ひとつだけ~` は打ち消し線として扱いません。

## 自動リンク

段落の途中にあるURLはリンクとして表示されます。

```markdown
ソースは https://github.com/catatsumuri/inkstream で読めます。
```

ソースは https://github.com/catatsumuri/inkstream で読めます。

URLを単独行にすると、inkstreamのリンクカードとして表示されることがあります。単純なリンクにしたい場合は、ラベル付きの `[テキスト](URL)` も使えます。

## GitHub風アラート

アラートは通常のGFM構文とは別のGitHub風拡張で、inkstreamが同じメッセージ表示へ変換します。

```markdown
> [!NOTE]
> 読者の理解を助ける補足情報。

> [!TIP]
> 試すと便利な小さなコツ。
```

> [!NOTE]
> 読者の理解を助ける補足情報。ZennのメッセージやMintlify風のNoteと同じ仕組みで表示されます。

> [!TIP]
> 長い説明は見出しで分け、短い例を添えると読みやすくなります。

> [!IMPORTANT]
> 記事のslugを変更すると、以前の公開URLは使えなくなります。

> [!WARNING]
> 記事を削除すると、公開ページも閲覧できなくなります。

> [!CAUTION]
> 操作前に対象を確認してください。WARNINGとCAUTIONは同じ警告系の見た目になります。

## リストと表を組み合わせる

1. 原稿を用意します。
2. 状態を確認します。

   | 状態 | 外部からの閲覧 |
   | --- | --- |
   | 下書き | 不可 |
   | 公開 | 可能 |

3. URLを共有します。

## 次に読む

[基本Markdown](./showcase-markdown) · [Zennフレーバー](./showcase-zenn) · [Mintlifyフレーバー](./showcase-mintlify) · [inkstream拡張](./showcase-extensions)
