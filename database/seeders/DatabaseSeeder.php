<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        Setting::putDefault('upload', 'max_file_size', config('yeyu_file_express.upload.max_file_size'), 'int', '上传最大文件大小');
        Setting::putDefault('upload', 'default_expire_days', config('yeyu_file_express.upload.default_expire_days'), 'int', '默认过期天数');
        Setting::putDefault('upload', 'max_expire_days', config('yeyu_file_express.upload.max_expire_days'), 'int', '最大过期天数');
        Setting::putDefault('upload', 'allowed_file_types', config('yeyu_file_express.upload.allowed_file_types'), 'string', '允许文件扩展名');
        Setting::putDefault('geetest', 'enabled', config('yeyu_file_express.geetest.enabled'), 'bool', '验证码开关');
        Setting::putDefault('geetest', 'captcha_id', config('yeyu_file_express.geetest.captcha_id'), 'string', 'GeeTest captcha id');
        Setting::putDefault('footer', 'icp_beian', config('yeyu_file_express.footer.icp_beian'), 'string', 'ICP备案号');
        Setting::putDefault('footer', 'gongan_beian', config('yeyu_file_express.footer.gongan_beian'), 'string', '公安备案号');
        Setting::putDefault('footer', 'gongan_code', config('yeyu_file_express.footer.gongan_code'), 'string', '公安备案 code');
        Setting::putDefault('footer', 'links', config('yeyu_file_express.footer.links'), 'json', '页脚链接');

        foreach (config('yeyu_file_express.lan_transfer') as $key => $value) {
            Setting::putDefault('lan_transfer', $key, $value, is_bool($value) ? 'bool' : (is_int($value) ? 'int' : 'string'), '局域网互传配置');
        }

        foreach (config('yeyu_file_express.app_download') as $key => $value) {
            Setting::putDefault('app_download', $key, $value, is_array($value) ? 'json' : (is_bool($value) ? 'bool' : 'string'), 'App 下载页配置');
        }

        Announcement::query()->firstOrCreate(
            ['title' => '请注意'],
            [
                'content' => '不要上传违规文件
上传文件自动扫描病毒',
                'type' => 'warning',
                'priority' => 0,
                'is_active' => true,
            ],
        );

        $adminEmail = env('ADMIN_EMAIL');
        $adminPassword = env('ADMIN_PASSWORD');
        if ($adminEmail && $adminPassword) {
            User::query()->firstOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => 'Administrator',
                    'password' => Hash::make($adminPassword),
                    'is_admin' => true,
                    'status' => 'active',
                ],
            );
        }
    }
}
