<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barber_service', function (Blueprint $table) {
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->primary(['barber_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barber_service');
    }
};
