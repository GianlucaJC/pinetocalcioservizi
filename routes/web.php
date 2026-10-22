<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\EmployeeContractController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::redirect('/', '/login');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'password.changed', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'password.changed'])->group(function () {
    Route::get('/my-contract', EmployeeContractController::class)->name('contract.show');
    Route::get('/documents', [EmployeeDocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/{document}', [EmployeeDocumentController::class, 'show'])->name('documents.show');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
