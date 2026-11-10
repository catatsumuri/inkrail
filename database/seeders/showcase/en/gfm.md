GitHub Flavored Markdown extends basic Markdown with tables, task lists, strikethrough, and autolinks. GitHub-style alerts are an additional supported extension.

## Tables

### Syntax

```markdown
| Feature | Purpose | Examples |
| :--- | :---: | ---: |
| Markdown | Writing | 8 |
| Zenn | Notes | 6 |
| Mintlify | Structure | 10 |
```

### Preview

| Feature | Purpose | Examples |
| :--- | :---: | ---: |
| Markdown | Writing | 8 |
| Zenn | Notes | 6 |
| Mintlify | Structure | 10 |

## Task lists

### Syntax

```markdown
- [x] Write the article
- [x] Check rendering
- [ ] Request a review
- [ ] Publish

These checkboxes reflect the source, rather than saving changes to the article.
```

### Preview

- [x] Write the article
- [x] Check rendering
- [ ] Request a review
- [ ] Publish

These checkboxes reflect the source, rather than saving changes to the article.

## Strikethrough

### Syntax

```text
~~Old instructions~~ → **Current instructions**

Use two tildes; a single tilde is not strikethrough.
```

### Preview

~~Old instructions~~ → **Current instructions**

Use two tildes; a single tilde is not strikethrough.

## Autolinks

Read the source at https://github.com/catatsumuri/inkstream for details.

## GitHub-style alerts

### Syntax

```markdown
> [!NOTE]
> Helpful background information.

> [!TIP]
> Keep examples short and concrete.

> [!IMPORTANT]
> Changing a slug changes the public URL.

> [!WARNING]
> Deleting an article removes its public page.

> [!CAUTION]
> Check the target before proceeding. WARNING and CAUTION share the alert style.
```

### Preview

> [!NOTE]
> Helpful background information.

> [!TIP]
> Keep examples short and concrete.

> [!IMPORTANT]
> Changing a slug changes the public URL.

> [!WARNING]
> Deleting an article removes its public page.

> [!CAUTION]
> Check the target before proceeding. WARNING and CAUTION share the alert style.

## Links to specific GitHub lines

Use GitHub's `#L3` anchor for one line or `#L3-L9` for a range. Place a labeled link inside a paragraph with surrounding text to navigate to GitHub rather than embed its contents. Line anchors are GitHub URL behavior, not a separate GFM parsing feature.

### Syntax

```markdown
Open [line 3](https://github.com/catatsumuri/inkstream/blob/v0.11.0/README.md#L3) on GitHub.
Read [lines 3–9](https://github.com/catatsumuri/inkstream/blob/v0.11.0/README.md#L3-L9) on GitHub.
```

### Preview

Open [line 3](https://github.com/catatsumuri/inkstream/blob/v0.11.0/README.md#L3) on GitHub.

Read [lines 3–9](https://github.com/catatsumuri/inkstream/blob/v0.11.0/README.md#L3-L9) on GitHub.

For an inline file excerpt, see the [Zenn GitHub embed example](./showcase-zenn).

## Explore the other guides

- [Basic Markdown — Writing fundamentals](./showcase-markdown)
- [Zenn flavor — Messages and disclosures](./showcase-zenn)
- [Mintlify flavor — Composing documentation](./showcase-mintlify)
- [inkstream extensions — Diagrams, charts, trees, and quizzes](./showcase-extensions)
