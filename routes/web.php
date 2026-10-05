<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->to('/admin');
});

Route::get('/login', function () {
    return redirect()->to('/admin/login');
})->name('login');

Route::middleware(['auth:web,member'])->group(function (): void {
    Route::get('/reports/condolences/matrix', [ReportController::class, 'matrix'])
        ->name('reports.condolences-matrix');
    Route::get('/reports/condolences/matrix.csv', [ReportController::class, 'matrixCsv'])
        ->name('reports.condolences-matrix-csv');
    Route::get('/reports/condolence/{condolence}.csv', [ReportController::class, 'condolenceCsv'])
        ->name('reports.condolence-csv');
    Route::get('/reports/condolence/{condolence}', [ReportController::class, 'condolence'])
        ->name('reports.condolence');
    Route::get('/reports/member/{member}/statement', [ReportController::class, 'memberStatement'])
        ->name('reports.member-statement');
});
