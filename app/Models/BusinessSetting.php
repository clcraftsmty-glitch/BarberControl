<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class BusinessSetting extends Model
{
    public const DAYS = [
        'monday' => 'Lunes',
        'tuesday' => 'Martes',
        'wednesday' => 'Miércoles',
        'thursday' => 'Jueves',
        'friday' => 'Viernes',
        'saturday' => 'Sábado',
        'sunday' => 'Domingo',
    ];

    protected $fillable = [
        'business_name',
        'legal_name',
        'tax_id',
        'logo_path',
        'address',
        'phones',
        'general_schedule',
        'currency_code',
        'currency_symbol',
        'tax_name',
        'tax_rate',
        'prices_include_tax',
        'ticket_header',
        'ticket_footer',
        'timezone',
        'default_appointment_duration_minutes',
        'arrival_tolerance_minutes',
        'cancellation_notice_hours',
        'minimum_booking_notice_minutes',
        'maximum_booking_advance_days',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'phones' => 'array',
            'general_schedule' => 'array',
            'tax_rate' => 'decimal:2',
            'prices_include_tax' => 'boolean',
            'default_appointment_duration_minutes' => 'integer',
            'arrival_tolerance_minutes' => 'integer',
            'cancellation_notice_hours' => 'integer',
            'minimum_booking_notice_minutes' => 'integer',
            'maximum_booking_advance_days' => 'integer',
        ];
    }

    public static function current(): self
    {
        if (Schema::hasTable('business_settings')) {
            $settings = self::query()->first();

            if ($settings) {
                return $settings;
            }
        }

        return new self(self::defaults());
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'business_name' => 'BarberControl',
            'phones' => [],
            'general_schedule' => collect(array_keys(self::DAYS))->mapWithKeys(
                fn (string $day): array => [$day => ['enabled' => true, 'start' => '00:00', 'end' => '23:59']],
            )->all(),
            'currency_code' => 'MXN',
            'currency_symbol' => '$',
            'tax_name' => 'IVA',
            'tax_rate' => 0,
            'prices_include_tax' => true,
            'ticket_header' => 'Gestión profesional',
            'ticket_footer' => 'Gracias por tu visita',
            'timezone' => config('app.timezone', 'America/Mexico_City'),
            'default_appointment_duration_minutes' => 30,
            'arrival_tolerance_minutes' => 10,
            'cancellation_notice_hours' => 2,
            'minimum_booking_notice_minutes' => 0,
            'maximum_booking_advance_days' => 3650,
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        $version = $this->updated_at?->getTimestamp() ?? 1;

        return "/branding/logo?v={$version}";
    }

    public function formatMoney(float|int|string $amount): string
    {
        return $this->currency_symbol.number_format((float) $amount, 2).' '.$this->currency_code;
    }

    /** @return array{subtotal: float, tax: float, total: float} */
    public function includedTaxBreakdown(float|int|string $total): array
    {
        $total = round((float) $total, 2);
        $rate = (float) $this->tax_rate;

        if ($rate <= 0 || ! $this->prices_include_tax) {
            return ['subtotal' => $total, 'tax' => 0.0, 'total' => $total];
        }

        $subtotal = round($total / (1 + ($rate / 100)), 2);

        return ['subtotal' => $subtotal, 'tax' => round($total - $subtotal, 2), 'total' => $total];
    }
}
