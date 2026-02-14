<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\InvoiceController;

Route::get('/ping', fn () => ['ok' => true]);

Route::get('/invoices/clients', [InvoiceController::class, 'clients']);
Route::get('/invoices/preview', [InvoiceController::class, 'preview']);
Route::post('/invoices/save', [InvoiceController::class, 'save']);

Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'pdf']);
Route::get('/invoices/{id}/xls', [InvoiceController::class, 'xls']);
