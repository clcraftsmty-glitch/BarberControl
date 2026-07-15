<?php

namespace App\Livewire\Forms;

use App\Models\Service;
use Illuminate\Validation\Rule;
use Livewire\Form;

class ServiceForm extends Form
{
    public ?Service $service = null;

    public string $name = '';

    public string $description = '';

    public int|string $duration_minutes = 30;

    public string $price = '';

    public string $commission_percentage = '0';

    public bool $is_active = true;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('services', 'name')->ignore($this->service?->id)],
            'description' => ['required', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'commission_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'name' => 'nombre',
            'description' => 'descripción',
            'duration_minutes' => 'duración',
            'price' => 'precio',
            'commission_percentage' => 'porcentaje de comisión',
            'is_active' => 'estado',
        ];
    }

    public function setService(Service $service): void
    {
        $this->service = $service;
        $this->name = $service->name;
        $this->description = $service->description;
        $this->duration_minutes = $service->duration_minutes;
        $this->price = $service->price;
        $this->commission_percentage = $service->commission_percentage;
        $this->is_active = $service->is_active;
    }

    public function store(): Service
    {
        return Service::query()->create($this->validatedData());
    }

    public function update(): Service
    {
        $this->service->update($this->validatedData());

        return $this->service->refresh();
    }

    private function validatedData(): array
    {
        $data = $this->validate();

        return [
            ...$data,
            'name' => trim($data['name']),
            'description' => trim($data['description']),
        ];
    }
}
