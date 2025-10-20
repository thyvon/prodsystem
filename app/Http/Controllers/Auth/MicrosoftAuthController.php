<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class MicrosoftAuthController extends Controller
{
    /**
     * Redirect the user to Microsoft's OAuth login page.
     */
    public function redirect()
    {
        return Socialite::driver('microsoft')
            ->stateless()
            ->scopes([
                'openid',
                'profile',
                'email',
                'User.Read',
                'Files.ReadWrite',
                'Sites.ReadWrite',
                'offline_access',
            ])
            ->with([
                'prompt' => 'login'
            ])
            ->redirect();
    }

    /**
     * Handle the callback from Microsoft OAuth.
     */
    public function callback()
    {
        try {
            $microsoftUser = Socialite::driver('microsoft')->stateless()->user();

            // Extract refresh token from raw response if not on the object
            $tokenResponse = $microsoftUser->tokenResponse ?? [];
            $refreshToken = $microsoftUser->refreshToken ?? $tokenResponse['refresh_token'] ?? null;

            if (!$refreshToken) {
                Log::warning('No refresh token received from Microsoft', [
                    'user_id' => $microsoftUser->getId(),
                    'raw_response' => $tokenResponse,
                ]);
                // Optionally, force re-auth with prompt=consent here
            }

            $user = User::updateOrCreate(
                ['email' => $microsoftUser->getEmail()],
                [
                    'name'                        => $microsoftUser->getName(),
                    'microsoft_id'                => $microsoftUser->getId(),
                    'password'                    => bcrypt(Str::random(16)),
                    'microsoft_token'             => $microsoftUser->token,
                    'microsoft_refresh_token'     => $refreshToken,
                    'microsoft_token_expires_at'  => $microsoftUser->expiresIn ? now()->addSeconds($microsoftUser->expiresIn) : null,
                ]
            );

            // Fetch profile photo (optional, errors will not break login)
            $this->fetchMicrosoftProfilePhoto($user);

            Auth::login($user, true);

            Log::info('Microsoft login successful', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'expires' => $user->microsoft_token_expires_at,
                'refresh_token' => $user->microsoft_refresh_token, // Added for debugging
            ]);

            return redirect('/');

        } catch (\Throwable $e) {
            Log::error('Microsoft OAuth Callback Error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'request' => request()->all(),
            ]);

            return redirect()->route('login')->with('error', 'Microsoft authentication failed. Please try again.');
        }
    }

    /**
     * Fetch user's Microsoft profile photo and store locally.
     */
    protected function fetchMicrosoftProfilePhoto(User $user): void
    {
        try {
            $response = Http::withToken($user->microsoft_token)
                ->get('https://graph.microsoft.com/v1.0/me/photo/$value');

            if ($response->ok()) {
                $imageName = 'profile_' . $user->id . '.jpg';
                Storage::put('public/profiles/' . $imageName, $response->body());
                $user->update(['profile_url' => 'profiles/' . $imageName]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch Microsoft profile photo', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}