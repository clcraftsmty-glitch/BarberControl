<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->decimal('opening_amount', 10, 2);
            $table->decimal('expected_cash', 10, 2)->nullable();
            $table->decimal('actual_cash', 10, 2)->nullable();
            $table->decimal('difference', 10, 2)->nullable();
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->string('status', 20)->default('abierta');
            $table->string('open_guard', 20)->nullable()->unique();
            $table->timestamps();

            $table->index(['status', 'opened_at']);
        });

        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->foreignId('cash_register_session_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cash_register_session_id');
            $table->foreignId('sale_id')->nullable(false)->change();
        });

        Schema::dropIfExists('cash_register_sessions');
    }
};
