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
            $table->foreignId('commission_settlement_id')
                ->nullable()
                ->unique()
                ->after('sale_id')
                ->constrained()
                ->restrictOnDelete();
        });

        DB::table('commission_settlements')
            ->orderBy('id')
            ->each(function (object $settlement): void {
                $existing = DB::table('cash_movements')
                    ->where('category', 'pago_comision')
                    ->where('description', 'like', '%'.$settlement->folio.'%')
                    ->first();

                if ($existing) {
                    DB::table('cash_movements')->where('id', $existing->id)->update([
                        'commission_settlement_id' => $settlement->id,
                        'updated_at' => now(),
                    ]);

                    return;
                }

                $session = DB::table('cash_register_sessions')
                    ->where('opened_at', '<=', $settlement->paid_at)
                    ->where(function ($query) use ($settlement): void {
                        $query->whereNull('closed_at')
                            ->orWhere('closed_at', '>=', $settlement->paid_at);
                    })
                    ->latest('opened_at')
                    ->first();

                DB::table('cash_movements')->insert([
                    'cash_register_session_id' => $session?->id,
                    'sale_id' => null,
                    'commission_settlement_id' => $settlement->id,
                    'type' => 'gasto',
                    'category' => 'pago_comision',
                    'amount' => $settlement->total_paid,
                    'payment_method' => $settlement->payment_method,
                    'description' => 'Pago de comisiones '.$settlement->folio.' (registro recuperado)',
                    'occurred_at' => $settlement->paid_at,
                    'created_by' => $settlement->created_by,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('cash_movements')
            ->whereNotNull('commission_settlement_id')
            ->where('description', 'like', '%(registro recuperado)%')
            ->delete();

        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('commission_settlement_id');
        });
    }
};
