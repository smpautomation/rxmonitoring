<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\RxMonitoringController;
use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Route::get('/', function () {
//     return Inertia::render('RxMonitoring/Index');
// })->name('rx-monitoring.');

Route::get('/', [RxMonitoringController::class, 'index'])->name('rx-monitoring.index');
Route::prefix('rx-monitoring')->name('rx-monitoring.')->group(function () {
    //Route::get('/', [RxMonitoringController::class, 'index'])->name('index');

    Route::post('/chambers', [RxMonitoringController::class, 'store'])->name('chambers.store');
    Route::patch('/chambers/{chamber}/oven', [RxMonitoringController::class, 'setOven'])->name('chambers.oven');
    Route::patch('/chambers/{chamber}/start', [RxMonitoringController::class, 'start'])->name('chambers.start');
    Route::patch('/chambers/{chamber}/peak', [RxMonitoringController::class, 'peak'])->name('chambers.peak');
    Route::patch('/chambers/{chamber}/stop', [RxMonitoringController::class, 'stop'])->name('chambers.stop');
    Route::patch('/chambers/{chamber}/cooling-start', [RxMonitoringController::class, 'coolingStart'])->name('chambers.cooling-start');
    Route::patch('/chambers/{chamber}/cooling-end', [RxMonitoringController::class, 'coolingEnd'])->name('chambers.cooling-end');
    Route::patch('/chambers/{chamber}/close', [RxMonitoringController::class, 'close'])->name('chambers.close');

    Route::put('/chambers/{chamber}/layers/{layer}', [RxMonitoringController::class, 'storeLayer'])->name('layers.store');
    Route::delete('/chambers/{chamber}/layers/{layer}', [RxMonitoringController::class, 'clearLayer'])->name('layers.clear');

    // Inline scan previews (plain JSON, not Inertia)
    Route::post('/scan/personnel', [ScanController::class, 'personnel'])->name('scan.personnel');
    Route::post('/scan/work-order', [ScanController::class, 'workOrder'])->name('scan.work-order');

    // Export / reporting screen (successor to the legacy frmExport.vb)
    Route::get('/export', [ExportController::class, 'index'])->name('export.index');
    Route::get('/export/download', [ExportController::class, 'download'])->name('export.download');
});
