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
            ->stateless() // Support for SPA / API or no-session setups
            ->scopes([
                'openid',
                'profile',
                'email',
                'User.Read',
                'Files.ReadWrite.All',
                // 'Sites.ReadWrite.All',
                'offline_access', // Required for token refresh
            ])
            ->with(['prompt' => 'select_account']) // Show account picker
            ->redirect();
    }

    /**
     * Handle the callback from Microsoft OAuth.
     */
    public function callback()
    {
        try {
            // Get user info from Microsoft Graph via Socialite
            $microsoftUser = Socialite::driver('microsoft')->stateless()->user();

            // Find or create a user in the database
            $user = User::updateOrCreate(
                ['email' => $microsoftUser->getEmail()],
                [
                    'name'                        => $microsoftUser->getName(),
                    'microsoft_id'                => $microsoftUser->getId(),
                    'password'                    => bcrypt(Str::random(16)), // random password for login
                    'microsoft_token'      => $microsoftUser->token,
                    'microsoft_refresh_token'     => $microsoftUser->refreshToken ?? null,
                    'microsoft_token_expires_at'  => now()->addSeconds($microsoftUser->expiresIn),
                ]
            );

            // Fetch and save Microsoft profile photo (optional)
            $this->updateMicrosoftProfilePhoto($user);

            // Log the user in
            Auth::login($user, true);

            Log::info('Microsoft user logged in successfully', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'expires' => $user->microsoft_token_expires_at,
            ]);

            return redirect('/'); // Redirect to dashboard/home

        } catch (\Throwable $e) {
            Log::error('Microsoft OAuth Callback Error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return redirect()->route('login')->with(
                'error',
                'Microsoft authentication failed. Please try again.'
            );
        }
    }

    /**
     * Fetch and save user's Microsoft profile photo from Graph API.
     */
    protected function updateMicrosoftProfilePhoto(User $user): void
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
