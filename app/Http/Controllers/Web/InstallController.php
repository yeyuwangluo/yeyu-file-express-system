<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\InstallationState;
use App\Support\SystemInstaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class InstallController extends Controller
{
    public function __construct(
        private InstallationState $installation,
        private SystemInstaller $installer,
    ) {
    }

    public function show(): Response|RedirectResponse
    {
        if ($this->installation->isInstalled()) {
            return redirect('/admin-lite');
        }

        return $this->render();
    }

    public function store(Request $request): Response|RedirectResponse
    {
        if ($this->installation->isInstalled()) {
            return redirect('/admin-lite');
        }

        $validator = Validator::make($request->all(), $this->rules($request), [], $this->attributes());

        if ($validator->fails()) {
            return $this->render($request->all(), $validator->errors()->toArray(), 422);
        }

        try {
            $this->installer->install($validator->validated());
        } catch (Throwable $exception) {
            report($exception);

            return $this->render($request->all(), [
                'install' => [$exception->getMessage()],
            ], 500);
        }

        return redirect('/admin-lite');
    }

    public function success(): Response|RedirectResponse
    {
        if ($this->installation->isInstalled()) {
            return redirect('/admin-lite');
        }

        return response()->view('install.success', [
            'appName' => config('app.name', '叶宇文件快递'),
            'appUrl' => config('app.url', url('/')),
            'dbType' => config('database.default') === 'mysql' ? 'MySQL' : 'SQLite',
            'dbPath' => config('database.default') === 'mysql'
                ? config('database.connections.mysql.host') . ':' . config('database.connections.mysql.port') . '/' . config('database.connections.mysql.database')
                : config('database.connections.sqlite.database'),
            'adminEmail' => env('ADMIN_EMAIL', 'admin@example.com'),
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
        ]);
    }

    private function render(array $input = [], array $formErrors = [], int $status = 200): Response
    {
        return response()->view('install.show', [
            'checks' => $this->installation->checks(),
            'input' => array_merge($this->defaults(), $input),
            'formErrors' => $formErrors,
        ], $status);
    }

    private function defaults(): array
    {
        return [
            'app_name' => config('app.name', '叶宇文件快递'),
            'app_url' => config('app.url', url('/')),
            'db_connection' => config('database.default') === 'mysql' ? 'mysql' : 'sqlite',
            'sqlite_path' => 'database/database.sqlite',
            'db_host' => config('database.connections.mysql.host', '127.0.0.1'),
            'db_port' => config('database.connections.mysql.port', '3306'),
            'db_database' => config('database.connections.mysql.database', 'yeyu_file_express'),
            'db_username' => config('database.connections.mysql.username', 'root'),
            'db_password' => '',
            'admin_name' => 'Administrator',
            'admin_email' => env('ADMIN_EMAIL', 'admin@example.com'),
        ];
    }

    private function rules(Request $request): array
    {
        $rules = [
            'app_name' => ['required', 'string', 'max:80'],
            'app_url' => ['required', 'url', 'max:255'],
            'db_connection' => ['required', Rule::in(['sqlite', 'mysql'])],
            'admin_name' => ['required', 'string', 'max:80'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        if ($request->input('db_connection') === 'mysql') {
            $rules += [
                'db_host' => ['required', 'string', 'max:255'],
                'db_port' => ['required', 'integer', 'min:1', 'max:65535'],
                'db_database' => ['required', 'string', 'max:128'],
                'db_username' => ['required', 'string', 'max:128'],
                'db_password' => ['nullable', 'string', 'max:255'],
            ];
        } else {
            $rules['sqlite_path'] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    private function attributes(): array
    {
        return [
            'app_name' => '站点名称',
            'app_url' => '站点地址',
            'db_connection' => '数据库类型',
            'sqlite_path' => 'SQLite 文件',
            'db_host' => '数据库主机',
            'db_port' => '数据库端口',
            'db_database' => '数据库名',
            'db_username' => '数据库用户名',
            'db_password' => '数据库密码',
            'admin_name' => '管理员名称',
            'admin_email' => '管理员邮箱',
            'admin_password' => '管理员密码',
        ];
    }
}
