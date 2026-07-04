<?php

use App\Http\Controllers\BracketController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PickController;
use App\Http\Controllers\PoolController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\StandingsController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('pools.index') : redirect()->route('login');
});

Route::post('locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/pools', [PoolController::class, 'index'])->name('pools.index');
    Route::get('/pools/create', [PoolController::class, 'create'])->name('pools.create');
    Route::post('/pools', [PoolController::class, 'store'])->name('pools.store');
    Route::get('/pools/{pool}', [PoolController::class, 'show'])->name('pools.show');
    Route::delete('/pools/{pool}', [PoolController::class, 'destroy'])->name('pools.destroy');

    // Manager: scoring / tie-breaker / deadline settings.
    Route::get('/pools/{pool}/settings', [PoolController::class, 'settings'])->name('pools.settings');
    Route::patch('/pools/{pool}/settings', [PoolController::class, 'updateSettings'])->name('pools.settings.update');
    Route::post('/pools/{pool}/members/{user}/reset-password', [PoolController::class, 'resetMemberPassword'])->name('pools.members.reset-password');

    Route::post('/pools/{pool}/open', [PoolController::class, 'open'])->name('pools.open');
    Route::post('/pools/{pool}/close', [PoolController::class, 'close'])->name('pools.close');
    Route::post('/pools/{pool}/reopen', [PoolController::class, 'reopen'])->name('pools.reopen');

    // Standings (all members).
    Route::get('/pools/{pool}/standings', [StandingsController::class, 'index'])->name('pools.standings');

    // Player: bracket pick sheet.
    Route::get('/pools/{pool}/picks', [PickController::class, 'edit'])->name('pools.picks.edit');
    Route::put('/pools/{pool}/picks', [PickController::class, 'update'])->name('pools.picks.update');
    Route::post('/pools/{pool}/picks/round', [PickController::class, 'saveRound'])->name('pools.picks.round');
    Route::get('/pools/{pool}/players/{user}/picks', [PickController::class, 'show'])->name('pools.picks.show');

    // Manager: incremental per-round lock/unlock.
    Route::post('/pools/{pool}/rounds/{round}/lock', [PoolController::class, 'lockRound'])->name('pools.rounds.lock');
    Route::post('/pools/{pool}/rounds/{round}/unlock', [PoolController::class, 'unlockRound'])->name('pools.rounds.unlock');

    // Manager: import picks from Excel/CSV.
    Route::get('/pools/{pool}/picks-import', [\App\Http\Controllers\PickImportController::class, 'showImport'])->name('pools.picks.import.show');
    Route::get('/pools/{pool}/picks-template', [\App\Http\Controllers\PickImportController::class, 'template'])->name('pools.picks.template');
    Route::post('/pools/{pool}/picks-import', [\App\Http\Controllers\PickImportController::class, 'import'])->name('pools.picks.import');

    // Manager: enter match results.
    Route::get('/pools/{pool}/results', [ResultController::class, 'edit'])->name('pools.results.edit');
    Route::put('/pools/{pool}/results', [ResultController::class, 'update'])->name('pools.results.update');

    // Manager: load the 32 teams + R32 matchups (builds the full bracket).
    Route::get('/pools/{pool}/bracket', [BracketController::class, 'edit'])->name('pools.bracket.edit');
    Route::post('/pools/{pool}/bracket', [BracketController::class, 'store'])->name('pools.bracket.store');
    Route::delete('/pools/{pool}/bracket', [BracketController::class, 'destroy'])->name('pools.bracket.destroy');

    // Manager: invite players.
    Route::get('/pools/{pool}/invites', [InviteController::class, 'index'])->name('pools.invites.index');
    Route::post('/pools/{pool}/invites', [InviteController::class, 'store'])->name('pools.invites.store');
    Route::delete('/pools/{pool}/invites/{invite}', [InviteController::class, 'destroy'])->name('pools.invites.destroy');
    Route::post('/pools/{pool}/join-link/regenerate', [PoolController::class, 'regenerateJoinLink'])->name('pools.join-link.regenerate');

    // Authenticated: accept an invite.
    Route::post('/join/{token}', [InviteController::class, 'accept'])->name('invite.accept');
});

// Public: invite landing page (works for guests; they log in / register first).
Route::get('/join/{token}', [InviteController::class, 'show'])->name('invite.show');

// Public: pool join link (no email — shareable on WhatsApp, etc.).
Route::get('/p/{token}', [InviteController::class, 'showPool'])->name('pool.join');

Route::middleware('auth')->group(function () {
    // Forced password change for manager-issued temporary passwords.
    Route::get('/password/change', [\App\Http\Controllers\Auth\PasswordChangeController::class, 'show'])->name('password.change.show');
    Route::post('/password/change', [\App\Http\Controllers\Auth\PasswordChangeController::class, 'update'])->name('password.change.update');

    // Global admin: user management / password recovery.
    Route::get('/admin/users', [\App\Http\Controllers\AdminUserController::class, 'index'])->name('admin.users.index');
    Route::post('/admin/users/{user}/reset-password', [\App\Http\Controllers\AdminUserController::class, 'resetPassword'])->name('admin.users.reset-password');
    Route::get('/admin/pools', [\App\Http\Controllers\AdminPoolController::class, 'index'])->name('admin.pools.index');
    Route::post('/admin/pools/{pool}/approve', [\App\Http\Controllers\AdminPoolController::class, 'approve'])->name('admin.pools.approve');
    Route::delete('/admin/pools/{pool}', [\App\Http\Controllers\AdminPoolController::class, 'reject'])->name('admin.pools.reject');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
