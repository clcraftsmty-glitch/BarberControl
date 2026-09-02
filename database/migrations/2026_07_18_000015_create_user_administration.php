<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('role')->index();
            $table->json('permissions')->nullable()->after('is_active');
            $table->dateTime('suspended_at')->nullable()->after('permissions');
            $table->foreignId('suspended_by')->nullable()->after('suspended_at')->constrained('users')->nullOnDelete();
            $table->text('suspension_reason')->nullable()->after('suspended_by');
            $table->dateTime('last_login_at')->nullable()->after('remember_token');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });

        Schema::create('user_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email', 255)->nullable();
            $table->string('event', 40);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('details', 500)->nullable();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['event', 'occurred_at']);
            $table->index(['email', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_access_logs');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'is_active',
                'permissions',
                'suspended_at',
                'suspension_reason',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
