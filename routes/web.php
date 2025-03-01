<?php

use App\Http\Controllers\ZtekoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Rutas web (con protección CSRF)
Route::middleware('web')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Welcome');
    })->name('home');

    Route::get('dashboard', function () {
        return Inertia::render('Dashboard');
    })->middleware(['auth', 'verified'])->name('dashboard');

    require __DIR__.'/settings.php';
    require __DIR__.'/auth.php';
});

// Rutas API (sin protección CSRF)
Route::middleware('api')->prefix('api')->group(function () {
    // Rutas para gestión de dispositivos ZKTeco (CRUD básico)
    Route::prefix('zteko')->group(function () {
        Route::get('/', [ZtekoController::class, 'index']); // Listar todos los dispositivos
        Route::post('/', [ZtekoController::class, 'store']); // Agregar un nuevo dispositivo
        Route::get('/device/{id}', [ZtekoController::class, 'show']); // Mostrar un dispositivo específico
        Route::put('/device/{id}', [ZtekoController::class, 'update']); // Actualizar un dispositivo
        Route::delete('/device/{id}', [ZtekoController::class, 'destroy']); // Eliminar un dispositivo
        Route::get('/device/{id}/refresh', [ZtekoController::class, 'refresh']); // Refrescar información del dispositivo

        // Rutas para operaciones específicas de cada dispositivo
        Route::prefix('/device/{id}')->group(function () {
            Route::get('/info', [ZtekoController::class, 'getDeviceInfo']); // Obtener información del dispositivo
            Route::get('/attendance', [ZtekoController::class, 'getAttendanceLogs']); // Obtener registros de asistencia

            // Rutas para gestión de usuarios del dispositivo
            Route::prefix('/users')->group(function () {
                Route::get('/', [ZtekoController::class, 'getUsers']); // Obtener lista de usuarios
                Route::post('/', [ZtekoController::class, 'addUser']); // Agregar un usuario
                Route::delete('/{userId}', [ZtekoController::class, 'deleteUser']); // Eliminar un usuario
                Route::put('/{userId}', [ZtekoController::class, 'editUser']); // Editar un usuario
            });
        });
    });
});
