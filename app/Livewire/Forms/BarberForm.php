<?php

namespace App\Livewire\Forms;

use App\Enums\UserRole;
use App\Models\Barber;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Form;

class BarberForm extends Form
{
    public ?Barber $barber = null;

    public ?int $user_id = null;

    public string $user_mode = 'new';

    public string $user_name = '';

    public string $user_email = '';

    public string $user_password = '';

    public string $user_password_confirmation = '';

    public string $display_name = '';

    public string $phone = '';

    public string $default_commission_percentage = '0';

    public array $work_schedule = [];

    public array $service_ids = [];

    public bool $is_active = true;

    public function rules(): array
    {
        $usesExistingUser = $this->barber || $this->user_mode === 'existing';

        return [
            'user_mode' => [$this->barber ? 'nullable' : 'required', Rule::in(['new', 'existing'])],
            'user_id' => [
                $usesExistingUser ? 'required' : 'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Barber->value),
                Rule::unique('barbers', 'user_id')->ignore($this->barber?->id),
            ],
            'user_name' => [$usesExistingUser ? 'nullable' : 'required', 'string', 'max:255'],
            'user_email' => [$usesExistingUser ? 'nullable' : 'required', 'string', 'lowercase', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'user_password' => [$usesExistingUser ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'display_name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'min:7', 'max:25', 'regex:/^[0-9+() .\-]+$/'],
            'default_commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'work_schedule' => ['required', 'array', 'size:7'],
            'work_schedule.*.enabled' => ['required', 'boolean'],
            'work_schedule.*.start' => ['nullable', 'date_format:H:i'],
            'work_schedule.*.end' => ['nullable', 'date_format:H:i'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'distinct', 'exists:services,id'],
            'is_active' => ['boolean'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'user_id' => 'usuario',
            'user_mode' => 'tipo de cuenta',
            'user_name' => 'nombre de la cuenta',
            'user_email' => 'correo de acceso',
            'user_password' => 'contraseña',
            'display_name' => 'nombre visible',
            'phone' => 'teléfono',
            'default_commission_percentage' => 'comisión predeterminada',
            'work_schedule' => 'horario laboral',
            'service_ids' => 'servicios',
            'is_active' => 'estado',
        ];
    }

    public function setDefaults(): void
    {
        $this->work_schedule = collect(Barber::DAYS)->map(fn (string $label, string $day): array => [
            'enabled' => ! in_array($day, ['sunday'], true),
            'start' => $day !== 'sunday' ? '09:00' : null,
            'end' => $day !== 'sunday' ? '18:00' : null,
        ])->all();
    }

    public function setBarber(Barber $barber): void
    {
        $this->barber = $barber;
        $this->user_mode = 'existing';
        $this->user_id = $barber->user_id;
        $this->display_name = $barber->display_name;
        $this->phone = $barber->phone;
        $this->default_commission_percentage = $barber->default_commission_percentage;
        $this->work_schedule = $barber->work_schedule;
        $this->service_ids = $barber->services()->pluck('services.id')->all();
        $this->is_active = $barber->is_active;
    }

    public function store(): Barber
    {
        [$profile, $serviceIds, $account] = $this->validatedData();

        return DB::transaction(function () use ($profile, $serviceIds, $account): Barber {
            if ($account !== null) {
                $user = User::query()->create($account);
                $user->forceFill(['email_verified_at' => now()])->save();
                $profile['user_id'] = $user->id;
            }

            $barber = Barber::query()->create($profile);
            $barber->services()->sync($serviceIds);

            return $barber->load(['user:id,name,email', 'services:id,name']);
        });
    }

    public function update(): Barber
    {
        [$profile, $serviceIds] = $this->validatedData();

        return DB::transaction(function () use ($profile, $serviceIds): Barber {
            $this->barber->update($profile);
            $this->barber->services()->sync($serviceIds);

            return $this->barber->refresh()->load(['user:id,name,email', 'services:id,name']);
        });
    }

    private function validatedData(): array
    {
        if (! $this->barber && $this->user_mode === 'new') {
            $this->user_email = mb_strtolower(trim($this->user_email));
        }

        $data = $this->validate();
        $errors = [];

        foreach (Barber::DAYS as $day => $label) {
            $schedule = $data['work_schedule'][$day] ?? [];

            if (! ($schedule['enabled'] ?? false)) {
                $data['work_schedule'][$day] = ['enabled' => false, 'start' => null, 'end' => null];

                continue;
            }

            if (blank($schedule['start'] ?? null)) {
                $errors["form.work_schedule.{$day}.start"] = "La hora inicial de {$label} es obligatoria.";
            }

            if (blank($schedule['end'] ?? null)) {
                $errors["form.work_schedule.{$day}.end"] = "La hora final de {$label} es obligatoria.";
            } elseif (filled($schedule['start'] ?? null) && $schedule['end'] <= $schedule['start']) {
                $errors["form.work_schedule.{$day}.end"] = "La hora final de {$label} debe ser posterior a la inicial.";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $account = $this->barber || $data['user_mode'] === 'existing' ? null : [
            'name' => trim($data['user_name']),
            'email' => mb_strtolower(trim($data['user_email'])),
            'password' => Hash::make($data['user_password']),
            'role' => UserRole::Barber,
        ];

        return [[
            'user_id' => $data['user_id'],
            'display_name' => trim($data['display_name']),
            'phone' => trim($data['phone']),
            'default_commission_percentage' => $data['default_commission_percentage'],
            'work_schedule' => $data['work_schedule'],
            'is_active' => $data['is_active'],
        ], array_map('intval', $data['service_ids']), $account];
    }
}
