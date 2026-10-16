<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Family Essentials Multi-Tenant Platform & Dashboard
|--------------------------------------------------------------------------
| Recreating Google Stitch Master Specifications:
| Platform Admin (Landlord) Portal: W-PA-01 to W-PA-04
| Tenant Admin (Client Family) Portal: W-TA-01 to W-TA-05
*/

// Switchboard & Showcase Portal
Route::get('/', function () {
    return view('welcome');
})->name('home');

// ==========================================
// Platform Admin (Landlord / Super Admin)
// ==========================================
Route::prefix('admin')->group(function () {
    // W-PA-01: Platform Admin Login
    Route::get('/login', function () {
        return view('admin.login');
    })->name('admin.login');

    // W-PA-02: Global Analytics & Operations Dashboard
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('admin.dashboard');

    // W-PA-03: Tenant Management Console (CRUD & Provisioning)
    Route::get('/tenants', function () {
        return view('admin.tenants');
    })->name('admin.tenants');

    // W-PA-04: Global Audit & Security Log Explorer
    Route::get('/audit-logs', function () {
        return view('admin.audit-logs');
    })->name('admin.audit-logs');
});

// ==========================================
// Tenant Admin (Client Household / Family)
// ==========================================
// W-TA-01: Tenant Admin Login & Registration
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::get('/register', function () {
    return view('auth.login', ['mode' => 'register']);
})->name('register');

Route::prefix('app')->group(function () {
    // W-TA-02: Household Kanban Overview & Today Dashboard
    Route::get('/dashboard', function () {
        return view('app.dashboard');
    })->name('app.dashboard');

    // W-TA-03: Family Member & Role Management
    Route::get('/members', function () {
        return view('app.members');
    })->name('app.members');

    // W-TA-04: Saved Places & Geofence Map Console
    Route::get('/places', function () {
        return view('app.places');
    })->name('app.places');

    // W-TA-05: Billing & Subscription Portal
    Route::get('/billing', function () {
        return view('app.billing');
    })->name('app.billing');
});
