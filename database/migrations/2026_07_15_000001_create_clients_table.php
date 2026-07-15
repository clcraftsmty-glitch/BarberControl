<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->index();
            $table->string('phone', 25)->index();
            $table->string('email')->nullable()->unique();
            $table->date('birth_date')->nullable();
            $table->foreignId('preferred_barber_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
