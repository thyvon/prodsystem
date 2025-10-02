<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class RefreshMicrosoftToken
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        if ($user && $user->microsoft_refresh_token) {
            // Check if token is expired or about to expire (e.g., within 1 min)
            if (!$user->microsoft_token_expires_at || now()->greaterThan($user->microsoft_token_expires_at->subMinute())) {
                $this->refreshAccessToken($user);
            }
        }

        return $next($request);
    }

    protected function refreshAccessToken($user)
    {
        $response = Http::asForm()->post('https://login.microsoftonline.com/'.config('services.microsoft.tenant_id').'/oauth2/v2.0/token', [
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $user->microsoft_refresh_token,
            'scope' => 'User.Read offline_access',
        ]);

        if ($response->ok()) {
            $data = $response->json();
            $user->microsoft_token = $data['access_token'];
            if (isset($data['refresh_token'])) {
                $user->microsoft_refresh_token = $data['refresh_token'];
            }
            $user->microsoft_token_expires_at = now()->addSeconds($data['expires_in']);
            $user->save();
        }
    }
}
