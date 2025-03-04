<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ZtekoController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceReportController;
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

    // Gestión de Asistencias
    Route::prefix('attendances')->group(function () {
        Route::get('/', [AttendanceController::class, 'index']); // Listar registros de asistencia
        Route::get('/sync/{id}', [AttendanceController::class, 'syncAttendance']); // Sincronizar un dispositivo específico
        Route::get('/sync-all', [AttendanceController::class, 'syncAllDevices']); // Sincronizar todos los dispositivos
        Route::get('/stats', [AttendanceController::class, 'getStats']); // Obtener estadísticas de asistencia
        Route::get('/export', [AttendanceController::class, 'export']); // Exportar registros a CSV
        Route::get('/daily-range', [AttendanceController::class, 'getFirstLastByDay']); // Obtener primer y último registro por día
    });

    // RUTAS: Gestión de Empleados (con la ruta by-device colocada ANTES de la ruta /{id})
Route::prefix('employees')->group(function () {
    // Ruta general para listar
    Route::get('/', [EmployeeController::class, 'index']); // Listar todos los empleados

    // Esta ruta debe ir ANTES de la ruta con parámetro {id}
    Route::get('/by-device', [EmployeeController::class, 'getEmployeesByDevice']); // Obtener empleados por dispositivo

    // CRUD básico
    Route::post('/', [EmployeeController::class, 'store']); // Crear un nuevo empleado
    Route::get('/{id}', [EmployeeController::class, 'show']); // Ver un empleado específico
    Route::put('/{id}', [EmployeeController::class, 'update']); // Actualizar un empleado
    Route::delete('/{id}', [EmployeeController::class, 'destroy']); // Eliminar un empleado

    // Gestión de asistencias por empleado
    Route::get('/{id}/attendance', [EmployeeController::class, 'getAttendanceRecords']); // Obtener registros de asistencia
    Route::get('/{id}/daily-summary', [EmployeeController::class, 'getDailySummary']); // Obtener resumen diario
    Route::get('/{id}/monthly', [EmployeeController::class, 'getMonthlyAttendance']); // Obtener asistencia mensual
    Route::get('/{id}/stats', [EmployeeController::class, 'getAttendanceStats']); // Obtener estadísticas de asistencia
    Route::get('/{id}/export', [EmployeeController::class, 'exportAttendance']); // Exportar asistencia a CSV

    // Importación masiva de empleados
    Route::post('/import', [EmployeeController::class, 'importEmployees']); // Importar empleados desde CSV
});

    // RUTAS: Reportes de Asistencia
    Route::prefix('reports')->group(function () {
        // Ruta de debug para diagnóstico de relaciones
        Route::get('/attendance-detailed-report', [App\Http\Controllers\AttendanceReportController::class, 'generateDetailedDeviceReport']);
    });

    // Rutas para días festivos (holidays)
    Route::get('/holidays', [App\Http\Controllers\AttendanceSettingsController::class, 'listHolidays']);
    Route::post('/holidays', [App\Http\Controllers\AttendanceSettingsController::class, 'storeHoliday']);
    Route::get('/holidays/{id}', [App\Http\Controllers\AttendanceSettingsController::class, 'showHoliday']);
    Route::put('/holidays/{id}', [App\Http\Controllers\AttendanceSettingsController::class, 'updateHoliday']);
    Route::delete('/holidays/{id}', [App\Http\Controllers\AttendanceSettingsController::class, 'deleteHoliday']);

    // Rutas para permisos (permissions)
    Route::get('/permissions', [App\Http\Controllers\AttendanceSettingsController::class, 'listPermissions']);
    Route::post('/permissions', [App\Http\Controllers\AttendanceSettingsController::class, 'storePermission']);
    Route::get('/permissions/{id}', [App\Http\Controllers\AttendanceSettingsController::class, 'showPermission']);
    Route::put('/permissions/{id}', [App\Http\Controllers\AttendanceSettingsController::class, 'updatePermission']);
    Route::delete('/permissions/{id}', [App\Http\Controllers\AttendanceSettingsController::class, 'deletePermission']);
    Route::post('/permissions/bulk', [App\Http\Controllers\AttendanceSettingsController::class, 'storeBulkPermissions']);

});
Route::get('/Asistencia', function () {
    return Inertia::render('Attendance/Report');
})->name('attendance.report');
