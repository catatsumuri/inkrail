```html-embed
<div class="rp">
  <div class="kicker">RENDERING PATH</div>
  <h3>DB の行から React の要素まで</h3>
  <input id="rp-payload" type="checkbox"><label for="rp-payload">ステップ間で渡るデータを表示</label>
  <div class="lanes">
    <div class="lane">
      <div class="lh"><span class="dot server"></span>LARAVEL</div>
      <div class="node" style="--d:0s"><small>データベース</small><b>articles.content</b><span class="pl">"## What is…"(Markdown 文字列)</span></div>
      <div class="link"><i style="--d:.2s"></i></div>
      <div class="node" style="--d:.3s"><small>コントローラ</small><b>PublicArticleController</b><span class="pl">show() → Article → props</span></div>
    </div>
    <div class="bridge"><i style="--d:.6s"></i></div>
    <div class="lane">
      <div class="lh"><span class="dot inertia"></span>INERTIA</div>
      <div class="node featured" style="--d:.9s"><small>ページ props</small><b>レスポンス内の JSON</b><span class="pl">{ article: { title, content } }</span></div>
    </div>
    <div class="bridge"><i style="--d:1.2s"></i></div>
    <div class="lane">
      <div class="lh"><span class="dot browser"></span>BROWSER · REACT</div>
      <div class="node" style="--d:1.5s"><small>ページ</small><b>public-show.tsx</b><span class="pl">&lt;ArticleContent content=… /&gt;</span></div>
      <div class="link"><i style="--d:1.7s"></i></div>
      <div class="node" style="--d:1.9s"><small>コンポーネント</small><b>ArticleContent</b><span class="pl">&lt;InkstreamMarkdown&gt;</span></div>
      <div class="link"><i style="--d:2.1s"></i></div>
      <div class="node featured" style="--d:2.3s"><small>レンダラー</small><b>InkstreamMarkdown</b><span class="pl">Markdown → AST → React</span></div>
      <div class="link"><i style="--d:2.5s"></i></div>
      <div class="node done" style="--d:2.7s"><small>結果</small><b>React elements</b><span class="pl">いま読んでいるこの記事</span></div>
    </div>
  </div>
  <div class="foot"><span></span>データベースには元の Markdown だけを保存し、描画済みの HTML は保存しません。</div>
</div>
<style>
.rp{--bg:#f4f7fc;--card:#fff;--ink:#20304d;--muted:#5e708b;--line:#dce5f1;--accent:#5b4bdb;--glow:#5b4bdb33;--ok:#259d83;padding:16px;border-radius:18px;background:var(--bg);color:var(--ink);font-family:system-ui,sans-serif;box-sizing:border-box}
.kicker{font-size:11px;letter-spacing:.16em;font-weight:700;color:var(--accent)}
.rp h3{margin:2px 0 8px;font-size:19px;letter-spacing:-.02em}
.rp label{display:inline-block;margin-bottom:8px;padding:4px 12px;border:1px solid var(--line);border-radius:999px;background:var(--card);color:var(--muted);font-size:12px;cursor:pointer}
#rp-payload{position:absolute;opacity:0}
#rp-payload:checked+label{border-color:var(--accent);color:var(--accent)}
.lanes{display:flex;align-items:center;gap:4px}
.lane{flex:1;min-width:0;display:flex;flex-direction:column;align-items:stretch;padding:10px;border:1px dashed var(--line);border-radius:14px}
.lane .lh{display:flex;align-items:center;gap:6px;margin-bottom:8px;font-size:10px;font-weight:700;letter-spacing:.14em;color:var(--muted)}
.dot{width:8px;height:8px;border-radius:50%}.server{background:#e5764a}.inertia{background:#8a5cf6}.browser{background:#2aa3d6}
.node{position:relative;padding:6px 10px;border:1px solid var(--line);border-radius:10px;background:var(--card);animation:rp-glow 4.2s ease-in-out infinite;animation-delay:var(--d)}
.node small{display:block;font-size:9px;letter-spacing:.12em;color:var(--muted)}
.node b{display:block;font-size:12px;overflow-wrap:anywhere}
.node.featured{border-color:var(--accent)}.node.done{border-color:var(--ok)}
.pl{display:none;margin-top:4px;font:10px/1.3 ui-monospace,monospace;color:var(--muted);overflow-wrap:anywhere}
#rp-payload:checked~.lanes .pl{display:block}
.link{position:relative;height:12px;width:2px;margin:0 auto;background:var(--line)}
.link i,.bridge i{position:absolute;left:-2px;top:0;width:6px;height:6px;border-radius:50%;background:var(--accent);box-shadow:0 0 6px var(--accent);animation:rp-down 1.6s linear infinite;animation-delay:var(--d)}
.bridge{position:relative;flex:0 0 22px;height:2px;background:var(--line)}
.bridge i{top:-2px;left:0;animation-name:rp-right}
.foot{margin-top:8px;display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted)}
.foot span{width:8px;height:8px;border-radius:50%;background:var(--ok);animation:rp-pulse 2s ease-in-out infinite}
@keyframes rp-glow{0%,12%,100%{box-shadow:none}4%{box-shadow:0 0 0 4px var(--glow)}}
@keyframes rp-down{0%{transform:translateY(0);opacity:0}20%{opacity:1}100%{transform:translateY(10px);opacity:0}}
@keyframes rp-right{0%{transform:translateX(0);opacity:0}20%{opacity:1}100%{transform:translateX(18px);opacity:0}}
@keyframes rp-pulse{50%{box-shadow:0 0 0 5px #259d8330}}
[data-theme="dark"] .rp{--bg:#121b2c;--card:#1c2940;--ink:#edf2ff;--muted:#afbed6;--line:#34425b;--accent:#b4acff;--glow:#b4acff40;--ok:#4fd1ae}
@media(max-width:560px){.rp{padding:14px}.rp label,.pl{display:none!important}.lanes{flex-direction:column;align-items:stretch;gap:0}.bridge{flex:0 0 10px;width:2px;height:10px;margin:0 auto}.bridge i{left:-2px;top:0;animation-name:rp-down}.lane{padding:0;border:0}.lane .lh{margin:2px 0 4px}.link{height:8px}.node{padding:5px 10px}.node small{display:inline;margin-right:6px}.node b{display:inline}.rp h3{font-size:16px;margin-bottom:10px}.foot{font-size:11px}}
@media(prefers-reduced-motion:reduce){.node,.link i,.bridge i,.foot span{animation:none}}
</style>
```

