<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->boolean('whatsapp_opt_in')->default(false)->after('phone');
            $table->dateTime('whatsapp_opt_in_at')->nullable()->after('whatsapp_opt_in');
            $table->dateTime('whatsapp_opt_out_at')->nullable()->after('whatsapp_opt_in_at');
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('template_name', 120);
            $table->string('deduplication_key')->unique();
            $table->string('recipient', 40);
            $table->json('payload');
            $table->string('status', 20)->default('pendiente');
            $table->string('provider_message_id')->nullable()->unique();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['client_id', 'created_at']);
            $table->index(['appointment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn(['whatsapp_opt_in', 'whatsapp_opt_in_at', 'whatsapp_opt_out_at']);
        });
    }
};
