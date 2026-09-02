<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\DatabaseBackup;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupSecurityTest extends TestCase
{
    use RefreshDatabase;

    private string $sqlitePath;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->sqlitePath = storage_path('framework/testing/security-restore.sqlite');
        @unlink($this->sqlitePath);
        file_put_contents($this->sqlitePath, '');
        config([
            'database.connections.security_restore' => [
                'driver' => 'sqlite',
                'database' => $this->sqlitePath,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'security.backups.disk' => 'local',
            'security.backups.directory' => 'backups-tests',
            'security.backups.encryption_key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('security_restore');
        @unlink($this->sqlitePath);
        parent::tearDown();
    }

    public function test_sqlite_backup_is_encrypted_verified_and_can_be_restored(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        DB::connection('security_restore')->statement('create table samples (id integer primary key, value text not null)');
        DB::connection('security_restore')->table('samples')->insert(['id' => 1, 'value' => 'valor original protegido']);

        $backup = app(DatabaseBackupService::class)->create($administrator, 'security_restore');
        $this->assertSame('completed', $backup->status);
        $this->assertNotNull($backup->sha256);
        Storage::disk('local')->assertExists($backup->path);
        $encrypted = Storage::disk('local')->get($backup->path);
        $this->assertStringStartsWith("BCBK1\0", $encrypted);
        $this->assertStringNotContainsString('valor original protegido', $encrypted);

        DB::connection('security_restore')->table('samples')->where('id', 1)->update(['value' => 'dato alterado']);
        app(DatabaseBackupService::class)->restore($backup);
        $this->assertSame('valor original protegido', DB::connection('security_restore')->table('samples')->value('value'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup_restored']);
    }

    public function test_only_administrator_can_open_center_and_download_verified_backup(): void
    {
        $administrator = User::factory()->create(['role' => UserRole::Administrator]);
        $receptionist = User::factory()->create(['role' => UserRole::Receptionist]);
        $contents = "BCBK1\0encrypted-test";
        Storage::disk('local')->put('backups-tests/test.sqlite.bcbk', $contents);
        $backup = DatabaseBackup::query()->create([
            'filename' => 'test.sqlite.bcbk', 'disk' => 'local', 'path' => 'backups-tests/test.sqlite.bcbk',
            'database_connection' => 'sqlite', 'database_driver' => 'sqlite', 'source_format' => 'sqlite',
            'status' => 'completed', 'size_bytes' => strlen($contents), 'sha256' => hash('sha256', $contents),
            'encrypted' => true, 'created_by' => $administrator->id, 'started_at' => now(), 'completed_at' => now(),
        ]);

        $this->actingAs($receptionist)->get(route('security.index'))->assertForbidden();
        $this->actingAs($administrator)->get(route('security.index'))->assertOk()->assertSee('Respaldos y seguridad');
        $response = $this->get(route('security.backups.download', $backup));
        $response->assertOk()->assertDownload($backup->filename);
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup_downloaded', 'actor_id' => $administrator->id]);
    }

    public function test_security_headers_are_added_to_web_responses(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
