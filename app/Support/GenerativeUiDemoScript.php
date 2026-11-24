<?php

namespace App\Support;

/**
 * Canned Markdown for the generative UI demo. It stands in for an LLM: the
 * output is deterministic, so the public demo needs no API key and cannot be
 * abused to spend money.
 */
class GenerativeUiDemoScript
{
    /** @var array<int, string> */
    public const PERIODS = ['day', 'week', 'month'];

    public const REPORT_ACTION = 'build-report';

    /**
     * @return array<int, string>
     */
    public function intro(): array
    {
        return $this->chunks(<<<'MD'
## What is Generative UI?

A chat reply does not have to be a wall of text. With **generative UI**, an AI agent answers with *interface* (cards, forms, charts) that your app renders and the visitor can use.

| Style | Who builds the UI? | Trade-off |
| --- | --- | --- |
| Controlled | You build the components; the model picks one | Safest and on-brand, least flexible |
| Declarative | The model composes UI from your component catalog | A good middle ground |
| Open-ended | The model writes the UI code itself | Most flexible, hardest to trust |

Protocols are forming around these ideas: A2UI describes a UI as streamable data, AG-UI carries the event stream between agent and app, and MCP Apps ships a view together with a tool.

### This page is a live demo

Everything here is Markdown streamed over **Server-Sent Events** and rendered by [Inkstream](https://github.com/catatsumuri/inkstream) with Generative UI enabled. The "agent" is a script, not an LLM, but the rendering path is the same.

<Card title="Declarative UI, rendered live">
  <Row align="center" gap="3">
    <Icon name="sparkles" />
    <Badge color="green">Streaming</Badge>
  </Row>
</Card>

Now try the form. The controls stay disabled until the stream finishes; then submit it and the server streams back a report built from your choices.

<Form id="report" action="build-report">
  <Select id="period" label="Period" default="week">
    <Option value="day">
      Daily
    </Option>

    <Option value="week">
      Weekly
    </Option>

    <Option value="month">
      Monthly
    </Option>
  </Select>

  <Slider id="threshold" label="Alert threshold" min="0" max="100" step="5" default="65" />

  <Checkbox id="archived" label="Include archived" default="false" />

  <Button type="submit">Build report</Button>
</Form>

MD);
    }

    /**
     * @return array<int, string>
     */
    public function report(string $period, int $threshold, bool $includeArchived): array
    {
        $label = ['day' => 'Daily', 'week' => 'Weekly', 'month' => 'Monthly'][$period];
        $archived = $includeArchived ? 'included' : 'excluded';
        $values = $this->series($period, $threshold);
        $alerts = count(array_filter($values, fn (int $value): bool => $value >= $threshold));
        $bars = collect($values)->map(fn (int $value, int $index): string => 'P'.($index + 1).': '.$value)->implode("\n");

        return $this->chunks(<<<MD
---

## {$label} report

The agent read your form and composed this view. Settings: **{$label}**, threshold **{$threshold}**, archived items **{$archived}**.

<Card title="{$alerts} of 5 periods at or above {$threshold}">
  <Row align="center" gap="3">
    <Icon name="bell" />
    <Badge color="green">Generated</Badge>
  </Row>
</Card>

```chart:bar
_title: Sample activity ({$label}, illustrative)
_min: 0
_max: 100
{$bars}
```

The numbers are made up. In a real app, the form's `submit` event would reach your backend, which would validate the values, call a model, and stream the next view the same way.

MD);
    }

    /**
     * @return array<int, int>
     */
    private function series(string $period, int $threshold): array
    {
        $seed = ['day' => 31, 'week' => 47, 'month' => 59][$period] + $threshold;

        return array_map(fn (int $step): int => (int) (($seed * ($step + 3) * 7) % 71) + 25, range(1, 5));
    }

    /**
     * @return array<int, string>
     */
    private function chunks(string $markdown): array
    {
        return mb_str_split($markdown, 14);
    }
}
