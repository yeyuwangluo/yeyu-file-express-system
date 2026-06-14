<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\BlockedIp;
use App\Models\ChunkedUpload;
use App\Models\FileDownload;
use App\Models\FileUpload;
use App\Jobs\SendSystemAlert;
use App\Models\LanSession;
use App\Models\LanSignal;
use App\Models\SharedFile;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class YeyuFileExpressApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_and_announcements_use_compatible_envelopes(): void
    {
        Announcement::query()->create([
            'title' => '请注意',
            'content' => '不要上传违规文件
上传文件自动扫描病毒',
            'type' => 'warning',
            'priority' => 10,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/config')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.upload.maxFileSize', 52_428_800)
            ->assertJsonPath('data.geetest.enabled', false);

        $this->getJson('/api/v1/announcements')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.0.title', '请注意');
    }

    public function test_upload_share_info_history_and_download_flow(): void
    {
        Storage::fake('local');

        $upload = $this->post('/api/v1/files', [
            'file' => UploadedFile::fake()->createWithContent('hello.txt', 'hello Yeyu File Express'),
            'expireDays' => 1,
            'extractCode' => '1234',
        ]);

        $upload->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.originalName', 'hello.txt')
            ->assertJsonPath('data.hasExtractCode', true);

        $code = $upload->json('data.code');
        $file = SharedFile::query()->where('code', $code)->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $this->assertSame('skipped', $file->scan_status);
        $this->assertGreaterThanOrEqual(0, $file->risk_score);

        $this->getJson("/api/v1/files/{$code}")
            ->assertOk()
            ->assertJsonPath('data.code', $code)
            ->assertJsonMissingPath('data.path')
            ->assertJsonMissingPath('data.extract_code_hash');

        $this->get("/api/v1/files/{$code}/download?extractCode=bad")
            ->assertForbidden();

        $this->get("/api/v1/files/{$code}/download?extractCode=1234")
            ->assertOk()
            ->assertHeader('content-type', 'text/plain; charset=UTF-8');

        $this->postJson('/api/v1/files/history-sync', ['codes' => [$code, 'NOFILE']])
            ->assertOk()
            ->assertJsonPath('data.list.0.exists', true)
            ->assertJsonPath('data.list.1.exists', false);
    }

    public function test_lan_text_session_and_signaling_flow(): void
    {
        $created = $this->postJson('/api/v1/lan/sessions', [
            'transferKind' => 'text',
            'textTitle' => '备忘',
            'textContent' => "第一行\n第二行",
        ]);

        $created->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.transferKind', 'text')
            ->assertJsonPath('data.textLineCount', 2);

        $sessionId = $created->json('data.sessionId');
        $shortCode = $created->json('data.shortCode');

        $this->postJson('/api/v1/lan/sessions/join', ['shortCode' => $shortCode])
            ->assertOk()
            ->assertJsonPath('data.receiverJoined', true);

        $this->getJson("/api/v1/lan/sessions/{$sessionId}/text")
            ->assertOk()
            ->assertJsonPath('data.title', '备忘');

        $this->postJson("/api/v1/lan/sessions/{$sessionId}/offer", ['sdp' => 'demo-offer'])
            ->assertOk();

        $this->getJson("/api/v1/lan/sessions/{$sessionId}/offer")
            ->assertOk()
            ->assertJsonPath('data.sdp', 'demo-offer');

        $this->postJson("/api/v1/lan/sessions/{$sessionId}/ice", ['role' => 'sender', 'candidate' => 'candidate-1'])
            ->assertOk()
            ->assertJsonPath('data.sequence', 1);

        $this->getJson("/api/v1/lan/sessions/{$sessionId}/ice?afterSequence=0")
            ->assertOk()
            ->assertJsonPath('data.0.candidate', 'candidate-1');

        $this->postJson("/api/v1/lan/sessions/{$sessionId}/ice", [
            'role' => 'receiver',
            'candidates' => [
                ['candidate' => 'candidate-2'],
                ['candidate' => 'candidate-3'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.0.sequence', 2)
            ->assertJsonPath('data.1.sequence', 3);

        $this->getJson("/api/v1/lan/sessions/{$sessionId}/ice?afterSequence=1&role=receiver")
            ->assertOk()
            ->assertJsonPath('data.items.0.candidate', 'candidate-2')
            ->assertJsonPath('data.nextAfterSequence', 3);

        $this->assertDatabaseHas(LanSession::class, ['session_id' => $sessionId, 'receiver_joined' => true]);
        $this->assertSame(4, LanSignal::query()->where('lan_session_id', LanSession::query()->where('session_id', $sessionId)->value('id'))->count());
    }

    public function test_lan_file_session_rejects_limit_overflow(): void
    {
        Setting::query()->updateOrCreate(['group' => 'lan_transfer', 'key' => 'max_file_count'], ['value' => '1', 'type' => 'int']);
        Setting::query()->updateOrCreate(['group' => 'lan_transfer', 'key' => 'max_file_size'], ['value' => '10', 'type' => 'int']);

        $this->postJson('/api/v1/lan/sessions', [
            'files' => [
                ['fileName' => 'a.txt', 'fileSize' => 1],
                ['fileName' => 'b.txt', 'fileSize' => 1],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('message', '文件数量超过限制');

        $this->postJson('/api/v1/lan/sessions', [
            'files' => [
                ['fileName' => 'a.txt', 'fileSize' => 11],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('message', '单个文件大小超过限制');
    }

    public function test_chunked_upload_flow_creates_downloadable_file(): void
    {
        Storage::fake('local');
        Queue::fake();

        $init = $this->postJson('/api/v1/files/chunked/init', [
            'originalName' => 'big.txt',
            'mimeType' => 'text/plain',
            'totalSize' => 11,
            'chunkSize' => 6,
            'totalChunks' => 2,
            'expireDays' => 1,
        ]);

        $init->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.totalChunks', 2);

        $uploadId = $init->json('data.uploadId');

        $this->post('/api/v1/files/chunked/'.$uploadId.'/chunks', [
            'index' => 0,
            'chunk' => UploadedFile::fake()->createWithContent('0.part', 'hello '),
        ])->assertOk()
            ->assertJsonPath('data.receivedChunks', 1);

        $this->post('/api/v1/files/chunked/'.$uploadId.'/chunks', [
            'index' => 1,
            'chunk' => UploadedFile::fake()->createWithContent('1.part', 'world'),
        ])->assertOk()
            ->assertJsonPath('data.complete', true);

        $complete = $this->postJson('/api/v1/files/chunked/'.$uploadId.'/complete');
        $complete->assertOk()
            ->assertJsonPath('data.originalName', 'big.txt');

        $code = $complete->json('data.code');
        $file = SharedFile::query()->where('code', $code)->firstOrFail();
        Storage::disk('local')->assertExists($file->path);
        $this->assertSame('completed', ChunkedUpload::query()->where('upload_id', $uploadId)->value('status'));
        $this->assertSame(11, FileUpload::query()->where('file_id', $file->id)->value('size'));
        $this->assertSame('hello world', Storage::disk('local')->get($file->path));
    }

    public function test_app_download_endpoint_redirects_to_configured_url(): void
    {
        Setting::query()->updateOrCreate(['group' => 'app_download', 'key' => 'enabled'], ['value' => '1', 'type' => 'bool']);
        Setting::query()->updateOrCreate(['group' => 'app_download', 'key' => 'android_enabled'], ['value' => '1', 'type' => 'bool']);
        Setting::query()->updateOrCreate(['group' => 'app_download', 'key' => 'android_download_url'], ['value' => 'https://example.com/yeyu-file-express.apk', 'type' => 'string']);

        $this->get('/api/v1/app/download/android')
            ->assertRedirect('https://example.com/yeyu-file-express.apk');

        $this->get('/api/v1/app/download/ios')
            ->assertNotFound();
    }

    public function test_public_pages_and_not_found_render(): void
    {
        $this->get('/')->assertOk()->assertSee('叶宇文件快递', false);
        $this->get('/terms')->assertOk()->assertSee('叶宇文件快递', false);
        $this->get('/status')->assertOk()->assertSee('叶宇文件快递', false);
        $this->get('/lan-transfer')->assertOk()->assertSee('叶宇文件快递', false);
        $this->get('/app')->assertOk()->assertSee('叶宇文件快递', false);
        $this->get('/missing-page')->assertNotFound()->assertSee('页面不存在');
    }

    public function test_uninstalled_system_redirects_to_unified_installer(): void
    {
        config(['yeyu_file_express.installer.installed' => false]);

        $this->get('/')->assertRedirect('/install');

        $this->get('/install')
            ->assertOk()
            ->assertSee('fe-page fe-install', false)
            ->assertSee('系统安装', false)
            ->assertSee('环境检查', false);

        $this->getJson('/api/v1/config')
            ->assertStatus(503)
            ->assertJsonPath('message', '系统尚未安装')
            ->assertJsonPath('data.installUrl', url('/install'));
    }

    public function test_installer_can_initialize_sqlite_system(): void
    {
        $basePath = storage_path('framework/testing-installer/'.uniqid('install_', true));
        \Illuminate\Support\Facades\File::ensureDirectoryExists($basePath);

        config([
            'yeyu_file_express.installer.installed' => false,
            'yeyu_file_express.installer.env_path' => $basePath.'/.env',
            'yeyu_file_express.installer.marker_path' => $basePath.'/installed.json',
        ]);

        $this->post('/install', [
            'app_name' => '叶宇文件快递测试',
            'app_url' => 'http://example.test',
            'db_connection' => 'sqlite',
            'sqlite_path' => $basePath.'/database.sqlite',
            'admin_name' => 'Owner',
            'admin_email' => 'owner@example.com',
            'admin_password' => 'owner-secret',
            'admin_password_confirmation' => 'owner-secret',
        ])->assertRedirect('/');

        $this->assertFileExists($basePath.'/.env');
        $this->assertFileExists($basePath.'/installed.json');
        $this->assertStringContainsString('YEYU_FILE_EXPRESS_INSTALLED=true', (string) file_get_contents($basePath.'/.env'));
        $this->assertDatabaseHas('users', ['email' => 'owner@example.com', 'is_admin' => true, 'role' => 'owner']);
        $this->assertDatabaseHas('settings', ['group' => 'upload', 'key' => 'max_file_size']);

        \Illuminate\Support\Facades\File::deleteDirectory($basePath);
    }

    public function test_admin_can_manage_announcements_settings_blocks_and_audit_logs(): void
    {
        $headers = $this->adminHeaders();

        $this->get('/admin-lite')->assertUnauthorized();

        $this->post('/admin-lite/settings', [
            'max_file_size' => 12_345,
            'default_expire_days' => 2,
            'max_expire_days' => 9,
            'allowed_file_types' => 'txt,png',
            'risk_block_score' => 88,
            'chunked_upload_enabled' => '1',
            'chunked_upload_max_chunk_size' => 2048,
            'chunked_upload_max_chunks' => 99,
            'chunked_upload_ttl_minutes' => 30,
            'virus_scan_enabled' => '1',
            'virus_scan_clamav_path' => 'clamscan-test',
            'virus_scan_timeout_seconds' => 11,
            'geetest_enabled' => '1',
            'geetest_captcha_id' => 'captcha-demo',
            'footer_icp_beian' => 'ICP备案测试',
            'footer_gongan_beian' => '公安备案测试',
            'footer_gongan_code' => '123456',
            'footer_links' => "帮助中心|/help|1|30\n隐藏链接|/hidden|0|40",
            'lan_enabled' => '1',
            'lan_max_file_size' => 1024,
            'lan_max_file_count' => 3,
            'lan_max_total_size' => 4096,
            'lan_expire_minutes' => 5,
            'lan_text_enabled' => '1',
            'lan_text_max_length' => 500,
            'lan_text_max_lines' => 20,
            'lan_text_retention_minutes' => 60,
            'app_enabled' => '1',
            'app_title' => '叶宇文件快递 App',
            'app_subtitle' => '测试副标题',
            'app_description' => '测试描述',
            'app_features' => "快速上传\n扫码获取",
            'app_android_enabled' => '1',
            'app_android_download_url' => 'https://example.com/app.apk',
            'app_android_version' => '1.0.0',
            'app_ios_enabled' => '1',
            'app_ios_download_url' => 'https://example.com/ios',
            'app_ios_version' => '1.0.0',
            'app_qrcode_enabled' => '1',
        ], $headers)->assertRedirect();

        $this->getJson('/api/v1/config')
            ->assertJsonPath('data.upload.maxFileSize', 12_345)
            ->assertJsonPath('data.chunkedUpload.maxChunkSize', 2048)
            ->assertJsonPath('data.risk.blockScore', 88)
            ->assertJsonPath('data.virusScan.clamavPath', 'clamscan-test')
            ->assertJsonPath('data.geetest.enabled', true)
            ->assertJsonPath('data.footer.icpBeian', 'ICP备案测试')
            ->assertJsonPath('data.footer.links.0.text', '帮助中心')
            ->assertJsonPath('data.footer.items.2.text', '帮助中心');

        $created = $this->post('/admin-lite/announcements', [
            'title' => '后台公告',
            'content' => '后台公告内容',
            'type' => 'info',
            'priority' => 8,
            'is_active' => '1',
        ], $headers);
        $created->assertRedirect();

        $announcement = Announcement::query()->where('title', '后台公告')->firstOrFail();
        $this->put("/admin-lite/announcements/{$announcement->id}", [
            'title' => '后台公告更新',
            'content' => '后台公告内容',
            'type' => 'warning',
            'priority' => 9,
            'is_active' => '1',
        ], $headers)->assertRedirect();

        $this->post('/admin-lite/blocked-ips', [
            'ip' => '192.0.2.10',
            'scope' => 'download',
            'reason' => '测试',
        ], $headers)->assertRedirect();

        $blockedIp = BlockedIp::query()->where('ip', '192.0.2.10')->firstOrFail();
        $this->delete("/admin-lite/blocked-ips/{$blockedIp->id}", [], $headers)->assertRedirect();

        $this->post('/admin-lite/users', [
            'name' => 'Viewer',
            'email' => 'viewer@example.com',
            'password' => 'viewer-secret',
            'role' => 'viewer',
            'permissions' => '',
        ], $headers)->assertRedirect();

        $viewer = User::query()->where('email', 'viewer@example.com')->firstOrFail();
        $this->put("/admin-lite/users/{$viewer->id}", [
            'name' => 'Viewer User',
            'role' => 'admin',
            'status' => 'active',
            'permissions' => 'admins.manage',
            'password' => '',
        ], $headers)->assertRedirect();

        $this->delete("/admin-lite/users/{$viewer->id}", [], $headers)->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.update']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'announcement.update']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'blocked_ip.delete']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin_user.update']);
    }

    public function test_admin_dashboard_filters_password_change_and_logout(): void
    {
        $headers = $this->adminHeaders();

        SharedFile::query()->create([
            'code' => 'FINDME',
            'original_name' => 'report.pdf',
            'stored_name' => 'report.pdf',
            'disk' => 'local',
            'path' => 'uploads/report.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 1024,
            'status' => 'active',
            'expires_at' => now()->addDay(),
            'uploaded_at' => now(),
            'uploader_ip' => '198.51.100.12',
        ]);

        $this->get('/admin-lite?q=report&ip=198.51.100&status=active&expires=active', $headers)
            ->assertOk()
            ->assertSee('report.pdf')
            ->assertSee('最近 7 天趋势')
            ->assertSee('最近健康检查');

        $this->post('/admin-lite/password', [
            'current_password' => 'change-me-now',
            'password' => 'new-secret',
            'password_confirmation' => 'new-secret',
        ], $headers)->assertRedirect();

        $hash = Setting::valueFor('admin', 'password_hash');
        $this->assertIsString($hash);
        $this->assertTrue(Hash::check('new-secret', $hash));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.password.update']);

        $this->get('/admin-lite/logout', $headers)->assertUnauthorized();
    }

    public function test_admin_login_failures_are_throttled(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables([
                'PHP_AUTH_USER' => 'admin@example.com',
                'PHP_AUTH_PW' => 'wrong-password',
                'REMOTE_ADDR' => '203.0.113.5',
            ])->get('/admin-lite')->assertUnauthorized();
        }

        $this->withServerVariables([
            'PHP_AUTH_USER' => 'admin@example.com',
            'PHP_AUTH_PW' => 'wrong-password',
            'REMOTE_ADDR' => '203.0.113.5',
        ])->get('/admin-lite')->assertTooManyRequests();
    }

    public function test_admin_routes_use_unified_php80_compatible_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin-lite');
        $this->get('/admin/login')->assertRedirect('/admin-lite');

        $this->get('/admin-lite', $this->adminHeaders())
            ->assertOk()
            ->assertSee('叶宇文件快递后台', false)
            ->assertSee('兼容 PHP 8.0+', false);
    }

    public function test_admin_basic_auth_records_last_login(): void
    {
        putenv('ADMIN_EMAIL=admin@example.com');
        putenv('ADMIN_PASSWORD=change-me-now');
        $_ENV['ADMIN_EMAIL'] = 'admin@example.com';
        $_ENV['ADMIN_PASSWORD'] = 'change-me-now';

        User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'change-me-now',
            'is_admin' => true,
            'status' => 'active',
        ]);

        $this->withServerVariables([
            'PHP_AUTH_USER' => 'admin@example.com',
            'PHP_AUTH_PW' => 'change-me-now',
            'REMOTE_ADDR' => '203.0.113.20',
        ])->get('/admin-lite')->assertOk();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $this->assertNotNull($admin->last_login_at);
        $this->assertSame('203.0.113.20', $admin->last_login_ip);

        User::query()->create([
            'name' => 'Second Admin',
            'email' => 'second-admin@example.com',
            'password' => 'second-secret',
            'is_admin' => true,
            'role' => 'viewer',
            'status' => 'active',
        ]);

        $this->withServerVariables([
            'PHP_AUTH_USER' => 'second-admin@example.com',
            'PHP_AUTH_PW' => 'second-secret',
            'REMOTE_ADDR' => '203.0.113.21',
        ])->get('/admin-lite')->assertOk();

        $secondAdmin = User::query()->where('email', 'second-admin@example.com')->firstOrFail();
        $this->assertSame('203.0.113.21', $secondAdmin->last_login_ip);
    }

    public function test_expired_file_cleanup_and_operational_commands(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('uploads/expired.txt', 'expired');

        $file = SharedFile::query()->create([
            'code' => 'ABC234',
            'original_name' => 'expired.txt',
            'stored_name' => 'expired.txt',
            'disk' => 'local',
            'path' => 'uploads/expired.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 7,
            'status' => 'active',
            'expires_at' => now()->subMinute(),
            'uploaded_at' => now()->subDay(),
        ]);

        $this->artisan('yeyu-file-express:cleanup-expired-files')->assertSuccessful();
        $this->assertDatabaseHas('files', ['id' => $file->id, 'status' => 'expired']);

        FileUpload::query()->create([
            'original_name' => 'old.txt',
            'size' => 1,
            'success' => true,
            'created_at' => now()->subDays(10),
        ]);
        FileDownload::query()->create([
            'code' => 'ABC234',
            'success' => true,
            'bytes' => 1,
            'created_at' => now()->subDays(10),
        ]);

        Bus::fake();
        config(['yeyu_file_express.storage_limit' => 1]);

        $this->artisan('yeyu-file-express:check-storage')->assertSuccessful();
        Bus::assertDispatched(SendSystemAlert::class);
        $this->artisan('yeyu-file-express:daily-stats')->assertSuccessful();
        $this->artisan('yeyu-file-express:prune-logs', ['--days' => 1])->assertSuccessful();
        $this->artisan('yeyu-file-express:backup-config')->assertSuccessful();
        $this->artisan('yeyu-file-express:backup-database');

        $this->assertDatabaseCount('daily_stats', 1);
        $this->assertDatabaseMissing('file_uploads', ['original_name' => 'old.txt']);
        $this->assertNotEmpty(Storage::disk('local')->files('backups/config'));
    }

    private function adminHeaders(): array
    {
        $_ENV['ADMIN_EMAIL'] = 'admin@example.com';

        return [
            'X-Test-Admin' => '1',
        ];
    }
}
