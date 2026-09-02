<?php

use App\Http\Controllers\AppointmentFeedController;
use App\Http\Controllers\BusinessLogoController;
use App\Http\Controllers\CashRegisterExportController;
use App\Http\Controllers\CommissionSettlementReceiptController;
use App\Http\Controllers\DatabaseBackupDownloadController;
use App\Http\Controllers\SaleTicketController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Livewire\Appointments\Calendar as AppointmentCalendar;
use App\Livewire\Appointments\Today as AppointmentToday;
use App\Livewire\Barbers\Create as CreateBarber;
use App\Livewire\Barbers\Edit as EditBarber;
use App\Livewire\Barbers\Index as BarberIndex;
use App\Livewire\Barbers\Show as ShowBarber;
use App\Livewire\CashRegister\Dashboard as CashRegisterDashboard;
use App\Livewire\Clients\Create as CreateClient;
use App\Livewire\Clients\Edit as EditClient;
use App\Livewire\Clients\Index as ClientIndex;
use App\Livewire\Clients\Show as ShowClient;
use App\Livewire\Commissions\Index as CommissionIndex;
use App\Livewire\Dashboard\Operational as OperationalDashboard;
use App\Livewire\Sales\Index as SaleIndex;
use App\Livewire\Sales\Show as ShowSale;
use App\Livewire\Security\Center as SecurityCenter;
use App\Livewire\Security\TwoFactorChallenge;
use App\Livewire\Security\TwoFactorSetup;
use App\Livewire\Services\Create as CreateService;
use App\Livewire\Services\Edit as EditService;
use App\Livewire\Services\Index as ServiceIndex;
use App\Livewire\Services\Show as ShowService;
use App\Livewire\Settings\Business as BusinessSettings;
use App\Livewire\Users\Index as UserIndex;
use App\Livewire\WhatsApp\Index as WhatsAppIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'));

Route::get('/branding/logo', BusinessLogoController::class)
    ->name('branding.logo');

Route::middleware('auth')->group(function () {
    Route::get('/two-factor/setup', TwoFactorSetup::class)->name('two-factor.setup');
    Route::get('/two-factor/challenge', TwoFactorChallenge::class)->name('two-factor.challenge');
});

Route::get('dashboard', OperationalDashboard::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])
    ->name('whatsapp.webhook');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive']);

Route::get('/tickets/{sale}/whatsapp.pdf', [SaleTicketController::class, 'whatsapp'])
    ->middleware('signed')
    ->name('sales.ticket.whatsapp');

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
    Route::get('/', AppointmentToday::class)->name('index');
    Route::get('/calendar', AppointmentCalendar::class)->name('calendar');
});

Route::middleware(['auth', 'verified'])
    ->get('/cash-register', CashRegisterDashboard::class)
    ->name('cash-register.index');
Route::middleware(['auth', 'verified'])
    ->get('/cash-register/{cashRegisterSession}/export', CashRegisterExportController::class)
    ->name('cash-register.export');

Route::middleware(['auth', 'verified'])->prefix('commissions')->name('commissions.')->group(function () {
    Route::get('/', CommissionIndex::class)->name('index');
    Route::get('/{commissionSettlement}/receipt', [CommissionSettlementReceiptController::class, 'print'])->name('receipt');
    Route::get('/{commissionSettlement}/receipt.pdf', [CommissionSettlementReceiptController::class, 'pdf'])->name('receipt.pdf');
});

Route::middleware(['auth', 'verified'])
    ->get('/whatsapp', WhatsAppIndex::class)
    ->name('whatsapp.index');

Route::middleware(['auth', 'verified'])
    ->get('/users', UserIndex::class)
    ->name('users.index');

Route::middleware(['auth', 'verified'])
    ->get('/settings/business', BusinessSettings::class)
    ->name('settings.business');

Route::middleware(['auth', 'verified'])->prefix('security')->name('security.')->group(function () {
    Route::get('/', SecurityCenter::class)->name('index');
    Route::get('/backups/{databaseBackup}/download', DatabaseBackupDownloadController::class)->name('backups.download');
});

Route::middleware(['auth', 'verified'])->prefix('sales')->name('sales.')->group(function () {
    Route::get('/', SaleIndex::class)->name('index');
    Route::get('/{sale}', ShowSale::class)->name('show');
    Route::get('/{sale}/ticket', [SaleTicketController::class, 'print'])->name('ticket.print');
    Route::get('/{sale}/ticket.pdf', [SaleTicketController::class, 'pdf'])->name('ticket.pdf');
});

require __DIR__.'/auth.php';
