<?php

use App\Http\Controllers\Api\ChunkedUploadController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\LanSessionController;
use App\Http\Controllers\Api\SystemController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/config', [SystemController::class, 'config']);
    Route::get('/announcements', [SystemController::class, 'announcements']);
    Route::get('/status', [SystemController::class, 'status']);
    Route::get('/app/download-config', [SystemController::class, 'appDownloadConfig']);
    Route::get('/terms', [SystemController::class, 'termsContent']);
    Route::get('/app/download/{platform}', [SystemController::class, 'appDownload'])->where('platform', 'android|ios');

    Route::post('/files', [FileController::class, 'store'])->middleware('throttle:uploads');
    Route::post('/files/chunked/init', [ChunkedUploadController::class, 'init'])->middleware('throttle:uploads');
    Route::post('/files/chunked/{uploadId}/chunks', [ChunkedUploadController::class, 'chunk'])->where('uploadId', 'chunk_[A-Za-z0-9]{32}')->middleware('throttle:uploads');
    Route::post('/files/chunked/{uploadId}/complete', [ChunkedUploadController::class, 'complete'])->where('uploadId', 'chunk_[A-Za-z0-9]{32}')->middleware('throttle:uploads');
    Route::delete('/files/chunked/{uploadId}', [ChunkedUploadController::class, 'cancel'])->where('uploadId', 'chunk_[A-Za-z0-9]{32}');
    Route::post('/files/history-sync', [FileController::class, 'historySync'])->middleware('throttle:downloads');
    Route::post('/files/batch-share', [FileController::class, 'batchShare'])->middleware('throttle:downloads');
    Route::post('/files/batch-shares/owner', [FileController::class, 'ownerBatchShares'])->middleware('throttle:downloads');
    Route::post('/files/batch-shares/{token}/owner/update', [FileController::class, 'ownerBatchShareUpdate'])->where('token', '[A-Za-z0-9]{10,32}')->middleware('throttle:downloads');
    Route::post('/files/batch-shares/{token}/owner/extend', [FileController::class, 'ownerBatchShareExtend'])->where('token', '[A-Za-z0-9]{10,32}')->middleware('throttle:downloads');
    Route::post('/files/batch-shares/{token}/owner/close', [FileController::class, 'ownerBatchShareClose'])->where('token', '[A-Za-z0-9]{10,32}')->middleware('throttle:downloads');
    Route::post('/files/batch-owner/extend', [FileController::class, 'ownerBatchExtend'])->middleware('throttle:downloads');
    Route::post('/files/batch-owner/extract-code', [FileController::class, 'ownerBatchExtractCode'])->middleware('throttle:downloads');
    Route::get('/files/{code}', [FileController::class, 'show'])->whereAlphaNumeric('code');
    Route::get('/files/{code}/scan-status', [FileController::class, 'scanStatus'])->whereAlphaNumeric('code');
    Route::get('/files/{code}/thumbnail', [FileController::class, 'thumbnail'])
        ->whereAlphaNumeric('code')
        ->middleware('throttle:downloads')
        ->name('api.files.thumbnail');
    Route::post('/files/{code}/owner/extend', [FileController::class, 'ownerExtend'])->whereAlphaNumeric('code')->middleware('throttle:downloads');
    Route::post('/files/{code}/owner/extract-code', [FileController::class, 'ownerExtractCode'])->whereAlphaNumeric('code')->middleware('throttle:downloads');
    Route::post('/files/{code}/owner/meta', [FileController::class, 'ownerMeta'])->whereAlphaNumeric('code')->middleware('throttle:downloads');
    Route::post('/files/{code}/owner/delete', [FileController::class, 'ownerDelete'])->whereAlphaNumeric('code')->middleware('throttle:downloads');
    Route::get('/files/{code}/preview', [FileController::class, 'preview'])
        ->whereAlphaNumeric('code')
        ->middleware('throttle:downloads')
        ->name('api.files.preview');
    Route::get('/files/{code}/download', [FileController::class, 'download'])
        ->whereAlphaNumeric('code')
        ->middleware('throttle:downloads')
        ->name('api.files.download');

    Route::post('/lan/sessions', [LanSessionController::class, 'store']);
    Route::post('/lan/sessions/join', [LanSessionController::class, 'join']);
    Route::get('/lan/sessions/{id}', [LanSessionController::class, 'show'])->where('id', 'lan_[A-Za-z0-9]{16}');
    Route::delete('/lan/sessions/{id}', [LanSessionController::class, 'destroy'])->where('id', 'lan_[A-Za-z0-9]{16}');
    Route::get('/lan/sessions/{id}/text', [LanSessionController::class, 'text'])->where('id', 'lan_[A-Za-z0-9]{16}');
    Route::post('/lan/sessions/{id}/text/complete', [LanSessionController::class, 'completeText'])->where('id', 'lan_[A-Za-z0-9]{16}');
    Route::post('/lan/sessions/{id}/status', [LanSessionController::class, 'updateStatus'])->where('id', 'lan_[A-Za-z0-9]{16}');
    Route::post('/lan/sessions/{id}/complete', [LanSessionController::class, 'complete'])->where('id', 'lan_[A-Za-z0-9]{16}');
    Route::post('/lan/sessions/{id}/{type}', [LanSessionController::class, 'storeSignal'])->where(['id' => 'lan_[A-Za-z0-9]{16}', 'type' => 'offer|answer|ice']);
    Route::get('/lan/sessions/{id}/{type}', [LanSessionController::class, 'getSignal'])->where(['id' => 'lan_[A-Za-z0-9]{16}', 'type' => 'offer|answer|ice']);

    if (config('app.debug')) {
        Route::prefix('lan-transfer')->group(function (): void {
            Route::post('/create', [\App\Http\Controllers\Api\LanTransferController::class, 'createTransfer']);
            Route::post('/join', [\App\Http\Controllers\Api\LanTransferController::class, 'joinTransfer']);
            Route::get('/info/{code}', [\App\Http\Controllers\Api\LanTransferController::class, 'getTransferInfo']);
            Route::post('/complete/{code}', [\App\Http\Controllers\Api\LanTransferController::class, 'completeTransfer']);
            Route::post('/cancel/{code}', [\App\Http\Controllers\Api\LanTransferController::class, 'cancelTransfer']);
            Route::get('/download/{code}', [\App\Http\Controllers\Api\LanTransferController::class, 'downloadFile']);
        });
    }
});
