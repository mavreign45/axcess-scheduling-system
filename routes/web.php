<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StandbyController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/schedule/month', [ScheduleController::class, 'month'])->name('schedule.month');
    Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::get('/schedule/create', [ScheduleController::class, 'create'])->name('schedule.create');
    Route::post('/schedule', [ScheduleController::class, 'store'])->name('schedule.store');
    Route::get('/schedule/{assignment}/edit', [ScheduleController::class, 'edit'])->name('schedule.edit');
    Route::put('/schedule/{assignment}', [ScheduleController::class, 'update'])->name('schedule.update');
    Route::delete('/schedule/{assignment}', [ScheduleController::class, 'destroy'])->name('schedule.destroy');
    Route::post('/schedule/{assignment}/replacement', [ScheduleController::class, 'replace'])->name('schedule.replace');

    Route::middleware('can:view-staff-directory')->group(function (): void {
        Route::resource('staff', StaffController::class);
    });

    Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
    Route::get('/leave/create', [LeaveController::class, 'create'])->name('leave.create');
    Route::post('/leave', [LeaveController::class, 'store'])->name('leave.store');
    Route::get('/leave/{leave}', [LeaveController::class, 'show'])->name('leave.show');
    Route::post('/leave/{leave}/approve', [LeaveController::class, 'approve'])->name('leave.approve');
    Route::post('/leave/{leave}/reject', [LeaveController::class, 'reject'])->name('leave.reject');
    Route::post('/leave/{leave}/cancel', [LeaveController::class, 'cancel'])->name('leave.cancel');

    Route::middleware('can:manage-operations')->group(function (): void {
        Route::get('/standby', [StandbyController::class, 'index'])->name('standby.index');
        Route::get('/standby/create', [StandbyController::class, 'create'])->name('standby.create');
        Route::post('/standby', [StandbyController::class, 'store'])->name('standby.store');
        Route::post('/standby/{assignment}/status', [StandbyController::class, 'updateStatus'])->name('standby.status');
        Route::delete('/standby/{assignment}', [StandbyController::class, 'destroy'])->name('standby.destroy');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('/reports/run-conflicts', [ReportController::class, 'runConflicts'])->name('reports.conflicts.run');
        Route::post('/reports/conflicts/{conflict}/resolve', [ReportController::class, 'resolveConflict'])->name('reports.conflicts.resolve');
    });

    Route::middleware('can:export-data')->prefix('exports')->name('exports.')->group(function (): void {
        Route::get('/schedule', [ExportController::class, 'schedule'])->name('schedule');
        Route::get('/staff', [ExportController::class, 'staff'])->name('staff');
        Route::get('/leave', [ExportController::class, 'leave'])->name('leave');
        Route::get('/reports', [ExportController::class, 'reports'])->name('reports');
    });
});
