<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class RefreshMicrosoftToken
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // Log that middleware is triggered
        \Log::info('RefreshMicrosoftToken middleware triggered for user: ' . optional($user)->id);

        if ($user && $user->microsoft_refresh_token) {
            // Parse expiry safely
            $expiresAt = $user->microsoft_token_expires_at
                ? Carbon::parse($user->microsoft_token_expires_at)
                : null;

            // Log current token status
            \Log::info('Current token expires at: ' . ($expiresAt ? $expiresAt->toDateTimeString() : 'null'));

            // Refresh if expired or will expire in 1 minute
            if (!$expiresAt || Carbon::now()->greaterThan($expiresAt->subMinute())) {
                \Log::info('Access token expired or about to expire. Refreshing...');
                $this->refreshAccessToken($user);
            } else {
                \Log::info('Access token is still valid. No refresh needed.');
            }
        } else {
            \Log::info('No user or no refresh token available.');
        }

        return $next($request);
    }

    protected function refreshAccessToken($user)
    {
        try {
            $response = Http::asForm()->post(
                'https://login.microsoftonline.com/' . config('services.microsoft.tenant_id') . '/oauth2/v2.0/token',
                [
                    'client_id' => config('services.microsoft.client_id'),
                    'client_secret' => config('services.microsoft.client_secret'),
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $user->microsoft_refresh_token,
                    'scope' => 'User.Read offline_access',
                ]
            );

            if ($response->successful()) {
                $data = $response->json();

                $user->microsoft_token = $data['access_token'] ?? $user->microsoft_token;
                $user->microsoft_refresh_token = $data['refresh_token'] ?? $user->microsoft_refresh_token;
                $user->microsoft_token_expires_at = Carbon::now()->addSeconds($data['expires_in'] ?? 3600);

                $user->save();

                \Log::info('Microsoft access token refreshed successfully for user: ' . $user->id);
            } else {
                \Log::warning('Microsoft token refresh failed with status ' . $response->status() . ': ' . $response->body());
            }
        } catch (\Exception $e) {
            \Log::error('Microsoft token refresh exception: ' . $e->getMessage());
        }
    }
}
