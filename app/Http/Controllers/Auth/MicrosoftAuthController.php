<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MicrosoftAuthController extends Controller
{
    /**
     * Redirect to Microsoft login
     */
    public function redirect()
    {
        return Socialite::driver('microsoft')
            ->stateless() // important for cross-domain/session
            ->scopes(['openid', 'profile', 'email', 'User.Read'])
            ->redirect();
    }

    /**
     * Handle Microsoft callback
     */
    public function callback()
    {
        // Get Microsoft user
        $microsoftUser = Socialite::driver('microsoft')->stateless()->user();

        // Find or create user
        $user = User::updateOrCreate(
            ['email' => $microsoftUser->getEmail()],
            [
                'name' => $microsoftUser->getName(),
                'microsoft_id' => $microsoftUser->getId(),
                'password' => bcrypt(Str::random(16)), // random password for new users
            ]
        );

        // Save Microsoft profile URL if exists
        if (!empty($microsoftUser->avatar)) {
            $user->profile_url = $microsoftUser->avatar;
            $user->save();
        }

        // Login the user
        Auth::login($user);

        return redirect('/'); // or your dashboard
    }
}
