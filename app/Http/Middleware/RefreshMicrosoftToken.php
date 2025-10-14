<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RefreshMicrosoftToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // ✅ Skip refresh for specific routes
        $excludedRoutes = [
            'logout',
            'login',
            'auth/microsoft/redirect',
            'auth/microsoft/callback',
        ];

        foreach ($excludedRoutes as $route) {
            if ($request->is($route)) {
                return $next($request);
            }
        }

        if ($user?->microsoft_refresh_token) {
            $expiresAt = $user->microsoft_token_expires_at
                ? Carbon::parse($user->microsoft_token_expires_at)
                : null;

            // ✅ Refresh if expired or less than 5 minutes remaining
            if (!$expiresAt || now()->greaterThanOrEqualTo($expiresAt->copy()->subMinutes(5))) {
                $this->refreshAccessToken($user);
            }
        }

        return $next($request);
    }

    /**
     * Refresh Microsoft access token.
     */
    protected function refreshAccessToken($user): void
    {
        try {
            $tenantId = config('services.microsoft.tenant_id');
            $clientId = config('services.microsoft.client_id');
            $clientSecret = config('services.microsoft.client_secret');

            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $user->microsoft_refresh_token,
                    'scope'         => 'User.Read Files.ReadWrite Sites.Selected Sites.ReadWrite.All offline_access',
                ]
            );

            $data = $response->json();

            // ✅ Refresh failed or token invalid
            if ($response->failed() || isset($data['error'])) {
                Log::warning('Microsoft token refresh failed', [
                    'user_id' => $user->id,
                    'status'  => $response->status(),
                    'body'    => $data,
                ]);

                // ⚠️ Force Microsoft login if refresh token expired
                if (isset($data['error']) && $data['error'] === 'invalid_grant') {
                    $user->update([
                        'microsoft_token' => null,
                        'microsoft_refresh_token' => null,
                        'microsoft_token_expires_at' => null,
                    ]);

                    redirect()->route('microsoft.login')->send();
                }

                return;
            }

            // ✅ Update user tokens
            $user->update([
                'microsoft_token'             => $data['access_token'] ?? $user->microsoft_token,
                'microsoft_refresh_token'     => $data['refresh_token'] ?? $user->microsoft_refresh_token,
                'microsoft_token_expires_at'  => now()->addSeconds($data['expires_in'] ?? 3600),
            ]);

            Log::info("✅ Microsoft token refreshed for user {$user->id}");

        } catch (\Throwable $e) {
            Log::error('Microsoft token refresh exception', [
                'user_id' => $user->id ?? null,
                'message' => $e->getMessage(),
            ]);
            // ⚠️ Optional: You can also redirect to login here if needed
            redirect()->route('microsoft.login')->send();
        }
    }
}
