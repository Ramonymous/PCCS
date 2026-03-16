<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;
 
// Users will be redirected to this route if not logged in
Volt::route('/login', 'auth.login')->name('login');
 
// Define the logout
Route::get('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
});
