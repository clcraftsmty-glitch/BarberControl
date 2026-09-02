<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('source', 20)->default('programada')->after('status')->index();
        });

        Schema::create('walk_in_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('preferred_barber_id')->nullable()->constrained('barbers')->nullOnDelete();
            $table->foreignId('assigned_barber_id')->nullable()->constrained('barbers')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('en_espera');
            $table->dateTime('arrived_at');
            $table->dateTime('called_at')->nullable();
            $table->dateTime('left_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'arrived_at']);
            $table->index(['preferred_barber_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walk_in_entries');

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn('source');
        });
    }
};
