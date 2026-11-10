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
