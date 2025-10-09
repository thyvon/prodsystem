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
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user?->microsoft_refresh_token) {
            $expiresAt = $user->microsoft_token_expires_at
                ? Carbon::parse($user->microsoft_token_expires_at)
                : null;

            // Refresh if expired or expiring within 5 minutes
            if (!$expiresAt || now()->greaterThanOrEqualTo($expiresAt->copy()->subMinutes(5))) {
                $this->refreshAccessToken($user);
            }
        }

        return $next($request);
    }

    protected function refreshAccessToken($user): void
    {
        try {
            $tenantId = config('services.microsoft.tenant_id');
            $clientId = config('services.microsoft.client_id');
            $clientSecret = config('services.microsoft.client_secret');

            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $user->microsoft_refresh_token,
                    'scope' => 'User.Read Files.ReadWrite.All offline_access',
                ]
            );

            if ($response->failed()) {
                Log::error('Microsoft token refresh failed', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                abort(403, "Microsoft access token could not be refreshed. Please re-login.");
            }

            $data = $response->json();

            $user->update([
                'microsoft_token' => $data['access_token'] ?? $user->microsoft_token,
                'microsoft_refresh_token' => $data['refresh_token'] ?? $user->microsoft_refresh_token,
                'microsoft_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            ]);
        } catch (\Throwable $e) {
            Log::error('Microsoft token refresh exception', [
                'user_id' => $user->id ?? null,
                'message' => $e->getMessage(),
            ]);
            abort(403, "Microsoft access token refresh failed. Please re-login.");
        }
    }
}
