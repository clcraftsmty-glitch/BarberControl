<?php

use App\Http\Controllers\AppointmentFeedController;
use App\Livewire\Appointments\Calendar as AppointmentCalendar;
use App\Livewire\Barbers\Create as CreateBarber;
use App\Livewire\Barbers\Edit as EditBarber;
use App\Livewire\Barbers\Index as BarberIndex;
use App\Livewire\Barbers\Show as ShowBarber;
use App\Livewire\Clients\Create as CreateClient;
use App\Livewire\Clients\Edit as EditClient;
use App\Livewire\Clients\Index as ClientIndex;
use App\Livewire\Clients\Show as ShowClient;
use App\Livewire\Services\Create as CreateService;
use App\Livewire\Services\Edit as EditService;
use App\Livewire\Services\Index as ServiceIndex;
use App\Livewire\Services\Show as ShowService;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'));

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified'])->prefix('clients')->name('clients.')->group(function () {
    Route::get('/', ClientIndex::class)->name('index');
    Route::get('/create', CreateClient::class)->name('create');
    Route::get('/{client}', ShowClient::class)->name('show');
    Route::get('/{client}/edit', EditClient::class)->name('edit');
});

Route::middleware(['auth', 'verified'])->prefix('services')->name('services.')->group(function () {
    Route::get('/', ServiceIndex::class)->name('index');
    Route::get('/create', CreateService::class)->name('create');
    Route::get('/{service}', ShowService::class)->name('show');
    Route::get('/{service}/edit', EditService::class)->name('edit');
});

Route::middleware(['auth', 'verified'])->prefix('barbers')->name('barbers.')->group(function () {
    Route::get('/', BarberIndex::class)->name('index');
    Route::get('/create', CreateBarber::class)->name('create');
    Route::get('/{barber}', ShowBarber::class)->name('show');
    Route::get('/{barber}/edit', EditBarber::class)->name('edit');
});

Route::middleware(['auth', 'verified'])->prefix('appointments')->name('appointments.')->group(function () {
    Route::get('/feed', AppointmentFeedController::class)->name('feed');
    Route::get('/', AppointmentCalendar::class)->name('index');
});

require __DIR__.'/auth.php';
