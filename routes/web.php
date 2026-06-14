<?php

use App\Http\Controllers\Web\AdminLiteController;
use App\Http\Controllers\Web\SharePageController;
use App\Http\Controllers\Web\StaticPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (StaticPageController $controller) => $controller('home'))->name('home');
Route::get('/terms', function () {
    $terms = \App\Models\Setting::valueFor('content', 'terms');
    $privacy = \App\Models\Setting::valueFor('content', 'privacy');
    return response()->view('terms', [
        'termsContent' => blank($terms) ? \App\Http\Controllers\Web\AdminLiteController::defaultTermsContent() : $terms,
        'privacyContent' => blank($privacy) ? \App\Http\Controllers\Web\AdminLiteController::defaultPrivacyContent() : $privacy,
    ]);
})->name('terms');
Route::get('/status', fn (StaticPageController $controller) => $controller('status'))->name('status');
Route::get('/lan-transfer', fn (StaticPageController $controller) => $controller('lan-transfer'))->name('lan-transfer');
Route::get('/app', fn (StaticPageController $controller) => $controller('app'))->name('app');
Route::get('/app/download/{platform}', [\App\Http\Controllers\Api\SystemController::class, 'appDownload'])
    ->where('platform', 'android|ios')
    ->name('app.download');
Route::get('/qr', [StaticPageController::class, 'qr'])->name('qr');

Route::get('/files/{code}', [SharePageController::class, 'show'])
    ->whereAlphaNumeric('code')
    ->name('files.show');
Route::get('/my-files', [SharePageController::class, 'myFiles'])->name('files.mine');
Route::get('/batch/{token}', [SharePageController::class, 'showBatch'])
    ->where('token', '[A-Za-z0-9]{10,32}')
    ->name('files.batch');
Route::get('/batch/{token}/download', [SharePageController::class, 'downloadBatch'])
    ->where('token', '[A-Za-z0-9]{10,32}')
    ->name('files.batch.download');

Route::prefix('admin-lite')->name('admin-lite.')->group(function (): void {
    Route::get('/login', [AdminLiteController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AdminLiteController::class, 'processLogin'])->name('login.submit');
});

