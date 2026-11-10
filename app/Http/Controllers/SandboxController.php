<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SandboxController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(config('app.demo_mode'), 404);

        if ($request->user() !== null) {
            return to_route('articles.index');
        }

        $showcase = User::where('is_showcase_owner', true)->firstOrFail();
        $user = DB::transaction(function () use ($showcase): User {
            $user = new User;
            $user->forceFill([
                'name' => 'Sandbox visitor',
                'email' => Str::uuid().'@sandbox.invalid',
                'email_verified_at' => now(),
                'password' => Str::random(64),
                'is_sandbox' => true,
            ])->save();

            foreach ($showcase->articles()->orderBy('id')->get() as $article) {
                $user->articles()->create([
                    'title' => $article->title,
                    'content' => $article->content,
                    'is_published' => false,
                ]);
            }

            return $user->refresh();
        });

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('articles.index');
    }
}
