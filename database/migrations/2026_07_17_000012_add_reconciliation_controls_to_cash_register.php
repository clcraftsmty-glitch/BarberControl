<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->string('category', 40)->default('otro')->after('type');
            $table->index(['cash_register_session_id', 'category']);
        });

        DB::table('cash_movements')
            ->whereNotNull('sale_id')
            ->where('type', 'ingreso')
            ->update(['category' => 'venta_servicio']);
        DB::table('cash_movements')
            ->whereNotNull('sale_id')
            ->where('type', 'gasto')
            ->update(['category' => 'devolucion']);
        DB::table('cash_movements')
            ->whereNull('sale_id')
            ->where('type', 'ingreso')
            ->update(['category' => 'ingreso_manual']);
        DB::table('cash_movements')
            ->whereNull('sale_id')
            ->where('type', 'gasto')
            ->update(['category' => 'gasto_operativo']);

        Schema::table('cash_register_sessions', function (Blueprint $table): void {
            $table->text('difference_reason')->nullable()->after('difference');
            $table->foreignId('difference_authorized_by')
                ->nullable()
                ->after('difference_reason')
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('difference_authorized_at')->nullable()->after('difference_authorized_by');
        });
    }

    public function down(): void
    {
        Schema::table('cash_register_sessions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('difference_authorized_by');
            $table->dropColumn(['difference_reason', 'difference_authorized_at']);
        });

        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->dropIndex(['cash_register_session_id', 'category']);
            $table->dropColumn('category');
        });
    }
};
