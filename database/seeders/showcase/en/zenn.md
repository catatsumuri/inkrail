```html-embed
<div class="studio">
  <div class="eyebrow">INKRAIL / CREATIVE STUDIO</div>
  <h2>WORDS. IDEAS.<br><span>BEAUTIFULLY CONNECTED.</span></h2>
  <p>Turn captured ideas into stories worth sharing. A small visual studio, written entirely in HTML and CSS.</p>
  <div class="chips"><span>HTML + CSS</span><span>Inline SVG</span><span>Light & Dark</span></div>
  <div class="metrics"><div><b>01</b><small>Capture an idea</small></div><div><b>02</b><small>Connect the dots</small></div><div><b>03</b><small>Share your story</small></div></div>
  <div class="orb orb-a"></div><div class="orb orb-b"></div>
</div>
<style>
.studio{position:relative;isolation:isolate;overflow:hidden;box-sizing:border-box;padding:32px;border-radius:20px;background:linear-gradient(135deg,#101b35,#243b67 65%,#315e78);color:#f4f8ff;font-family:system-ui,sans-serif}
.eyebrow{font-size:12px;letter-spacing:.2em;color:#9ce6dd;font-weight:700}.studio h2{position:relative;z-index:1;font-size:clamp(24px,5vw,38px);line-height:1.12;letter-spacing:-.04em;margin:20px 0}.studio h2 span{color:#9ce6dd}.studio p{max-width:430px;color:#d5e1f1;font-size:14px;line-height:1.8}.chips{display:flex;flex-wrap:wrap;gap:8px;margin:20px 0}.chips span{font-size:12px;padding:6px 10px;border:1px solid #ffffff35;border-radius:30px;background:#ffffff0c}.metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;border-top:1px solid #ffffff30;padding-top:20px;position:relative;z-index:1}.metrics b{display:block;font-size:28px;font-weight:500;color:#fff}.metrics small{display:block;margin-top:6px;font-size:12px;color:#b8d5e7}.orb{position:absolute;z-index:-1;border-radius:50%;pointer-events:none}.orb-a{width:220px;height:220px;right:-80px;top:-80px;background:radial-gradient(circle,#65d6c555,transparent 70%)}.orb-b{width:280px;height:280px;right:-80px;bottom:-140px;border:1px solid #94bfff40;box-shadow:0 0 0 30px #94bfff0b,0 0 0 60px #94bfff08}
[data-theme="dark"] .studio{background:linear-gradient(135deg,#0c1224,#182946 65%,#214452)}
@media(max-width:400px){.studio{padding:22px}.metrics{gap:6px}.metrics b{font-size:24px}}
</style>
```

## A visual studio in HTML and CSS

An `html-embed` fence turns HTML and CSS into a diagram. Both panels are drawn without images. Try dark mode and a narrow viewport, then toggle the checkbox below.

```html-embed
<div class="map">
  <div class="kicker">FROM DRAFT TO DELIGHT</div><h3>From idea to published story</h3><p class="lead">Three steps, illustrated with HTML and CSS. Check the box to peek behind the scenes.</p>
  <input id="architecture" type="checkbox"><label for="architecture">Show how it works</label>
  <div class="pipeline"><div class="node"><span class="icon">✎</span><small>01 / WRITE</small><b>Markdown</b><p>Write words and diagrams as text.</p></div><div class="arrow">→</div><div class="node featured"><span class="icon">◇</span><small>02 / TRANSFORM</small><b>inkstream</b><p>Parse the syntax. Compose the view.</p></div><div class="arrow">→</div><div class="node"><span class="icon">↗</span><small>03 / PUBLISH</small><b>InkRail</b><p>Preview your work. Share it with readers.</p></div></div>
  <div class="detail"><b>HTML + CSS → sandboxed iframe</b><p>Rendered separately from the article. Styles stay inside the diagram and JavaScript cannot execute.</p></div>
  <div class="status"><span></span>No external libraries · CSS animation</div>
</div>
<style>
.map{--bg:#f4f7fc;--card:#fff;--ink:#20304d;--muted:#5e708b;--line:#dce5f1;padding:24px;border-radius:18px;background:var(--bg);color:var(--ink);font-family:system-ui,sans-serif}.kicker{font-size:12px;letter-spacing:.15em;font-weight:700;color:#5363c9}.map h3{font-size:24px;margin:10px 0}.lead{font-size:14px;color:var(--muted);line-height:1.7}.map input{accent-color:#6555d9}.map label{font-size:12px;cursor:pointer;display:inline-block;padding:8px 12px;border:1px solid var(--line);border-radius:20px;background:var(--card)}.pipeline{display:flex;align-items:center;gap:10px;margin:24px 0}.node{flex:1;min-width:0;box-sizing:border-box;padding:18px 14px;background:var(--card);border:1px solid var(--line);border-radius:14px;box-shadow:0 5px 14px #20304d08}.icon{display:grid;place-items:center;width:32px;height:32px;border-radius:10px;background:#edf0ff;color:#6555d9;font-size:22px;margin-bottom:14px}.node small{display:block;font-size:10px;color:var(--muted);letter-spacing:.08em}.node b{display:block;font-size:18px;margin-top:8px}.node p{font-size:12px;line-height:1.6;color:var(--muted);margin-bottom:0}.featured{border-color:#8c80e6;box-shadow:0 5px 20px #6555d91a}.arrow{color:#8c80e6;font-size:22px}.detail{display:none;padding:16px;border:1px dashed #8c80e6;border-radius:12px;font-size:12px;line-height:1.7}.detail p{margin-bottom:0;color:var(--muted)}#architecture:checked~.detail{display:block}.status{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--muted);margin-top:18px}.status span{width:8px;height:8px;border-radius:50%;background:#259d83;animation:breathe 2s infinite}
[data-theme="dark"] .map{--bg:#121b2c;--card:#1c2940;--ink:#edf2ff;--muted:#afbed6;--line:#34425b}[data-theme="dark"] .icon{background:#34315a;color:#c6bfff}[data-theme="dark"] .kicker{color:#b4acff}
@keyframes breathe{50%{box-shadow:0 0 0 5px #259d8320}}
@media(max-width:540px){.pipeline{flex-direction:column;align-items:stretch}.arrow{align-self:center;transform:rotate(90deg)}.node{padding:14px}.icon{float:right;margin:0}.map{padding:18px}.node p{margin-top:6px}.map h3{font-size:21px}}
@media(prefers-reduced-motion:reduce){.status span{animation:none}}
</style>
```

