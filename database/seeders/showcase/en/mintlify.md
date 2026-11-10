Compose documentation with Mintlify-style tags. This is inkstream's supported syntax, not full MDX compatibility. Keep opening tags on one line and quote attributes with double quotes.

## Callouts

### Syntax

```markdown
<Note>Helpful **background** information.</Note>

<Tip>Keep examples short and concrete.</Tip>

<Info>Different flavors can coexist in one article.</Info>

<Warning>Tags never execute JavaScript expressions.</Warning>

<Check>The article is ready to share.</Check>
```

### Preview

<Note>Helpful **background** information.</Note>

<Tip>Keep examples short and concrete.</Tip>

<Info>Different flavors can coexist in one article.</Info>

<Warning>Tags never execute JavaScript expressions.</Warning>

<Check>The article is ready to share.</Check>

## Cards

### Syntax

```markdown
<CardGroup cols="2">
    <Card title="Basic Markdown" icon="book-open" href="./showcase-markdown">
        Learn headings, lists, and code.
    </Card>

    <Card title="GitHub flavor" icon="table" href="./showcase-gfm">
        Explore tables, tasks, and alerts.
    </Card>
</CardGroup>
```

### Preview

<CardGroup cols="2">
<Card title="Basic Markdown" icon="book-open" href="./showcase-markdown">
Learn headings, lists, and code.
</Card>

<Card title="GitHub flavor" icon="table" href="./showcase-gfm">
Explore tables, tasks, and alerts.
</Card>
</CardGroup>

## Steps

### Syntax

```markdown
<Steps>
    <Step title="Write">
        Create a draft.
    </Step>

    <Step title="Review">
        Check the content and links.
    </Step>

    <Step title="Publish">
        Share the public URL.
    </Step>
</Steps>
```

### Preview

<Steps>
<Step title="Write">
Create a draft.
</Step>

<Step title="Review">
Check the content and links.
</Step>

<Step title="Publish">
Share the public URL.
</Step>
</Steps>

## Tabs

### Syntax

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

### Preview

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

## Accordions

### Syntax

```markdown
<AccordionGroup>
    <Accordion title="Can I unpublish an article?" icon="file-text">
        Yes. Return it to a draft in the editor.
    </Accordion>

    <Accordion title="Can I change the URL?" icon="link">
        Yes. Changing the slug makes the previous URL unavailable.
    </Accordion>
</AccordionGroup>
```

### Preview

<AccordionGroup>
<Accordion title="Can I unpublish an article?" icon="file-text">
Yes. Return it to a draft in the editor.
</Accordion>

<Accordion title="Can I change the URL?" icon="link">
Yes. Changing the slug makes the previous URL unavailable.
</Accordion>
</AccordionGroup>

## Inline components

### Syntax

```markdown
The status is <Badge color="green">Published</Badge>.

A <Tooltip tip="The article identifier in its URL">slug</Tooltip> uses lowercase letters, numbers, and hyphens.
```

### Preview

The status is <Badge color="green">Published</Badge>.

A <Tooltip tip="The article identifier in its URL">slug</Tooltip> uses lowercase letters, numbers, and hyphens.

## API fields

### Syntax

```markdown
These are documentation examples, not newly implemented API endpoints.

<ParamField body="title" type="string" required="true">
    The article title, up to 255 characters.
</ParamField>

<ParamField body="options" type="object">
    Publishing options.

    <Expandable title="Option details">
        `is_published` is a boolean.
    </Expandable>
</ParamField>

<ResponseField name="slug" type="string">
    The identifier used in the public URL.
</ResponseField>
```

### Preview

These are documentation examples, not newly implemented API endpoints.

<ParamField body="title" type="string" required="true">
The article title, up to 255 characters.
</ParamField>

<ParamField body="options" type="object">
Publishing options.

<Expandable title="Option details">
`is_published` is a boolean.
</Expandable>
</ParamField>

<ResponseField name="slug" type="string">
The identifier used in the public URL.
</ResponseField>

## Updates

### Syntax

```markdown
<Update label="Showcase" description="Syntax guides">
Explore Markdown, Zenn, Mintlify, GFM, and inkstream extensions.
</Update>
```

### Preview

<Update label="Showcase" description="Syntax guides">
Explore Markdown, Zenn, Mintlify, GFM, and inkstream extensions.
</Update>

## Explore the other guides

- [Basic Markdown — Writing fundamentals](./showcase-markdown)
- [Zenn flavor — Messages and disclosures](./showcase-zenn)
- [GitHub flavor — Tables, tasks, and alerts](./showcase-gfm)
- [inkstream extensions — Diagrams, charts, trees, and quizzes](./showcase-extensions)