Route::prefix('admin-lite')->middleware('admin.basic')->name('admin-lite.')->group(function (): void {
    Route::get('/', [AdminLiteController::class, 'dashboard'])->name('dashboard');
    Route::get('/dashboard', [AdminLiteController::class, 'dashboard'])->name('dashboard.full');
    Route::post('/logout', [AdminLiteController::class, 'logout'])->name('logout');
    Route::post('/settings', [AdminLiteController::class, 'updateSettings'])->name('settings.update');
    Route::post('/ops-alert/test', [AdminLiteController::class, 'testOpsAlert'])->name('ops-alert.test');
    Route::get('/audit/export', [AdminLiteController::class, 'exportAuditLogs'])->name('audit.export');
    Route::post('/appeals/review', [AdminLiteController::class, 'reviewAppeal'])->name('appeals.review');
    Route::post('/password', [AdminLiteController::class, 'updatePassword'])->name('password.update');
    Route::post('/users', [AdminLiteController::class, 'storeAdminUser'])->name('users.store');
    Route::get('/users/{user}', [AdminLiteController::class, 'editAdminUser'])->name('users.edit');
    Route::put('/users/{user}', [AdminLiteController::class, 'updateAdminUser'])->name('users.update');
    Route::delete('/users/{user}', [AdminLiteController::class, 'deleteAdminUser'])->name('users.delete');
    Route::post('/announcements', [AdminLiteController::class, 'storeAnnouncement'])->name('announcements.store');
    Route::put('/announcements/{announcement}', [AdminLiteController::class, 'updateAnnouncement'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [AdminLiteController::class, 'deleteAnnouncement'])->name('announcements.delete');
    Route::post('/blocked-ips', [AdminLiteController::class, 'storeBlockedIp'])->name('blocked-ips.store');
    Route::post('/blocked-ips/{blockedIp}/unblock', [AdminLiteController::class, 'deleteBlockedIp'])->name('blocked-ips.unblock');
    Route::delete('/blocked-ips/{blockedIp}', [AdminLiteController::class, 'deleteBlockedIp'])->name('blocked-ips.delete');
    Route::delete('/files/{file}', [AdminLiteController::class, 'deleteFile'])->name('files.delete');
    Route::post('/files/{file}/block', [AdminLiteController::class, 'blockFile'])->name('files.block');
    Route::post('/files/{file}/extend', [AdminLiteController::class, 'extendFile'])->name('files.extend');
    Route::post('/files/{file}/rescan', [AdminLiteController::class, 'rescanFile'])->name('files.rescan');
    Route::get('/files/{file}/source', [AdminLiteController::class, 'sourceFile'])->name('files.source');
    Route::get('/files/{file}/risk-report', [AdminLiteController::class, 'exportRiskReport'])->name('files.risk-report');
    Route::post('/files/{file}/risk-review', [AdminLiteController::class, 'reviewRiskFile'])->name('files.risk-review');
    Route::post('/files/risk-review/bulk', [AdminLiteController::class, 'bulkReviewRiskFiles'])->name('files.risk-review.bulk');
    Route::post('/batch-shares/{token}/close', [AdminLiteController::class, 'closeBatchShare'])->where('token', '[A-Za-z0-9]{10,32}')->name('batch-shares.close');
    Route::post('/files/bulk', [AdminLiteController::class, 'bulkFiles'])->name('files.bulk');
    Route::post('/failed-jobs/{failedJobId}/retry-scan', [AdminLiteController::class, 'retryFailedScan'])->whereNumber('failedJobId')->name('failed-jobs.retry-scan');
    Route::get('/ai-settings', [AdminLiteController::class, 'aiSettings'])->name('ai-settings');
    Route::post('/ai-settings/update', [AdminLiteController::class, 'updateAiSettings'])->name('update-ai-settings');
    Route::post('/ai-settings/test', [AdminLiteController::class, 'testAiConnection'])->name('ai-settings.test');

    Route::post('/content', [AdminLiteController::class, 'updateContent'])->name('content.update');
    Route::get('/oss', function () {
        return view('admin.oss', [
            'settings' => \App\Support\YeyuFileExpressSettings::configPayload(),
        ]);
    })->name('oss');
    Route::get('/netdisk123', [\App\Http\Controllers\Admin\Netdisk123Controller::class, 'index'])->name('netdisk123');
    Route::post('/netdisk123', [\App\Http\Controllers\Admin\Netdisk123Controller::class, 'update']);
    Route::get('/netdisk123/test', [\App\Http\Controllers\Admin\Netdisk123Controller::class, 'test'])->name('netdisk123.test');
    Route::post('/maintenance/cleanup-lan', [AdminLiteController::class, 'runLanCleanup'])->name('maintenance.cleanup-lan');
    Route::post('/maintenance/prune-logs', [AdminLiteController::class, 'runLogPrune'])->name('maintenance.prune-logs');
    Route::post('/maintenance/ops-check', [AdminLiteController::class, 'runOpsCheck'])->name('maintenance.ops-check');
});

Route::redirect('/admin', '/admin-lite')->name('admin.redirect');
Route::redirect('/admin/login', '/admin-lite/login')->name('admin.login.redirect');

if (config('app.debug')) {
    Route::get('/test-simple', function () {
        return 'Test simple route works!';
    });

    Route::get('/test-install', function() { return response()->json(["installed" => app(App\Support\InstallationState::class)->isInstalled()]); });

    Route::get('/lan-simple', function () { return File::get(public_path('lan-simple.html')); })->name('lan-simple');
}

Route::get('/files/{code}/threat-details', [SharePageController::class, 'showThreatDetails'])
    ->whereAlphaNumeric('code')
    ->name('files.threat-details');
Route::get('/appeals/{lookupCode}', [SharePageController::class, 'appealStatus'])
    ->where('lookupCode', '[A-Z0-9]{8,20}')
    ->name('files.appeal-status');
Route::post('/files/{code}/appeal', [SharePageController::class, 'submitAppeal'])
    ->whereAlphaNumeric('code')
    ->name('files.appeal');
