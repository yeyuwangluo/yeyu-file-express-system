<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class VirusScanController extends Controller
{
    public function index(): JsonResponse
    {
        $scanLogs = DB::table('files')
            ->select([
                'id',
                'original_name',
                'size',
                'scan_status',
                'scan_result',
                'scan_checked_at',
                'uploaded_at'
            ])
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($file) {
                return [
                    'id' => $file->id,
                    'file_name' => $file->original_name,
                    'file_size' => $file->size,
                    'scan_status' => $file->scan_status,
                    'scan_result' => $file->scan_result,
                    'scan_time' => $file->scan_checked_at,
                    'upload_time' => $file->uploaded_at,
                    'is_clean' => $file->scan_status === 'clean',
                    'is_infected' => $file->scan_status === 'infected',
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $scanLogs
        ]);
    }

    public function stats(): JsonResponse
    {
        $total = DB::table('files')->count();
        $clean = DB::table('files')->where('scan_status', 'clean')->count();
        $infected = DB::table('files')->where('scan_status', 'infected')->count();
        $pending = DB::table('files')->where('scan_status', 'pending')->count();
        $scanning = DB::table('files')->where('scan_status', 'scanning')->count();
        $error = DB::table('files')->where('scan_status', 'error')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'clean' => $clean,
                'infected' => $infected,
                'pending' => $pending,
                'scanning' => $scanning,
                'error' => $error,
                'clean_rate' => $total > 0 ? round(($clean / $total) * 100, 2) : 0,
                'engine_version' => '1.4.4',
                'signature_count' => 3627865,
            ]
        ]);
    }
}