Explore the Zenn-style syntax supported by this npm release of inkstream.

## Messages

### Syntax

```markdown
:::message
Drafts are visible only to their owner.
:::
```

### Preview

:::message
Drafts are visible only to their owner.
:::

## Alerts

### Syntax

```markdown
:::message alert
Do not publish passwords or private information.
:::
```

### Preview

:::message alert
Do not publish passwords or private information.
:::

## Collapsible details

### Syntax

```markdown
:::details Pre-publication checklist
- Check the title.
- Test the links.
- Describe your images.
:::
```

### Preview

:::details Pre-publication checklist
- Check the title.
- Test the links.
- Describe your images.
:::

## Named code blocks

### Syntax

````markdown
```php:routes/web.php
Route::get('p', [PublicArticleController::class, 'index']);
```
````

### Preview

```php:routes/web.php
Route::get('p', [PublicArticleController::class, 'index']);
```

## Diffs

### Syntax

````markdown
```diff typescript:article.ts
- const published = false;
+ const published = true;
```
````

### Preview

```diff typescript:article.ts
- const published = false;
+ const published = true;
```

## Image sizing and captions

### Syntax

```markdown
![Writing and publishing](/images/inkrail-showcase.svg =320x)
*Write an idea, refine it, and publish an article.*
```

### Preview

![Writing and publishing](/images/inkrail-showcase.svg =320x)
*Write an idea, refine it, and publish an article.*

## Link cards

### Syntax

```markdown
@[card](https://github.com/catatsumuri/inkstream)

Without an OGP endpoint, cards show the URL rather than fetched metadata.
```

### Preview

@[card](https://github.com/catatsumuri/inkstream)

Without an OGP endpoint, cards show the URL rather than fetched metadata.

## Compatibility

This page uses the Zenn syntax profile: standard Markdown and Zenn features are enabled, while Mintlify tags and Inkstream-only quiz, chart, and tree components are left as source text.

## GitHub file excerpts with line ranges

Place a GitHub file URL in its own paragraph to embed it. Append `#L3-L9` to show a range, or `#L3` to start at one line. A single-line anchor displays only that line in the Zenn profile. The example pins a release tag so the source stays stable.

### Syntax

```markdown
https://github.com/catatsumuri/inkstream/blob/v0.11.0/README.md#L1-L12
```

### Preview

https://github.com/catatsumuri/inkstream/blob/v0.11.0/README.md#L1-L12

File contents are fetched from GitHub in the browser. If the request fails, the original GitHub link remains available. The explicit `@[github](URL)` syntax is also supported.

## Tables, math, and footnotes

### Syntax

```markdown
| Feature | Included |
| --- | --- |
| Standard Markdown | Yes |
| Zenn directives | Yes |

Inline math: $e^{i\theta} + 1 = 0$.

A named footnote[^example] and an inline footnote^[Inline **formatted** note].

[^example]: A regular footnote definition.
```

### Preview

| Feature | Included |
| --- | --- |
| Standard Markdown | Yes |
| Zenn directives | Yes |

Inline math: $e^{i\theta} + 1 = 0$.

A named footnote[^example] and an inline footnote^[Inline **formatted** note].

[^example]: A regular footnote definition.

## Zenn profile boundaries

The following source stays literal in this profile. The Mintlify showcase continues to render these tags with the default, all-features profile.

### Syntax

```markdown
<Note>This is a Mintlify tag.</Note>
```

### Preview

<Note>This is a Mintlify tag.</Note>

## External embed syntax

Zenn supports URL-based YouTube, GitHub and X embeds, plus explicit directives for these providers. Third-party content requires access to its provider.

```markdown
@[youtube](WRVsOCh907o)
@[tweet](https://x.com/jack/status/20)
@[gist](https://gist.github.com/example/abcdef?file=example.ts)
@[codepen](https://codepen.io/example/pen/example)
@[slideshare](slide-key)
@[speakerdeck](4f926da9cb4cd0001f00a1ff?slide=24)
@[docswell](https://www.docswell.com/s/ku-suke/LK7J5V-hello-docswell#p13)
@[jsfiddle](https://jsfiddle.net/example/example/)
@[codesandbox](https://codesandbox.io/embed/example)
@[stackblitz](https://stackblitz.com/edit/example)
@[figma](https://www.figma.com/design/example)
@[blueprintue](https://blueprintue.com/render/0ovgynk-/)
```

## Explore the other guides

- [Basic Markdown — Writing fundamentals](./showcase-markdown)
- [GitHub flavor — Tables, tasks, and alerts](./showcase-gfm)
- [Mintlify flavor — Composing documentation](./showcase-mintlify)
- [inkstream extensions — Diagrams, charts, trees, and quizzes](./showcase-extensions)
