<?php

use App\Http\Controllers\ReviewMarkdownController;
use App\Livewire\Login;
use App\Livewire\ReviewHistory;
use App\Livewire\ReviewShow;
use App\Livewire\ReviewWizard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/', ReviewWizard::class)->name('home');
    Route::livewire('/bewertungen', ReviewHistory::class)->name('reviews.index');
    Route::livewire('/bewertungen/{review}', ReviewShow::class)->name('reviews.show');
    Route::get('/bewertungen/{review}/markdown', ReviewMarkdownController::class)->name('reviews.markdown');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