データベースの行を、React の要素として画面に出すまでの経路です。Inertia がページコンポーネントとそのデータを届け、Inkstream が Markdown を解釈します。データベースが保持するのは元の Markdown 文字列で、描画済みの HTML のコピーではありません。

## 1. 記事を読み込み、公開可否を確認する

公開用のルートは、スラッグで記事を引き当てます。

```php
Route::get('p/{article:slug}', [PublicArticleController::class, 'show'])
    ->name('public.articles.show');
```

コントローラは、ページを返す前に公開状態を確認します。未公開の記事は、スラッグを知っていても 404 になります。

`PublicArticleController::show()` の中心部分は次のとおりです。

```php
abort_unless($article->is_published, 404);

return Inertia::render('articles/public-show', [
    'article' => $article->only(['title', 'content', 'slug']),
    'editableArticleId' => $request->user()?->can('update', $article)
        ? $article->id
        : null,
    'publicIndexEnabled' => (bool) config('articles.public_index_enabled'),
]);
```

記事のタイトル、Markdown の本文、スラッグが、React ページの props になります。管理画面へのリンクと公開一覧のナビゲーションは、別の props で制御しています。

## 2. React のページを選ぶ

コンポーネント名 `articles/public-show` は、アプリケーションの Inertia と Vite の連携によって、`resources/js/pages/articles/public-show.tsx` を指します。

`resources/js/app.tsx` では、公開用のページはレイアウトの選択で `null` を返します。管理画面のレイアウトを引き継がず、公開用のヘッダーとコンテンツの枠を自前で持つためです。

公開ページの描画部分を短くすると、次のようになります。

```tsx
<ArticleContent
    content={article.content}
    syntax={article.slug === 'showcase-zenn' ? 'zenn' : 'all'}
/>
```

このショーケースでは、`showcase-zenn` のスラッグにだけ Zenn のプロファイルを使います。ほかの記事は、すべての機能を備えた既存のパイプラインです。プロファイルはアプリケーション側で選んでいて、現時点の InkRail では、データベースの独立したカラムにはなっていません。

## 3. Inkstream で描画する

`resources/js/components/article-content.tsx` は、公開ページとログイン後の記事ページが共有する描画の境界です。

