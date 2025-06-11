<?php

use App\Http\Controllers\WhatsAppInstanceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('whatsapp')->group(function () {
    Route::get('/', [WhatsAppInstanceController::class, 'index']);
    Route::post('/', [WhatsAppInstanceController::class, 'store']);
    Route::get('/{instance}', [WhatsAppInstanceController::class, 'show']);
    Route::put('/{instance}', [WhatsAppInstanceController::class, 'update']);
    Route::delete('/{instance}', [WhatsAppInstanceController::class, 'destroy']);
    Route::post('/{instance}/start', [WhatsAppInstanceController::class, 'start']);
    Route::post('/{instance}/stop', [WhatsAppInstanceController::class, 'stop']);
    Route::get('/{instance}/status', [WhatsAppInstanceController::class, 'status']);
});

Route::any('instance/{instance}/{path?}', function ($instance, $path = '') {
    // This route will be handled by the WhatsAppProxyMiddleware
    return response()->json(['error' => 'Middleware should handle this route'], 500);
})->where('path', '.*')->middleware('whatsapp.proxy');
