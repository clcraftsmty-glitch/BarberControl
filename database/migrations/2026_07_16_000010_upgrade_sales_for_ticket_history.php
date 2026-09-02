<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();
        });

        DB::table('document_sequences')->insert([
            'name' => 'sales',
            'current_value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('sales', function (Blueprint $table): void {
            $table->unsignedBigInteger('folio_number')->nullable()->unique()->after('id');
            $table->string('folio', 24)->nullable()->unique()->after('folio_number');
            $table->string('status', 20)->default('completada')->after('folio');
            $table->string('client_name_snapshot')->nullable()->after('created_by');
            $table->string('client_phone_snapshot', 40)->nullable()->after('client_name_snapshot');
            $table->string('barber_name_snapshot')->nullable()->after('client_phone_snapshot');
            $table->string('service_name_snapshot')->nullable()->after('barber_name_snapshot');
            $table->text('service_description_snapshot')->nullable()->after('service_name_snapshot');
            $table->unsignedInteger('service_duration_minutes_snapshot')->nullable()->after('service_description_snapshot');
            $table->decimal('unit_price_snapshot', 10, 2)->nullable()->after('service_duration_minutes_snapshot');
            $table->decimal('commission_percentage_snapshot', 5, 2)->nullable()->after('unit_price_snapshot');
            $table->dateTime('cancelled_at')->nullable()->after('commission_percentage_snapshot');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            $table->dateTime('refunded_at')->nullable()->after('cancellation_reason');
            $table->foreignId('refunded_by')->nullable()->after('refunded_at')->constrained('users')->nullOnDelete();
            $table->text('refund_reason')->nullable()->after('refunded_by');
            $table->decimal('refunded_amount', 10, 2)->default(0)->after('refund_reason');

            $table->index(['status', 'paid_at']);
            $table->index('client_phone_snapshot');
        });

        $sales = DB::table('sales')
            ->leftJoin('clients', 'clients.id', '=', 'sales.client_id')
            ->leftJoin('barbers', 'barbers.id', '=', 'sales.barber_id')
            ->leftJoin('services', 'services.id', '=', 'sales.service_id')
            ->leftJoin('commissions', 'commissions.sale_id', '=', 'sales.id')
            ->orderBy('sales.id')
            ->select([
                'sales.id',
                'sales.total',
                'clients.first_name',
                'clients.last_name',
                'clients.phone',
                'barbers.display_name',
                'services.name as service_name',
                'services.description as service_description',
                'services.duration_minutes',
                'commissions.percentage as commission_percentage',
            ])
            ->get();

        foreach ($sales as $index => $sale) {
            $number = $index + 1;
            DB::table('sales')->where('id', $sale->id)->update([
                'folio_number' => $number,
                'folio' => sprintf('V-%08d', $number),
                'client_name_snapshot' => trim(($sale->first_name ?? '').' '.($sale->last_name ?? '')),
                'client_phone_snapshot' => $sale->phone,
                'barber_name_snapshot' => $sale->display_name,
                'service_name_snapshot' => $sale->service_name,
                'service_description_snapshot' => $sale->service_description,
                'service_duration_minutes_snapshot' => $sale->duration_minutes,
                'unit_price_snapshot' => $sale->total,
                'commission_percentage_snapshot' => $sale->commission_percentage,
            ]);
        }

        DB::table('document_sequences')->where('name', 'sales')->update([
            'current_value' => $sales->count(),
            'updated_at' => now(),
        ]);

        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->dropUnique(['sale_id']);
            $table->index('sale_id');
        });

        Schema::create('sale_ticket_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('action', 20);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sale_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_ticket_logs');

        Schema::table('cash_movements', function (Blueprint $table): void {
            $table->dropIndex(['sale_id']);
            $table->unique('sale_id');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(['status', 'paid_at']);
            $table->dropIndex(['client_phone_snapshot']);
            $table->dropForeign(['cancelled_by']);
            $table->dropForeign(['refunded_by']);
            $table->dropColumn([
                'folio_number',
                'folio',
                'status',
                'client_name_snapshot',
                'client_phone_snapshot',
                'barber_name_snapshot',
                'service_name_snapshot',
                'service_description_snapshot',
                'service_duration_minutes_snapshot',
                'unit_price_snapshot',
                'commission_percentage_snapshot',
                'cancelled_at',
                'cancelled_by',
                'cancellation_reason',
                'refunded_at',
                'refunded_by',
                'refund_reason',
                'refunded_amount',
            ]);
        });

        Schema::dropIfExists('document_sequences');
    }
};
