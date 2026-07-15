<?php

namespace App\Livewire\Forms;

use App\Enums\UserRole;
use App\Models\Client;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ClientForm extends Form
{
    public ?Client $client = null;

    public string $first_name = '';

    public string $last_name = '';

    public string $phone = '';

    public ?string $email = null;

    public ?string $birth_date = null;

    public ?int $preferred_barber_id = null;

    public ?string $notes = null;

    public bool $is_active = true;

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'min:7', 'max:25', 'regex:/^[0-9+() .\-]+$/'],
            'email' => ['nullable', 'email:rfc', 'max:255', Rule::unique('clients', 'email')->ignore($this->client?->id)],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'preferred_barber_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', UserRole::Barber->value),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'first_name' => 'nombre',
            'last_name' => 'apellidos',
            'phone' => 'teléfono',
            'email' => 'correo electrónico',
            'birth_date' => 'fecha de nacimiento',
            'preferred_barber_id' => 'barbero preferido',
            'notes' => 'notas',
            'is_active' => 'estado',
        ];
    }

    public function setClient(Client $client): void
    {
        $this->client = $client;
        $this->first_name = $client->first_name;
        $this->last_name = $client->last_name;
        $this->phone = $client->phone;
        $this->email = $client->email;
        $this->birth_date = $client->birth_date?->format('Y-m-d');
        $this->preferred_barber_id = $client->preferred_barber_id;
        $this->notes = $client->notes;
        $this->is_active = $client->is_active;
    }

    public function store(): Client
    {
        return Client::query()->create($this->validatedData());
    }

    public function update(): Client
    {
        $this->client->update($this->validatedData());

        return $this->client->refresh();
    }

    private function validatedData(): array
    {
        $data = $this->validate();

        return [
            ...$data,
            'first_name' => trim($data['first_name']),
            'last_name' => trim($data['last_name']),
            'phone' => trim($data['phone']),
            'email' => filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null,
            'birth_date' => filled($data['birth_date'] ?? null) ? $data['birth_date'] : null,
            'preferred_barber_id' => $data['preferred_barber_id'] ?: null,
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
        ];
    }
}
