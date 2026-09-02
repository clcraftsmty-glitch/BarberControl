<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('business_name', 150);
            $table->string('legal_name', 180)->nullable();
            $table->string('tax_id', 40)->nullable();
            $table->string('logo_path')->nullable();
            $table->text('address')->nullable();
            $table->json('phones');
            $table->json('general_schedule');
            $table->char('currency_code', 3)->default('MXN');
            $table->string('currency_symbol', 8)->default('$');
            $table->string('tax_name', 40)->default('IVA');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('prices_include_tax')->default(true);
            $table->string('ticket_header', 180)->nullable();
            $table->text('ticket_footer')->nullable();
            $table->string('timezone', 80)->default('America/Mexico_City');
            $table->unsignedSmallInteger('default_appointment_duration_minutes')->default(30);
            $table->unsignedSmallInteger('arrival_tolerance_minutes')->default(10);
            $table->unsignedSmallInteger('cancellation_notice_hours')->default(2);
            $table->unsignedSmallInteger('minimum_booking_notice_minutes')->default(0);
            $table->unsignedSmallInteger('maximum_booking_advance_days')->default(3650);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $schedule = collect(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
            ->mapWithKeys(fn (string $day): array => [$day => [
                'enabled' => true,
                'start' => '00:00',
                'end' => '23:59',
            ]])
            ->all();

        DB::table('business_settings')->insert([
            'id' => 1,
            'business_name' => 'BarberControl',
            'phones' => json_encode([], JSON_THROW_ON_ERROR),
            'general_schedule' => json_encode($schedule, JSON_THROW_ON_ERROR),
            'currency_code' => 'MXN',
            'currency_symbol' => '$',
            'tax_name' => 'IVA',
            'tax_rate' => 0,
            'prices_include_tax' => true,
            'ticket_header' => 'Gestión profesional',
            'ticket_footer' => 'Gracias por tu visita',
            'timezone' => 'America/Mexico_City',
            'default_appointment_duration_minutes' => 30,
            'arrival_tolerance_minutes' => 10,
            'cancellation_notice_hours' => 2,
            'minimum_booking_notice_minutes' => 0,
            'maximum_booking_advance_days' => 3650,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
