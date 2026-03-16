<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Two-Factor Authentication routes
Route::middleware('auth')->group(function () {
	Volt::route('/2fa/verify', 'auth.two-factor-verify')->name('2fa.verify');
	Volt::route('/2fa/setup', 'auth.two-factor-setup')->name('2fa.setup')->middleware('2fa');
	Volt::route('/profile', 'profile.index')->name('profile.index')->middleware('2fa');
});

Route::middleware(['auth', '2fa'])->group(function () {
	// Dashboard & Traceability
	Volt::route('/', 'dashboard')->name('dashboard');
	Volt::route('/trace/pcc', 'trace.pcc')->name('trace.pcc');
    Volt::route('/trace/pcc-report', 'trace.pcc-report')->name('trace.pcc-report');

	// PPIC - HPM (prefix)
	Route::prefix('ppic/hpm')->group(function () {
		Volt::route('/dashboard', 'ppic.hpm.dashboard')->name('ppic.hpm.dashboard');
		Volt::route('/schedules', 'ppic.hpm.schedules')->name('ppic.hpm.schedules');
		// Require either 'manage' OR 'manage_pcc' permission
		Volt::route('/pccs', 'ppic.hpm.pccs')
			->name('ppic.hpm.pccs')
			->middleware(['permission:manage|manage_pcc']);
		// Scanner routes guarded by permissions
        Volt::route('/received', 'ppic.hpm.received')
            ->name('ppic.hpm.received')
            ->middleware(['permission:manage|receive_hpm']);
        Volt::route('/delivery', 'ppic.hpm.delivery')
            ->name('ppic.hpm.delivery')
            ->middleware(['permission:manage|delivery_hpm']);
	});

	// Production - Weld (roles: admin OR weld) with prefix
	Route::prefix('weld/hpm')->middleware('role:admin|weld')->group(function () {
		Volt::route('/check', 'weld.hpm.check')->name('weld.hpm.check');
	});

	// Quality (roles: admin OR quality) with prefix
	Route::prefix('qa/hpm')->middleware('role:admin|quality')->group(function () {
		Volt::route('/check', 'qa.hpm.check')->name('qa.hpm.check');
		Volt::route('/ccp', 'qa.hpm.ccp.index')->name('qa.hpm.ccp.index');
		Volt::route('/ccp/create', 'qa.hpm.ccp.create')->name('qa.hpm.ccp.create');
		Volt::route('/ccp/{id}/edit', 'qa.hpm.ccp.edit')->name('qa.hpm.ccp.edit');
	});

	// Master Data (admin only) with 'manage' prefix
	Route::prefix('manage')->middleware('role:admin')->group(function () {
		Volt::route('/users', 'manage.users.index')->name('manage.users.index');
		Volt::route('/customers', 'manage.customers.index')->name('manage.customers.index');
		Volt::route('/suppliers', 'manage.suppliers.index')->name('manage.suppliers.index');
		Volt::route('/finish-goods', 'manage.finish_goods.index')->name('manage.finish_goods.index');
	});

	// Scanner Locks (accessible by supervisors with unlock permissions or admins)
	Route::prefix('manage')->middleware(['permission:manage'])->group(function () {
		Volt::route('/scanner-locks', 'manage.scanner-locks')->name('manage.scanner-locks');
	});

	// CSRF Token Refresh Endpoint
	Route::get('/csrf-token-refresh', function () {
		return response()->json(['token' => csrf_token()]);
	})->name('csrf.refresh');

	// Push Notification Subscriptions
	Route::post('/push-subscribe', function (\Illuminate\Http\Request $request) {
		$user = $request->user();
		$user->updatePushSubscription(
			$request->input('endpoint'),
			$request->input('keys.p256dh'),
			$request->input('keys.auth'),
			$request->input('contentEncoding')
		);
		return response()->json(['success' => true]);
	})->name('push.subscribe');

	Route::post('/push-unsubscribe', function (\Illuminate\Http\Request $request) {
		$user = $request->user();
		$user->deletePushSubscription($request->input('endpoint'));
		return response()->json(['success' => true]);
	})->name('push.unsubscribe');
});

require __DIR__.'/auth.php';