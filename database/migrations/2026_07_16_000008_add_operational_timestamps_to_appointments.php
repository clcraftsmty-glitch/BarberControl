<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dateTime('arrived_at')->nullable()->after('ends_at');
            $table->dateTime('service_started_at')->nullable()->after('arrived_at');
            $table->dateTime('service_finished_at')->nullable()->after('service_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropColumn([
                'arrived_at',
                'service_started_at',
                'service_finished_at',
            ]);
        });
    }
};
