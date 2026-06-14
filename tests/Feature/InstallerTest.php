<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckBlockedIp;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_installer_can_initialize_sqlite_system(): void
    {
        $this->withoutMiddleware(CheckBlockedIp::class);

        $basePath = storage_path('framework/testing-installer/'.uniqid('install_', true));
        File::ensureDirectoryExists($basePath);

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
        ])->assertRedirect('/admin-lite');

        $this->assertFileExists($basePath.'/.env');
        $this->assertFileExists($basePath.'/installed.json');
        $this->assertStringContainsString('YEYU_FILE_EXPRESS_INSTALLED=true', (string) file_get_contents($basePath.'/.env'));
        $this->assertDatabaseHas('users', ['email' => 'owner@example.com', 'is_admin' => true, 'role' => 'owner']);
        $this->assertDatabaseHas('settings', ['group' => 'upload', 'key' => 'max_file_size']);
    }
}
