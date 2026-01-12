<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS Paths
    |--------------------------------------------------------------------------
    | These paths will accept CORS requests.
    | "api/*" is for API routes
    | "sanctum/csrf-cookie" is required if using Sanctum
    */
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    */
    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    | List ONLY frontend domains
    */
    'allowed_origins' => [
        'http://localhost:5173',      // Vue (Vite)
        'http://127.0.0.1:5173',
        'https://prodsystem.mjqe-purchasing.site',     // Production
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origin Patterns
    |--------------------------------------------------------------------------
    | Use this if you want to allow subdomains
    | Example: app.yourdomain.com, admin.yourdomain.com
    */
    'allowed_origins_patterns' => [
        '/^https:\/\/.*\.mjqe-purchasing\.site$/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers
    |--------------------------------------------------------------------------
    */
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'Origin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    | Headers accessible from frontend JS
    */
    'exposed_headers' => [
        'Authorization',
    ],

    /*
    |--------------------------------------------------------------------------
    | Max Age
    |--------------------------------------------------------------------------
    | Cache preflight request (seconds)
    */
    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials
    |--------------------------------------------------------------------------
    | TRUE if using cookies / Sanctum
    | FALSE if using Bearer token only
    */
    'supports_credentials' => true,

];
