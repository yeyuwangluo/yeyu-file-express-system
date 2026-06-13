<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class LanTransferController extends Controller
{
    public function createTransfer(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => 'required|in:text,file',
                'content' => 'required_if:type,text|string|max:10000',
                'fileName' => 'required_if:type,file|string|max:255',
                'fileSize' => 'required_if:type,file|integer|min:1',
                'fileData' => 'required_if:type,file|string', // Base64编码的文件内容
            ]);

            $code = $this->generateCode();
            $expiresAt = Carbon::now()->addMinutes(5);
            $senderIp = $request->ip();

            $data = [
                'transfer_code' => $code,
                'sender_ip' => $senderIp,
                'transfer_type' => $validated['type'],
                'status' => 'waiting',
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($validated['type'] === 'text') {
                $data['text_content'] = $validated['content'];
            } else {
                $data['file_name'] = $validated['fileName'];
                $data['file_size'] = $validated['fileSize'];
                
                // 存储文件内容
                $fileContent = base64_decode($validated['fileData']);
                $fileName = $validated['fileName'];
                $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                $storedFileName = 'lan_transfer_' . $code . '.' . $extension;
                
                // 确保存储目录存在
                $storagePath = storage_path('app/public/lan_transfers');
                if (!file_exists($storagePath)) {
                    mkdir($storagePath, 0755, true);
                }
                
                // 存储文件
                $fullPath = $storagePath . '/' . $storedFileName;
                file_put_contents($fullPath, $fileContent);
                
                $data['file_path'] = $storedFileName;
            }

            $result = \DB::table('lan_transfers')->insertGetId($data);

            return response()->json([
                'success' => true,
                'message' => '传输创建成功',
                'data' => [
                    'transferId' => $result,
                    'transferCode' => $code,
                    'type' => $validated['type'],
                    'expiresAt' => $expiresAt->toIso8601String(),
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '创建传输失败: ' . $e->getMessage()
            ], 500);
        }
    }

    public function joinTransfer(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|size:6',
            ]);

            $transfer = \DB::table('lan_transfers')
                ->where('transfer_code', $validated['code'])
                ->where('status', 'waiting')
                ->where('expires_at', '>', now())
                ->first();

            if (!$transfer) {
                return response()->json([
                    'success' => false,
                    'message' => '传输不存在或已过期'
                ], 404);
            }

            $receiverIp = $request->ip();
            
            \DB::table('lan_transfers')
                ->where('transfer_code', $validated['code'])
                ->update([
                    'receiver_ip' => $receiverIp,
                    'status' => 'connected',
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => '加入传输成功',
                'data' => [
                    'transferId' => $transfer->id,
                    'transferCode' => $transfer->transfer_code,
                    'type' => $transfer->transfer_type,
                    'senderIp' => $transfer->sender_ip,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '加入传输失败: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getTransferInfo(Request $request, string $code): JsonResponse
    {
        try {
            $transfer = \DB::table('lan_transfers')
                ->where('transfer_code', $code)
                ->where('expires_at', '>', now())
                ->first();

            if (!$transfer) {
                return response()->json([
                    'success' => false,
                    'message' => '传输不存在或已过期'
                ], 404);
            }

            $data = [
                'transferId' => $transfer->id,
                'transferCode' => $transfer->transfer_code,
                'type' => $transfer->transfer_type,
                'status' => $transfer->status,
                'senderIp' => $transfer->sender_ip,
                'receiverIp' => $transfer->receiver_ip,
                'expiresAt' => $transfer->expires_at,
            ];

            if ($transfer->transfer_type === 'text') {
                $data['content'] = $transfer->text_content;
            } else {
                $data['fileName'] = $transfer->file_name;
                $data['fileSize'] = $transfer->file_size;
                $data['downloadUrl'] = '/api/v1/lan-transfer/download/' . $transfer->transfer_code;
            }

            return response()->json([
                'success' => true,
                'message' => '获取信息成功',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取信息失败: ' . $e->getMessage()
            ], 500);
        }
    }

    public function downloadFile(Request $request, string $code)
    {
        try {
            $transfer = \DB::table('lan_transfers')
                ->where('transfer_code', $code)
                ->where('transfer_type', 'file')
                ->where('status', 'connected')
                ->first();

            if (!$transfer || !$transfer->file_path) {
                return response('文件不存在', 404);
            }

            $filePath = storage_path('app/public/lan_transfers/' . $transfer->file_path);
            
            if (!file_exists($filePath)) {
                return response('文件不存在', 404);
            }

            return response()->download($filePath, $transfer->file_name);

        } catch (\Exception $e) {
            return response('下载失败: ' . $e->getMessage(), 500);
        }
    }

    public function completeTransfer(Request $request, string $code): JsonResponse
    {
        try {
            $transfer = \DB::table('lan_transfers')
                ->where('transfer_code', $code)
                ->first();

            if (!$transfer) {
                return response()->json([
                    'success' => false,
                    'message' => '传输不存在'
                ], 404);
            }

            \DB::table('lan_transfers')
                ->where('transfer_code', $code)
                ->update([
                    'status' => 'completed',
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => '传输完成'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '完成传输失败: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cancelTransfer(Request $request, string $code): JsonResponse
    {
        try {
            $transfer = \DB::table('lan_transfers')
                ->where('transfer_code', $code)
                ->first();

            if (!$transfer) {
                return response()->json([
                    'success' => false,
                    'message' => '传输不存在'
                ], 404);
            }

            // 删除文件
            if ($transfer->transfer_type === 'file' && $transfer->file_path) {
                $filePath = storage_path('app/public/lan_transfers/' . $transfer->file_path);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            \DB::table('lan_transfers')
                ->where('transfer_code', $code)
                ->update([
                    'status' => 'cancelled',
                    'updated_at' => now()
                ]);

            return response()->json([
                'success' => true,
                'message' => '传输已取消'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '取消传输失败: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateCode(): string
    {
        do {
            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (\DB::table('lan_transfers')->where('transfer_code', $code)->exists());

        return $code;
    }
}

