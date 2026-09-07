<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ManageController;
use App\Http\Controllers\OvenController;
use App\Http\Controllers\ProductModelController;
use App\Http\Controllers\RxMonitoringController;
use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

    // Back-office CRUD for the oven/model lookup tables. Not linked from
    // the main operator page - put this behind admin/supervisor auth
    // middleware, e.g. ->middleware('can:manage-rx-monitoring').
    Route::get('/manage', [ManageController::class, 'index'])->name('manage.index');

    Route::post('/areas', [AreaController::class, 'store'])->name('areas.store');
    Route::patch('/areas/{area}', [AreaController::class, 'update'])->name('areas.update');
    Route::delete('/areas/{area}', [AreaController::class, 'destroy'])->name('areas.destroy');

    Route::post('/ovens', [OvenController::class, 'store'])->name('ovens.store');
    Route::patch('/ovens/{oven}', [OvenController::class, 'update'])->name('ovens.update');
    Route::delete('/ovens/{oven}', [OvenController::class, 'destroy'])->name('ovens.destroy');

    Route::post('/product-models', [ProductModelController::class, 'store'])->name('product-models.store');
    Route::patch('/product-models/{productModel}', [ProductModelController::class, 'update'])->name('product-models.update');
    Route::delete('/product-models/{productModel}', [ProductModelController::class, 'destroy'])->name('product-models.destroy');
});
