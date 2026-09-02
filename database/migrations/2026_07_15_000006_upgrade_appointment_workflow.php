<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('appointments')
            ->where('status', 'en_proceso')
            ->update(['status' => 'en_servicio']);

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('barber_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);
            $table->string('payment_method', 30);
            $table->string('payment_reference', 120)->nullable();
            $table->dateTime('paid_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained()->restrictOnDelete();
            $table->string('type', 20)->default('ingreso');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 30);
            $table->string('description');
            $table->dateTime('occurred_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('commissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('barber_id')->constrained()->restrictOnDelete();
            $table->decimal('base_amount', 10, 2);
            $table->decimal('percentage', 5, 2);
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->default('pendiente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('sales');

        DB::table('appointments')
            ->where('status', 'en_servicio')
            ->update(['status' => 'en_proceso']);
    }
};
