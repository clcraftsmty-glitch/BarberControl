<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BusinessSetting;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\Client;
use App\Models\CommissionAdjustment;
use App\Models\CommissionSettlement;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Models\WalkInEntry;
use App\Models\WhatsAppMessage;
use App\Observers\CriticalModelObserver;
use App\Policies\AppointmentPolicy;
use App\Policies\BarberPolicy;
use App\Policies\BusinessSettingPolicy;
use App\Policies\CashRegisterSessionPolicy;
use App\Policies\ClientPolicy;
use App\Policies\CommissionSettlementPolicy;
use App\Policies\SalePolicy;
use App\Policies\ServicePolicy;
use App\Policies\UserPolicy;
use App\Policies\WalkInEntryPolicy;
use App\Policies\WhatsAppMessagePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (Schema::hasTable('business_settings')) {
            $settings = BusinessSetting::current();
            config([
                'app.name' => $settings->business_name,
                'app.timezone' => $settings->timezone,
            ]);
            date_default_timezone_set($settings->timezone);
        }

        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(Barber::class, BarberPolicy::class);
        Gate::policy(BusinessSetting::class, BusinessSettingPolicy::class);
        Gate::policy(CashRegisterSession::class, CashRegisterSessionPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(CommissionSettlement::class, CommissionSettlementPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(WalkInEntry::class, WalkInEntryPolicy::class);
        Gate::policy(WhatsAppMessage::class, WhatsAppMessagePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::define('manage-security', fn (User $user): bool => $user->is_active && $user->role === UserRole::Administrator);

        foreach ([
            User::class,
            BusinessSetting::class,
            Client::class,
            Service::class,
            Barber::class,
            Appointment::class,
            WalkInEntry::class,
            Sale::class,
            CashRegisterSession::class,
            CashMovement::class,
            CommissionSettlement::class,
            CommissionAdjustment::class,
        ] as $model) {
            $model::observe(CriticalModelObserver::class);
        }
    }
}
