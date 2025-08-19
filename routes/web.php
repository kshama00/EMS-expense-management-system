<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseSummaryController;
use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\HeadquarterController;


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

// Route::get('/', function () {
//     return view('welcome');
// });


Route::get('/', [ExpenseSummaryController::class, 'index'])->name('expenses.summary');
Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
Route::get('/expenses/view', [ExpenseController::class, 'index'])->name('expenses.view');
Route::post('/expenses/store', [ExpenseController::class, 'store'])->name('expenses.store');
Route::get('/admin/expenses', [ApprovalController::class, 'index'])->name('admin.approval');
Route::get('/hq-status', [HeadquarterController::class, 'index'])->name('hq-status');
Route::post('/expenses/store/{id}', [ExpenseController::class, 'store'])->name('expenses.resubmit');
Route::get('/expenses/check-status/{id}', [ExpenseController::class, 'checkStatus']);
Route::get('/admin/expenses/check-status/{id}', [ApprovalController::class, 'checkStatus'])->name('expenses.check-status');
Route::post('/admin/expenses/bulk-update', [ApprovalController::class, 'bulkUpdate'])->name('expenses.bulk-update');
Route::post('/admin/expenses/update-status', [ApprovalController::class, 'updateStatus'])->name('expenses.update-status');
Route::post('/admin/expenses/approval/{expense}', [ApprovalController::class, 'updateApproval'])->name('expenses.update');
Route::post('/expenses/check-duplicate', [ExpenseController::class, 'checkDuplicate'])->name('expenses.check-duplicate');










