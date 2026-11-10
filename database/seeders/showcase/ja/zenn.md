Zennフレーバーでは、基本Markdownにメッセージ・折りたたみ・画像の補助記法などを組み合わせます。ここでは inkstream のnpm版で対応している記法を紹介します。

## メッセージ

補足説明には `:::message` を使います。

````markdown
:::message
下書きは所有者だけが閲覧できます。
:::
````

:::message
下書きは所有者だけが閲覧できます。公開すると、ログインしていない読者にもURLで共有できます。
:::

## 注意を促すメッセージ

````markdown
:::message alert
公開記事には、パスワードや個人情報を載せないでください。
:::
````

:::message alert
公開記事には、パスワードや個人情報を載せないでください。公開を取り消す場合は、編集画面で下書きに戻します。
:::

## 折りたたみ

````markdown
:::details 公開前のチェックリスト
- タイトルは内容を表していますか？
- リンク先は正しいですか？
- 画像の説明を付けましたか？
:::
````

:::details 公開前のチェックリスト
- タイトルは内容を表していますか？
- リンク先は正しいですか？
- 画像の説明を付けましたか？

チェックを終えたら、**公開状態**に切り替えて保存します。
:::

## ファイル名付きコード

言語名のあとに `:ファイル名` を付けます。コピー・折り返しも試せます。

````markdown
```php:routes/web.php
Route::get('p', [PublicArticleController::class, 'index']);
```
````

```php:routes/web.php
Route::get('p', [PublicArticleController::class, 'index']);
```

## 差分の表示

````markdown
```diff typescript:article.ts
- const published = false;
+ const published = true;
```
````

```diff typescript:article.ts
- const published = false;
+ const published = true;
```

## 画像のサイズとキャプション

画像URLのあとに `=320x` を付けると幅を指定できます。直後の行を斜体で書くとキャプションになります。この画像はアプリに同梱しているため、外部サイトにアクセスせず表示できます。

```markdown
![執筆から公開まで](/images/inkrail-showcase.svg =320x)
*考えを書き留め、記事として公開する。*
```

![執筆から公開まで](/images/inkrail-showcase.svg =320x)
*考えを書き留め、記事として公開する。*

## リンクカード

`@[card](URL)` でURLをカードとして表示できます。現在のアプリにはOGP取得用のサーバー機能がないため、カードはURLを表示します。

```markdown
@[card](https://github.com/catatsumuri/inkstream)
```

@[card](https://github.com/catatsumuri/inkstream)

## 対応範囲について

Zenn本体と完全に同じではありません。このnpm版では数式記法、`@[youtube]`、`@[tweet]` などを対応済みの例として扱っていません。動画は、対応するYouTubeのURLを単独行に置く方法があります。

記法そのものを説明したいときは、`:::message` のようにインラインコード、またはコードブロックで囲んでください。

## 次に読む

[基本Markdown](./showcase-markdown) · [Mintlifyフレーバー](./showcase-mintlify) · [GitHubフレーバー](./showcase-gfm) · [inkstream拡張](./showcase-extensions)
