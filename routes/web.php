<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Family Essentials
|--------------------------------------------------------------------------
| This backend is API-only. The user interfaces are separate applications:
|
|   Frontend/    React dashboard  -> /api/v1
|   Mobile app/  Flutter app      -> /mobile/v1
|
| The Blade screen mockups that used to be served here have been removed;
| the design references now live in `UI/` and
| `stitch_cross_platform_dashboard_ui/` at the repository root, and are
| implemented in the two clients above.
*/

// Discovery document: handy for a quick "is this the right host?" check.
Route::get('/', fn () => response()->json([
    'application' => 'Family Essentials API',
    'surfaces' => [
        'frontend' => [
            'prefix' => '/api/v1',
            'token_ability' => 'dashboard',
            'client' => 'Frontend/ (React)',
        ],
        'mobile' => [
            'prefix' => '/mobile/v1',
            'token_ability' => 'mobile',
            'client' => 'Mobile app/ (Flutter)',
        ],
    ],
    'health' => url('/up'),
]));
