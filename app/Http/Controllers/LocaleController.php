<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use LaravelLang\Routes\Helpers\Name;

class LocaleController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate(['locale' => ['required', Rule::in(config('articles.locales'))]]);
        $request->session()->put(Name::session(), $validated['locale']);

        return back();
    }
}
