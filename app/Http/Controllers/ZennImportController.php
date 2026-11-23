<?php

namespace App\Http\Controllers;

use App\Actions\LogSecurityEvent;
use App\Actions\Zenn\ImportZennArticles;
use App\Exceptions\ZennImportException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ZennImportController extends Controller
{
    public function __invoke(Request $request, ImportZennArticles $import, LogSecurityEvent $logSecurityEvent): RedirectResponse
    {
        Gate::authorize('import-from-zenn');

        try {
            $result = $import($request->user());
        } catch (ZennImportException $exception) {
            report($exception);
            $logSecurityEvent->failure('articles.zenn_import_failed', $request->user());
            Inertia::flash('toast', ['type' => 'error', 'message' => __($exception->getPrevious() instanceof LockTimeoutException
                ? 'The repository is busy or the sync lock was lost. Try again shortly.'
                : 'The Zenn articles could not be imported.')]);

            return to_route('dashboard');
        }

        $request->session()->flash('zenn_import', $result);

        return to_route('dashboard');
    }
}
