<?php

use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['demo.stream_delay_ms' => 0]);
});

/**
 * @return array<int, array{event: string, data: string}>
 */
function sseEvents(string $body): array
{
    return collect(preg_split("/\n\n/", trim($body)))->map(function (string $block): array {
        $event = 'message';
        $data = [];

        foreach (explode("\n", $block) as $line) {
            if (str_starts_with($line, 'event: ')) {
                $event = substr($line, 7);
            } elseif (str_starts_with($line, 'data:')) {
                $data[] = preg_replace('/^ /', '', substr($line, 5));
            }
        }

        return ['event' => $event, 'data' => implode("\n", $data)];
    })->all();
}

test('guests can open the generative ui demo page', function () {
    $this->get(route('demo'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('demo'));
});

test('the intro streams markdown chunks and then signals completion', function () {
    $response = $this->post(route('demo.stream'), ['action' => 'intro'])->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');

    $events = sseEvents($response->streamedContent());
    $markdown = collect($events)->where('event', 'chunk')->pluck('data')->implode('');

    expect(collect($events)->last()['event'])->toBe('done');
    expect($markdown)->toContain('## What is Generative UI?')
        ->toContain('<Form id="report" action="build-report">');
});

test('submitting the form streams a report built from the submitted values', function () {
    $response = $this->post(route('demo.stream'), [
        'action' => 'build-report',
        'values' => ['period' => 'month', 'threshold' => 80, 'archived' => true],
    ])->assertOk();

    $markdown = collect(sseEvents($response->streamedContent()))->where('event', 'chunk')->pluck('data')->implode('');

    expect($markdown)->toContain('## Monthly report')
        ->toContain('threshold **80**')
        ->toContain('archived items **included**')
        ->toContain('```chart:bar');
});

test('the stream endpoint only accepts allowlisted actions and valid values', function (array $payload) {
    $this->postJson(route('demo.stream'), $payload)->assertUnprocessable();
})->with([
    'unknown action' => [['action' => 'delete-everything']],
    'missing action' => [[]],
    'report without values' => [['action' => 'build-report']],
    'unknown period' => [['action' => 'build-report', 'values' => ['period' => 'year', 'threshold' => 10]]],
    'threshold out of range' => [['action' => 'build-report', 'values' => ['period' => 'day', 'threshold' => 500]]],
]);
