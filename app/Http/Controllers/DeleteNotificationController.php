<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeleteNotificationController extends Controller
{
    public function __invoke(Request $request, string $notification): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $user->notifications()->findOrFail($notification)->delete();

        return back();
    }
}
