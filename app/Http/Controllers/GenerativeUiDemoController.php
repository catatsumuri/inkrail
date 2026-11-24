<?php

namespace App\Http\Controllers;

use App\Support\GenerativeUiDemoScript;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedEvent;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerativeUiDemoController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('demo');
    }

    public function stream(Request $request, GenerativeUiDemoScript $script): StreamedResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['intro', GenerativeUiDemoScript::REPORT_ACTION])],
            'values' => ['required_if:action,'.GenerativeUiDemoScript::REPORT_ACTION, 'array'],
            'values.period' => ['required_if:action,'.GenerativeUiDemoScript::REPORT_ACTION, Rule::in(GenerativeUiDemoScript::PERIODS)],
            'values.threshold' => ['required_if:action,'.GenerativeUiDemoScript::REPORT_ACTION, 'integer', 'between:0,100'],
            'values.archived' => ['sometimes', 'boolean'],
        ]);

        $chunks = $validated['action'] === 'intro'
            ? $script->intro()
            : $script->report(
                $validated['values']['period'],
                (int) $validated['values']['threshold'],
                (bool) ($validated['values']['archived'] ?? false),
            );

        $delay = (int) config('demo.stream_delay_ms', 35) * 1000;

        return response()->eventStream(function () use ($chunks, $delay) {
            foreach ($chunks as $chunk) {
                yield new StreamedEvent('chunk', $chunk);

                if ($delay > 0) {
                    usleep($delay);
                }
            }
        }, endStreamWith: new StreamedEvent('done', ''));
    }
}
