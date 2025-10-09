<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class MicrosoftAuthController extends Controller
{
    /**
     * Redirect to Microsoft login
     */
    public function redirect()
    {
        return Socialite::driver('microsoft')
            ->stateless()
            ->scopes(['openid', 'profile', 'email', 'User.Read', 'offline_access']) // <-- add offline_access
            ->redirect();
    }

    /**
     * Handle Microsoft callback
     */
    public function callback()
    {
        // Get Microsoft user
        $microsoftUser = Socialite::driver('microsoft')->stateless()->user();

        // Save or update user
        $user = User::updateOrCreate(
            ['email' => $microsoftUser->getEmail()],
            [
                'name' => $microsoftUser->getName(),
                'microsoft_id' => $microsoftUser->getId(),
                'password' => bcrypt(Str::random(16)),
                'microsoft_token' => $microsoftUser->token,
                'microsoft_refresh_token' => $microsoftUser->refreshToken ?? null,
                'microsoft_token_expires_at' => now()->addSeconds($microsoftUser->expiresIn),
            ]
        );

        // Fetch profile photo from Microsoft Graph (optional)
        try {
            $response = Http::withToken($user->microsoft_token)
                ->get('https://graph.microsoft.com/v1.0/me/photo/$value');

            if ($response->ok()) {
                $imageName = 'profile_'.$user->id.'.jpg';
                Storage::put('public/profiles/' . $imageName, $response->body());
                $user->profile_url = 'profiles/' . $imageName;
                $user->save();
            }
        } catch (\Exception $e) {
            // ignore photo errors
        }

        // Login user
        Auth::login($user);

        return redirect('/');
    }
}
