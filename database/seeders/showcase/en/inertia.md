InkRail is both a live demo of Inkstream and a small example of integrating it into a Laravel application with Inertia.js and React. This guide follows the code used to render this very page.

## The rendering path

```html-embed
<div class="rp">
  <div class="kicker">RENDERING PATH</div>
  <h3>From a database row to React elements</h3>
  <input id="rp-payload" type="checkbox"><label for="rp-payload">Show what travels between steps</label>
  <div class="lanes">
    <div class="lane">
      <div class="lh"><span class="dot server"></span>LARAVEL</div>
      <div class="node" style="--d:0s"><small>DATABASE</small><b>articles.content</b><span class="pl">"## What is…" (Markdown string)</span></div>
      <div class="link"><i style="--d:.2s"></i></div>
      <div class="node" style="--d:.3s"><small>CONTROLLER</small><b>PublicArticleController</b><span class="pl">show() → Article → props</span></div>
    </div>
    <div class="bridge"><i style="--d:.6s"></i></div>
    <div class="lane">
      <div class="lh"><span class="dot inertia"></span>INERTIA</div>
      <div class="node featured" style="--d:.9s"><small>PAGE PROPS</small><b>JSON in the response</b><span class="pl">{ article: { title, content } }</span></div>
    </div>
    <div class="bridge"><i style="--d:1.2s"></i></div>
    <div class="lane">
      <div class="lh"><span class="dot browser"></span>BROWSER · REACT</div>
      <div class="node" style="--d:1.5s"><small>PAGE</small><b>public-show.tsx</b><span class="pl">&lt;ArticleContent content=… /&gt;</span></div>
      <div class="link"><i style="--d:1.7s"></i></div>
      <div class="node" style="--d:1.9s"><small>COMPONENT</small><b>ArticleContent</b><span class="pl">&lt;InkstreamMarkdown&gt;</span></div>
      <div class="link"><i style="--d:2.1s"></i></div>
      <div class="node featured" style="--d:2.3s"><small>RENDERER</small><b>InkstreamMarkdown</b><span class="pl">Markdown → AST → React</span></div>
      <div class="link"><i style="--d:2.5s"></i></div>
      <div class="node done" style="--d:2.7s"><small>RESULT</small><b>React elements</b><span class="pl">the article you are reading</span></div>
    </div>
  </div>
  <div class="foot"><span></span>The database keeps the original Markdown, never a rendered HTML copy.</div>
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

Inertia delivers the page component and its data. Inkstream interprets the Markdown. The database keeps the original Markdown string rather than a rendered HTML copy.

## 1. Load and authorize the article

The public route binds an article by its slug:

```php
Route::get('p/{article:slug}', [PublicArticleController::class, 'show'])
    ->name('public.articles.show');
```

The controller checks publication before returning the page. An unpublished article produces a 404, even if someone knows its slug.

The central part of `PublicArticleController::show()` is:

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

The article title, Markdown content, and slug become props of the React page. Separate props control the management link and the public-index navigation.

## 2. Select the React page

The component name `articles/public-show` identifies `resources/js/pages/articles/public-show.tsx` through the application's Inertia/Vite integration.

In `resources/js/app.tsx`, the public pages return `null` from the layout selector. They supply their own public header and content container instead of inheriting the management layout.

A shortened version of the public page's rendering call is:

```tsx
<ArticleContent
    content={article.content}
    syntax={article.slug === 'showcase-zenn' ? 'zenn' : 'all'}
/>
```

This showcase chooses the Zenn profile for the `showcase-zenn` slug. Other articles use the existing all-features pipeline. The profile is selected in the application; it is not a separate database field in InkRail today.

## 3. Render with Inkstream

`resources/js/components/article-content.tsx` is the shared rendering boundary for public pages and authenticated article pages:

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

Pass the Markdown string as children. Inkstream performs its normalization and parser transforms, then renders the resulting elements through React components. InkRail does not pre-render the article body into an HTML string or insert it with `dangerouslySetInnerHTML`.

The `min-w-0` class allows the renderer to shrink inside its container. Code blocks and other wide content have their own presentation inside that boundary.

## 4. Choose an authoring profile

```tsx
// Standard Markdown plus Zenn's authoring dialect.
<InkstreamMarkdown syntax="zenn">{content}</InkstreamMarkdown>

// Standard Markdown without the extended dialects.
<InkstreamMarkdown syntax="basic">{content}</InkstreamMarkdown>

// The existing combined pipeline; also the default when omitted.
<InkstreamMarkdown syntax="all">{content}</InkstreamMarkdown>
```

The Zenn profile includes tables, math, footnotes, Zenn messages and disclosures, and provider embeds. Mintlify tags such as `<Note>` stay literal in that profile. See the [Zenn guide](./showcase-zenn) and [Mintlify guide](./showcase-mintlify) to compare them.

## 5. Load the stylesheet

`resources/css/app.css` imports the application's Inkstream stylesheet:

```css
@import './inkstream.css';
```

That file imports the package styles and maps their semantic colors onto InkRail's theme variables. For example:

```css
@import '@catatsumuri/inkstream/styles.css';

.ink-markdown {
    --ink-border: var(--border);
    --ink-foreground: var(--foreground);
    --ink-muted: var(--muted);
    --ink-primary: var(--primary);
}
```

The actual file also maps muted foreground, primary foreground, accent, and card colors. These variables let package components follow the application's light and dark themes.

## Initial rendering and browser behavior

The Vite configuration includes `@inertiajs/vite`. When Inertia server-side rendering is active, the same React component can produce the initial article markup on the server and hydrate in the browser. InkRail does not maintain a second article-body rendering template for SSR.

Some components still need browser work: code highlighting initializes its highlighter, Mermaid draws diagrams, and GitHub file excerpts fetch source content. A successful page render does not guarantee that an external provider is reachable. Link-card metadata also needs an OGP endpoint; InkRail currently does not pass one.

## Follow the source

These files are the main integration points in the [InkRail repository](https://github.com/catatsumuri/inkrail):

- [PublicArticleController.php](https://github.com/catatsumuri/inkrail/blob/main/app/Http/Controllers/PublicArticleController.php) selects published article data.
- [public-show.tsx](https://github.com/catatsumuri/inkrail/blob/main/resources/js/pages/articles/public-show.tsx) provides the public page and chooses the profile.
- [article-content.tsx](https://github.com/catatsumuri/inkrail/blob/main/resources/js/components/article-content.tsx) wraps the package renderer.
- [inkstream.css](https://github.com/catatsumuri/inkrail/blob/main/resources/css/inkstream.css) connects package styling to the application theme.
- [vite.config.ts](https://github.com/catatsumuri/inkrail/blob/main/vite.config.ts) configures the Laravel, Inertia, and React build integration.

For package usage outside this application, read the [Inkstream README](https://github.com/catatsumuri/inkstream/blob/v0.13.0/README.md).
