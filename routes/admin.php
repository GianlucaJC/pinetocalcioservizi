<?php

use App\Http\Controllers\Admin\AdminAuthenticatedSessionController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\PayslipBatchController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin.guest')->group(function () {
    Route::get('/login', [AdminAuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AdminAuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('admin')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.employees.index'))->name('dashboard');
    Route::get('employees/{employee}/contract', [EmployeeController::class, 'contract'])->name('employees.contract');
    Route::post('employees/{employee}/send-credentials', [EmployeeController::class, 'sendCredentials'])->name('employees.send-credentials');
    Route::resource('employees', EmployeeController::class)->except('show');
    Route::get('payslips', [PayslipBatchController::class, 'index'])->name('payslips.index');
    Route::get('payslips/upload', [PayslipBatchController::class, 'create'])->name('payslips.create');
    Route::post('payslips', [PayslipBatchController::class, 'store'])->name('payslips.store');
    Route::get('payslips/{batch}', [PayslipBatchController::class, 'show'])->name('payslips.show');
    Route::patch('payslips/{batch}', [PayslipBatchController::class, 'update'])->name('payslips.update');
    Route::post('payslips/{batch}/publish', [PayslipBatchController::class, 'publish'])->name('payslips.publish');
    Route::post('payslips/{batch}/send-notification', [PayslipBatchController::class, 'sendNotification'])->name('payslips.send-notification');
    Route::delete('payslips/{batch}', [PayslipBatchController::class, 'destroy'])->name('payslips.destroy');
    Route::get('payslips/documents/{document}', [PayslipBatchController::class, 'document'])->name('payslips.documents.show');
    Route::get('cuds', [PayslipBatchController::class, 'index'])->name('cuds.index');
    Route::get('cuds/upload', [PayslipBatchController::class, 'create'])->name('cuds.create');
    Route::post('cuds', [PayslipBatchController::class, 'store'])->name('cuds.store');
    Route::get('cuds/{batch}', [PayslipBatchController::class, 'show'])->name('cuds.show');
    Route::patch('cuds/{batch}', [PayslipBatchController::class, 'update'])->name('cuds.update');
    Route::post('cuds/{batch}/publish', [PayslipBatchController::class, 'publish'])->name('cuds.publish');
    Route::post('cuds/{batch}/send-notification', [PayslipBatchController::class, 'sendNotification'])->name('cuds.send-notification');
    Route::delete('cuds/{batch}', [PayslipBatchController::class, 'destroy'])->name('cuds.destroy');
    Route::get('cuds/documents/{document}', [PayslipBatchController::class, 'document'])->name('cuds.documents.show');
    Route::post('/logout', [AdminAuthenticatedSessionController::class, 'destroy'])->name('logout');
});
