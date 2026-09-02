<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable()->after('last_login_ip');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->dateTime('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->dateTime('last_two_factor_at')->nullable()->after('two_factor_confirmed_at');
        });

        Schema::create('database_backups', function (Blueprint $table): void {
            $table->id();
            $table->string('filename')->unique();
            $table->string('disk', 60);
            $table->string('path');
            $table->string('database_connection', 60);
            $table->string('database_driver', 30);
            $table->string('source_format', 20);
            $table->string('status', 20)->index();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->boolean('encrypted')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('auditable_type')->nullable();
            $table->string('auditable_id', 80)->nullable();
            $table->string('description', 500);
            $table->text('before_values')->nullable();
            $table->text('after_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->dateTime('occurred_at')->index();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('system_errors', function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->string('exception_class');
            $table->string('message', 1000);
            $table->string('route_name')->nullable();
            $table->string('request_method', 10)->nullable();
            $table->string('request_path', 500)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('occurrences')->default(1);
            $table->dateTime('first_occurred_at');
            $table->dateTime('last_occurred_at')->index();
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_errors');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('database_backups');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'last_two_factor_at',
            ]);
        });
    }
};
