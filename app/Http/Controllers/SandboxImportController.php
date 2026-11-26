<?php

namespace App\Http\Controllers;

use App\Actions\ImportSandboxDocuments;
use App\Exceptions\ZennImportException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SandboxImportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, ImportSandboxDocuments $import): RedirectResponse
    {
        try {
            $result = $import($request->user());
        } catch (ZennImportException|LockTimeoutException $exception) {
            report($exception);
            Inertia::flash('toast', ['type' => 'error', 'message' => __('The articles could not be imported from GitHub.')]);

            return to_route('dashboard');
        }

        return to_route('articles.index', ['namespace' => 'sandbox'])->with('sandbox_import', $result);
    }
}
