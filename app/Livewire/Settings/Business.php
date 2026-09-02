<?php

namespace App\Livewire\Settings;

use App\Models\BusinessSetting;
use DateTimeZone;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class Business extends Component
{
    use AuthorizesRequests, WithFileUploads;

    public BusinessSetting $settings;

    public string $business_name = '';

    public ?string $legal_name = null;

    public ?string $tax_id = null;

    public ?string $address = null;

    /** @var array<int, string> */
    public array $phones = [''];

    /** @var array<string, array{enabled: bool, start: string, end: string}> */
    public array $general_schedule = [];

    public string $currency_code = 'MXN';

    public string $currency_symbol = '$';

    public string $tax_name = 'IVA';

    public string $tax_rate = '0';

    public bool $prices_include_tax = true;

    public ?string $ticket_header = null;

    public ?string $ticket_footer = null;

    public string $timezone = 'America/Mexico_City';

    public int $default_appointment_duration_minutes = 30;

    public int $arrival_tolerance_minutes = 10;

    public int $cancellation_notice_hours = 2;

    public int $minimum_booking_notice_minutes = 0;

    public int $maximum_booking_advance_days = 3650;

    public mixed $logo = null;

    public function mount(): void
    {
        $this->authorize('viewAny', BusinessSetting::class);
        $this->settings = BusinessSetting::current();
        $this->fillFromSettings();
    }

    public function addPhone(): void
    {
        if (count($this->phones) < 5) {
            $this->phones[] = '';
        }
    }

    public function updatedCurrencyCode(string $currency): void
    {
        $this->currency_symbol = match ($currency) {
            'USD' => 'US$',
            'CAD' => 'C$',
            'EUR' => '€',
            default => '$',
        };
    }

    public function removePhone(int $index): void
    {
        unset($this->phones[$index]);
        $this->phones = array_values($this->phones);

        if ($this->phones === []) {
            $this->phones = [''];
        }
    }

    public function removeLogo(): void
    {
        $this->authorize('update', $this->settings);

        if ($this->settings->logo_path) {
            Storage::disk('public')->delete($this->settings->logo_path);
            $this->settings->update(['logo_path' => null, 'updated_by' => auth()->id()]);
        }

        $this->logo = null;
        session()->flash('status', 'Logotipo eliminado correctamente.');
    }

    public function save(): void
    {
        $this->authorize('update', $this->settings);
        $data = $this->validate($this->rules(), [], $this->validationAttributes());
        $this->validateSchedules();

        $phones = collect($data['phones'])
            ->map(fn (string $phone): string => trim($phone))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $logoPath = $this->settings->logo_path;

        if ($this->logo) {
            $newLogoPath = $this->logo->store('business-logos', 'public');

            if ($logoPath) {
                Storage::disk('public')->delete($logoPath);
            }

            $logoPath = $newLogoPath;
        }

        $this->settings->fill([
            ...$data,
            'phones' => $phones,
            'logo_path' => $logoPath,
            'business_name' => trim($data['business_name']),
            'legal_name' => filled($data['legal_name'] ?? null) ? trim($data['legal_name']) : null,
            'tax_id' => filled($data['tax_id'] ?? null) ? trim($data['tax_id']) : null,
            'address' => filled($data['address'] ?? null) ? trim($data['address']) : null,
            'ticket_header' => filled($data['ticket_header'] ?? null) ? trim($data['ticket_header']) : null,
            'ticket_footer' => filled($data['ticket_footer'] ?? null) ? trim($data['ticket_footer']) : null,
            'updated_by' => auth()->id(),
        ]);
        $this->settings->save();

        config(['app.name' => $this->settings->business_name, 'app.timezone' => $this->settings->timezone]);
        date_default_timezone_set($this->settings->timezone);
        $this->logo = null;
        $this->fillFromSettings();
        session()->flash('status', 'Configuración de la barbería actualizada.');
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        $rules = [
            'business_name' => ['required', 'string', 'min:2', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:180'],
            'tax_id' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phones' => ['array', 'max:5'],
            'phones.*' => ['nullable', 'string', 'max:30'],
            'general_schedule' => ['required', 'array'],
            'currency_code' => ['required', Rule::in(['MXN', 'USD', 'CAD', 'EUR'])],
            'currency_symbol' => ['required', 'string', 'max:8'],
            'tax_name' => ['required', 'string', 'max:40'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'prices_include_tax' => ['boolean'],
            'ticket_header' => ['nullable', 'string', 'max:180'],
            'ticket_footer' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'default_appointment_duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'arrival_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'cancellation_notice_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'minimum_booking_notice_minutes' => ['required', 'integer', 'min:0', 'max:43200'],
            'maximum_booking_advance_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        foreach (array_keys(BusinessSetting::DAYS) as $day) {
            $rules["general_schedule.{$day}.enabled"] = ['required', 'boolean'];
            $rules["general_schedule.{$day}.start"] = ['required', 'date_format:H:i'];
            $rules["general_schedule.{$day}.end"] = ['required', 'date_format:H:i'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function validationAttributes(): array
    {
        return [
            'business_name' => 'nombre comercial',
            'legal_name' => 'razón social',
            'tax_id' => 'identificación fiscal',
            'address' => 'dirección',
            'phones' => 'teléfonos',
            'currency_code' => 'moneda',
            'currency_symbol' => 'símbolo monetario',
            'tax_name' => 'nombre del impuesto',
            'tax_rate' => 'porcentaje de impuesto',
            'ticket_header' => 'encabezado del ticket',
            'ticket_footer' => 'pie del ticket',
            'timezone' => 'zona horaria',
            'default_appointment_duration_minutes' => 'duración predeterminada',
            'arrival_tolerance_minutes' => 'tolerancia de llegada',
            'cancellation_notice_hours' => 'anticipación de cancelación',
            'minimum_booking_notice_minutes' => 'anticipación mínima para reservar',
            'maximum_booking_advance_days' => 'anticipación máxima para reservar',
            'logo' => 'logotipo',
        ];
    }

    private function validateSchedules(): void
    {
        $errors = [];

        foreach (BusinessSetting::DAYS as $day => $label) {
            $schedule = $this->general_schedule[$day];

            if ($schedule['enabled'] && $schedule['end'] <= $schedule['start']) {
                $errors["general_schedule.{$day}.end"] = "La hora de cierre del {$label} debe ser posterior a la apertura.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function fillFromSettings(): void
    {
        $this->business_name = $this->settings->business_name;
        $this->legal_name = $this->settings->legal_name;
        $this->tax_id = $this->settings->tax_id;
        $this->address = $this->settings->address;
        $this->phones = $this->settings->phones ?: [''];
        $this->general_schedule = array_replace_recursive(
            BusinessSetting::defaults()['general_schedule'],
            $this->settings->general_schedule ?: [],
        );
        $this->currency_code = $this->settings->currency_code;
        $this->currency_symbol = $this->settings->currency_symbol;
        $this->tax_name = $this->settings->tax_name;
        $this->tax_rate = (string) $this->settings->tax_rate;
        $this->prices_include_tax = $this->settings->prices_include_tax;
        $this->ticket_header = $this->settings->ticket_header;
        $this->ticket_footer = $this->settings->ticket_footer;
        $this->timezone = $this->settings->timezone;
        $this->default_appointment_duration_minutes = $this->settings->default_appointment_duration_minutes;
        $this->arrival_tolerance_minutes = $this->settings->arrival_tolerance_minutes;
        $this->cancellation_notice_hours = $this->settings->cancellation_notice_hours;
        $this->minimum_booking_notice_minutes = $this->settings->minimum_booking_notice_minutes;
        $this->maximum_booking_advance_days = $this->settings->maximum_booking_advance_days;
    }

    public function render(): View
    {
        return view('livewire.settings.business', [
            'days' => BusinessSetting::DAYS,
            'timezones' => DateTimeZone::listIdentifiers(),
            'currencies' => [
                'MXN' => ['name' => 'Peso mexicano', 'symbol' => '$'],
                'USD' => ['name' => 'Dólar estadounidense', 'symbol' => 'US$'],
                'CAD' => ['name' => 'Dólar canadiense', 'symbol' => 'C$'],
                'EUR' => ['name' => 'Euro', 'symbol' => '€'],
            ],
        ])->layout('layouts.app');
    }
}
