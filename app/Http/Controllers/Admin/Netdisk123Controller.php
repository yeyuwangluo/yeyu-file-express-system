<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Netdisk123Service;
use App\Support\XiaoxinFileExpressSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class Netdisk123Controller extends Controller
{
    public function index()
    {
        return view('admin.netdisk123', [
            'netdisk123' => XiaoxinFileExpressSettings::netdisk123(),
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
            'cookie' => 'nullable|string',
            'token' => 'nullable|string',
            'username' => 'nullable|string',
            'max_file_size' => 'required|integer|min:1',
            'auto_share' => 'required|boolean',
            'share_expire_days' => 'required|integer|min:1|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        Setting::setValue('netdisk123', 'enabled', $request->input('enabled'), 'bool');
        Setting::setValue('netdisk123', 'cookie', $request->input('cookie', ''), 'string');
        Setting::setValue('netdisk123', 'token', $request->input('token', ''), 'string');
        Setting::setValue('netdisk123', 'username', $request->input('username', ''), 'string');
        Setting::setValue('netdisk123', 'max_file_size', $request->input('max_file_size'), 'int');
        Setting::setValue('netdisk123', 'auto_share', $request->input('auto_share'), 'bool');
        Setting::setValue('netdisk123', 'share_expire_days', $request->input('share_expire_days'), 'int');

        return response()->json([
            'success' => true,
            'message' => '123网盘配置已更新'
        ]);
    }

    public function test()
    {
        $service = new Netdisk123Service();
        $result = $service->testConnection();

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => '123网盘连接测试成功'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => '123网盘连接测试失败，请检查配置'
        ], 400);
    }
}
