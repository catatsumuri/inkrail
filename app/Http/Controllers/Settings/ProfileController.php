<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Notifications\SendGeneralNotification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Support\SecurityEvent;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request, SendGeneralNotification $sendGeneralNotification): RedirectResponse
    {
        $previousEmail = $request->user()->email;

        $request->user()->fill($request->profileData());

        $isEmailChanged = $request->user()->isDirty('email');

        if ($isEmailChanged) {
            $request->user()->email_verified_at = null;
        }

        $saved = $request->user()->save();

        if ($isEmailChanged && $saved) {
            $sendGeneralNotification->toEmailAddress(
                $previousEmail,
                SecurityEvent::EmailChanged->subject(),
                SecurityEvent::EmailChanged->mailBody(),
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request, SendGeneralNotification $sendGeneralNotification): RedirectResponse
    {
        $user = $request->user();
        $email = $user->email;

        if (! $user->delete()) {
            return back();
        }

        // Logging out would otherwise cycle and save() a remember token onto
        // this now-deleted (exists: false) model, re-inserting the row.
        $user->setRememberToken(null);
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $sendGeneralNotification->toEmailAddress(
            $email,
            SecurityEvent::AccountDeleted->subject(),
            SecurityEvent::AccountDeleted->mailBody(),
        );

        return redirect('/');
    }
}