```tsx
import {
    InkstreamMarkdown,
    type SyntaxProfile,
} from '@catatsumuri/inkstream/react';

export default function ArticleContent({
    content,
    syntax = 'all',
}: {
    content: string;
    syntax?: SyntaxProfile;
}) {
    return (
        <InkstreamMarkdown className="min-w-0" syntax={syntax}>
            {content}
        </InkstreamMarkdown>
    );
}
```

Markdown の文字列を children として渡します。Inkstream が正規化とパーサーの変換を行い、その結果の要素を React のコンポーネントとして描画します。InkRail は、記事本文を HTML の文字列に事前変換することも、`dangerouslySetInnerHTML` で差し込むこともしません。

`min-w-0` というクラスで、描画結果が枠の中で縮められるようにしています。コードブロックなど幅の広い内容は、その枠の内側で、それぞれの表示方法を持っています。

## 4. 書き方のプロファイルを選ぶ

```tsx
// 標準の Markdown に Zenn の記法を加えたもの
<InkstreamMarkdown syntax="zenn">{content}</InkstreamMarkdown>

// 拡張記法を使わない標準の Markdown
<InkstreamMarkdown syntax="basic">{content}</InkstreamMarkdown>

// すべてを組み合わせた既存のパイプライン。省略したときの既定値でもある
<InkstreamMarkdown syntax="all">{content}</InkstreamMarkdown>
```

Zenn プロファイルには、表、数式、脚注、Zenn のメッセージと折りたたみ、各サービスの埋め込みが含まれます。`<Note>` などの Mintlify のタグは、このプロファイルではそのままの文字として残ります。違いは [Zenn のガイド](./showcase-zenn)と [Mintlify のガイド](./showcase-mintlify)で比べられます。

## 5. スタイルシートを読み込む

`resources/css/app.css` が、アプリケーション用の Inkstream のスタイルシートを読み込みます。

```css
@import './inkstream.css';
```

このファイルはパッケージのスタイルを読み込み、パッケージの意味的な色を InkRail のテーマ変数に対応づけます。たとえば次のとおりです。

```css
@import '@catatsumuri/inkstream/styles.css';

.ink-markdown {
    --ink-border: var(--border);
    --ink-foreground: var(--foreground);
    --ink-muted: var(--muted);
    --ink-primary: var(--primary);
}
```

実際のファイルでは、補助的な文字色、primary の文字色、アクセント、カードの色も対応づけています。これらの変数のおかげで、パッケージのコンポーネントがアプリケーションのライトテーマとダークテーマに追従します。

## 初回の描画とブラウザでの動作

Vite の設定には `@inertiajs/vite` が含まれます。Inertia のサーバーサイドレンダリングが有効なときは、同じ React コンポーネントがサーバーで最初の記事のマークアップを作り、ブラウザでハイドレーションします。InkRail は、SSR のためだけに記事本文の描画テンプレートをもう 1 つ持つことはしません。

一部のコンポーネントは、ブラウザ側の処理を必要とします。コードのハイライトはハイライターを初期化し、Mermaid は図を描き、GitHub のファイルの抜粋はソースを取得します。ページの描画に成功しても、外部のサービスに接続できることまでは保証されません。リンクカードのメタデータには OGP のエンドポイントも必要ですが、InkRail は現在それを渡していません。

## ソースをたどる

[InkRail のリポジトリ](https://github.com/catatsumuri/inkrail)で、連携の中心になるファイルは次のとおりです。

- [PublicArticleController.php](https://github.com/catatsumuri/inkrail/blob/main/app/Http/Controllers/PublicArticleController.php) は、公開済みの記事のデータを選びます。
- [public-show.tsx](https://github.com/catatsumuri/inkrail/blob/main/resources/js/pages/articles/public-show.tsx) は、公開ページを作り、プロファイルを選びます。
- [article-content.tsx](https://github.com/catatsumuri/inkrail/blob/main/resources/js/components/article-content.tsx) は、パッケージのレンダラーを包みます。
- [inkstream.css](https://github.com/catatsumuri/inkrail/blob/main/resources/css/inkstream.css) は、パッケージのスタイルをアプリケーションのテーマにつなぎます。
- [vite.config.ts](https://github.com/catatsumuri/inkrail/blob/main/vite.config.ts) は、Laravel、Inertia、React のビルド連携を設定します。

このアプリケーションの外でパッケージを使う方法は、[Inkstream の README](https://github.com/catatsumuri/inkstream/blob/v0.13.0/README.md) を読んでください。
