<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\DocumentationController;
use App\Http\Controllers\Api\KeywordController;
use App\Http\Controllers\Api\LicenseController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\ResourceFileController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/documentation', [DocumentationController::class, 'ui']);
Route::get('/openapi.yaml', [DocumentationController::class, 'spec']);

Route::post('/login', [AuthController::class, 'login']);

Route::get('/resources/semantic', [ResourceController::class, 'semantic']);
Route::get('/resources', [ResourceController::class, 'index']);
Route::get('/resources/{resource}', [ResourceController::class, 'show']);
Route::get('/resources/{resource}/files/{file}/download', [ResourceFileController::class, 'download']);

Route::get('/authors', [AuthorController::class, 'index']);
Route::get('/authors/{author}', [AuthorController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('/collections', [CollectionController::class, 'index']);
Route::get('/collections/{collection}', [CollectionController::class, 'show']);
Route::get('/collections/{collection}/resources', [CollectionController::class, 'resources']);
Route::get('/keywords', [KeywordController::class, 'index']);
Route::get('/keywords/{keyword}', [KeywordController::class, 'show']);
Route::get('/licenses', [LicenseController::class, 'index']);
Route::get('/licenses/{license}', [LicenseController::class, 'show']);

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('users', UserController::class);

    Route::post('/authors', [AuthorController::class, 'store']);
    Route::put('/authors/{author}', [AuthorController::class, 'update']);
    Route::delete('/authors/{author}', [AuthorController::class, 'destroy']);

    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    Route::post('/collections', [CollectionController::class, 'store']);
    Route::put('/collections/{collection}', [CollectionController::class, 'update']);
    Route::delete('/collections/{collection}', [CollectionController::class, 'destroy']);

    Route::post('/keywords', [KeywordController::class, 'store']);
    Route::put('/keywords/{keyword}', [KeywordController::class, 'update']);
    Route::delete('/keywords/{keyword}', [KeywordController::class, 'destroy']);

    Route::post('/licenses', [LicenseController::class, 'store']);
    Route::put('/licenses/{license}', [LicenseController::class, 'update']);
    Route::delete('/licenses/{license}', [LicenseController::class, 'destroy']);

    Route::post('/resources', [ResourceController::class, 'store']);
    Route::put('/resources/{resource}', [ResourceController::class, 'update']);
    Route::delete('/resources/{resource}', [ResourceController::class, 'destroy']);
    Route::post('/resources/{resource}/submit', [ResourceController::class, 'submit']);
    Route::post('/resources/{resource}/review', [ResourceController::class, 'review']);
    Route::post('/resources/{resource}/publish', [ResourceController::class, 'publish']);
    Route::post('/resources/{resource}/withdraw', [ResourceController::class, 'withdraw']);
    Route::post('/resources/{resource}/files', [ResourceFileController::class, 'store']);
    Route::delete('/resources/{resource}/files/{file}', [ResourceFileController::class, 'destroy']);

    Route::middleware('role:gestor')->group(function () {
        Route::get('/stats', StatsController::class);
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
    });
});
