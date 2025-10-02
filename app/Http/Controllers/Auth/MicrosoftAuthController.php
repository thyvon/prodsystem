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
        $microsoftUser = Socialite::driver('microsoft')->stateless()->user();

        $user = User::updateOrCreate(
            ['email' => $microsoftUser->getEmail()],
            [
                'name' => $microsoftUser->getName(),
                'microsoft_id' => $microsoftUser->getId(),
                'password' => bcrypt(Str::random(16)),
                'profile_url' => $microsoftUser->avatar, // save directly here
            ]
        );

        Auth::login($user);

        return redirect('/');
    }
    }
