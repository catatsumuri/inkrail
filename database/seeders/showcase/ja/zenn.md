```html-embed
<div class="studio">
  <div class="eyebrow">INKRAIL / CREATIVE STUDIO</div>
  <h2>WORDS. IDEAS.<br><span>BEAUTIFULLY CONNECTED.</span></h2>
  <p>書き留めたアイデアを、読んで伝わるストーリーへ。コードから生まれる、小さなビジュアルスタジオ。</p>
  <div class="chips"><span>HTML + CSS</span><span>Inline SVG</span><span>Light & Dark</span></div>
  <div class="metrics"><div><b>01</b><small>ひらめきを記録</small></div><div><b>02</b><small>考えをつなぐ</small></div><div><b>03</b><small>世界へ届ける</small></div></div>
  <div class="orb orb-a"></div><div class="orb orb-b"></div>
</div>
<style>
.studio{position:relative;isolation:isolate;overflow:hidden;box-sizing:border-box;padding:32px;border-radius:20px;background:linear-gradient(135deg,#101b35,#243b67 65%,#315e78);color:#f4f8ff;font-family:system-ui,sans-serif}
.eyebrow{font-size:12px;letter-spacing:.2em;color:#9ce6dd;font-weight:700}.studio h2{position:relative;z-index:1;font-size:clamp(24px,5vw,38px);line-height:1.12;letter-spacing:-.04em;margin:20px 0}.studio h2 span{color:#9ce6dd}.studio p{max-width:430px;color:#d5e1f1;font-size:14px;line-height:1.8}.chips{display:flex;flex-wrap:wrap;gap:8px;margin:20px 0}.chips span{font-size:12px;padding:6px 10px;border:1px solid #ffffff35;border-radius:30px;background:#ffffff0c}.metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;border-top:1px solid #ffffff30;padding-top:20px;position:relative;z-index:1}.metrics b{display:block;font-size:28px;font-weight:500;color:#fff}.metrics small{display:block;margin-top:6px;font-size:12px;color:#b8d5e7}.orb{position:absolute;z-index:-1;border-radius:50%;pointer-events:none}.orb-a{width:220px;height:220px;right:-80px;top:-80px;background:radial-gradient(circle,#65d6c555,transparent 70%)}.orb-b{width:280px;height:280px;right:-80px;bottom:-140px;border:1px solid #94bfff40;box-shadow:0 0 0 30px #94bfff0b,0 0 0 60px #94bfff08}
[data-theme="dark"] .studio{background:linear-gradient(135deg,#0c1224,#182946 65%,#214452)}
@media(max-width:400px){.studio{padding:22px}.metrics{gap:6px}.metrics b{font-size:24px}}
</style>
```

## HTMLとCSSのビジュアルスタジオ

`html-embed` のコードブロックでは、HTMLとCSSをそのまま図として表示できます。上のパネルも下の構成図も、画像を使わずに描いています。ダークモードやスマートフォンの幅でも試してみてください。

```html-embed
<div class="map">
  <div class="kicker">FROM DRAFT TO DELIGHT</div><h3>アイデアが記事になるまで</h3><p class="lead">3つのステップを、HTMLとCSSだけで可視化。チェックを入れると、その裏側も見られます。</p>
  <input id="architecture" type="checkbox"><label for="architecture">仕組みをもう少し詳しく</label>
  <div class="pipeline"><div class="node"><span class="icon">✎</span><small>01 / WRITE</small><b>Markdown</b><p>文章も図解もテキストで編集。</p></div><div class="arrow">→</div><div class="node featured"><span class="icon">◇</span><small>02 / TRANSFORM</small><b>inkstream</b><p>構文を読み取り、表現へ変換。</p></div><div class="arrow">→</div><div class="node"><span class="icon">↗</span><small>03 / PUBLISH</small><b>InkRail</b><p>プレビューして、読者へ届ける。</p></div></div>
  <div class="detail"><b>HTML + CSS → sandboxed iframe</b><p>本文から独立した領域で描画。CSSは記事全体に影響せず、JavaScriptも実行されません。</p></div>
  <div class="status"><span></span>外部ライブラリ不要 · CSSアニメーション</div>
</div>
<style>
.map{--bg:#f4f7fc;--card:#fff;--ink:#20304d;--muted:#5e708b;--line:#dce5f1;padding:24px;border-radius:18px;background:var(--bg);color:var(--ink);font-family:system-ui,sans-serif}.kicker{font-size:12px;letter-spacing:.15em;font-weight:700;color:#5363c9}.map h3{font-size:24px;margin:10px 0}.lead{font-size:14px;color:var(--muted);line-height:1.7}.map input{accent-color:#6555d9}.map label{font-size:12px;cursor:pointer;display:inline-block;padding:8px 12px;border:1px solid var(--line);border-radius:20px;background:var(--card)}.pipeline{display:flex;align-items:center;gap:10px;margin:24px 0}.node{flex:1;min-width:0;box-sizing:border-box;padding:18px 14px;background:var(--card);border:1px solid var(--line);border-radius:14px;box-shadow:0 5px 14px #20304d08}.icon{display:grid;place-items:center;width:32px;height:32px;border-radius:10px;background:#edf0ff;color:#6555d9;font-size:22px;margin-bottom:14px}.node small{display:block;font-size:10px;color:var(--muted);letter-spacing:.08em}.node b{display:block;font-size:18px;margin-top:8px}.node p{font-size:12px;line-height:1.6;color:var(--muted);margin-bottom:0}.featured{border-color:#8c80e6;box-shadow:0 5px 20px #6555d91a}.arrow{color:#8c80e6;font-size:22px}.detail{display:none;padding:16px;border:1px dashed #8c80e6;border-radius:12px;font-size:12px;line-height:1.7}.detail p{margin-bottom:0;color:var(--muted)}#architecture:checked~.detail{display:block}.status{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted);margin-top:18px}.status span{width:8px;height:8px;border-radius:50%;background:#259d83;animation:breathe 2s infinite}
[data-theme="dark"] .map{--bg:#121b2c;--card:#1c2940;--ink:#edf2ff;--muted:#afbed6;--line:#34425b}[data-theme="dark"] .icon{background:#34315a;color:#c6bfff}[data-theme="dark"] .kicker{color:#b4acff}
@keyframes breathe{50%{box-shadow:0 0 0 5px #259d8320}}
@media(max-width:540px){.pipeline{flex-direction:column;align-items:stretch}.arrow{align-self:center;transform:rotate(90deg)}.node{padding:14px}.icon{float:right;margin:0}.map{padding:18px}.node p{margin-top:6px}.map h3{font-size:21px}}
@media(prefers-reduced-motion:reduce){.status span{animation:none}}
</style>
```

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
