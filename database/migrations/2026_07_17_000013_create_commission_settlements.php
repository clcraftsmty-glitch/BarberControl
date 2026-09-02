<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_settlements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('folio_number')->unique();
            $table->string('folio', 20)->unique();
            $table->foreignId('barber_id')->constrained()->restrictOnDelete();
            $table->string('period_type', 20);
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('commissions_total', 10, 2);
            $table->decimal('adjustments_total', 10, 2)->default(0);
            $table->decimal('total_paid', 10, 2);
            $table->string('payment_method', 30);
            $table->string('payment_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('paid_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['barber_id', 'paid_at']);
            $table->index(['period_start', 'period_end']);
        });

        Schema::table('commissions', function (Blueprint $table): void {
            $table->foreignId('commission_settlement_id')
                ->nullable()
                ->after('barber_id')
                ->constrained()
                ->restrictOnDelete();
            $table->dateTime('paid_at')->nullable()->after('status');
            $table->foreignId('paid_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            $table->index(['barber_id', 'status']);
        });

        Schema::create('commission_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('barber_id')->constrained()->restrictOnDelete();
            $table->foreignId('commission_settlement_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->decimal('amount', 10, 2);
            $table->text('reason');
            $table->string('status', 20)->default('pendiente');
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('authorized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['barber_id', 'status']);
            $table->index(['commission_settlement_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_adjustments');

        Schema::table('commissions', function (Blueprint $table): void {
            $table->dropIndex(['barber_id', 'status']);
            $table->dropConstrainedForeignId('paid_by');
            $table->dropConstrainedForeignId('commission_settlement_id');
            $table->dropColumn('paid_at');
        });

        Schema::dropIfExists('commission_settlements');
    }
};
