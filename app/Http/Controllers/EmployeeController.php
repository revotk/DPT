<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the employees.
     */
    public function index(Request $request)
    {
        $query = Employee::query();

        // Filtros opcionales
        if ($request->has('name')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'LIKE', '%' . $request->name . '%')
                  ->orWhere('lastname', 'LIKE', '%' . $request->name . '%')
                  ->orWhere('fullname', 'LIKE', '%' . $request->name . '%');
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('position')) {
            $query->where('position', 'LIKE', '%' . $request->position . '%');
        }

        if ($request->has('adscription')) {
            $query->where('adscription', 'LIKE', '%' . $request->adscription . '%');
        }

        // Ordenar
        $orderBy = $request->input('order_by', 'id');
        $orderDir = $request->input('order_dir', 'asc');
        $query->orderBy($orderBy, $orderDir);

        $employees = $query->paginate($request->input('per_page', 15));

        return response()->json($employees);
    }

    /**
     * Store a newly created employee in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'employee_id' => 'nullable|numeric|unique:employees,employee_id',
            'rfc' => 'nullable|string|max:13',
            'curp' => 'nullable|string|max:18',
            'position' => 'nullable|string|max:255',
            'adscription' => 'nullable|string|max:255',
            'checker_uid' => 'nullable|string|max:255',
            'checker_id' => 'nullable|numeric',
        ]);

        $employee = Employee::create($request->all());

        return response()->json($employee, 201);
    }

    /**
     * Display the specified employee.
     */
    public function show($id)
    {
        $employee = Employee::findOrFail($id);
        return response()->json($employee);
    }

    /**
     * Update the specified employee in storage.
     */
    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'lastname' => 'sometimes|required|string|max:255',
            'employee_id' => 'sometimes|nullable|numeric|unique:employees,employee_id,' . $id,
            'rfc' => 'nullable|string|max:13',
            'curp' => 'nullable|string|max:18',
            'position' => 'nullable|string|max:255',
            'adscription' => 'nullable|string|max:255',
            'checker_uid' => 'nullable|string|max:255',
            'checker_id' => 'nullable|numeric',
        ]);

        $employee->update($request->all());

        return response()->json($employee);
    }

    /**
     * Remove the specified employee from storage.
     */
    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();

        return response()->json(null, 204);
    }

    /**
     * Display employee attendance records for a date range.
     */
    public function getAttendanceRecords(Request $request, $id)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $employee = Employee::findOrFail($id);
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $records = $employee->getAttendanceRecords($startDate, $endDate);

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->fullname ?: $employee->name . ' ' . $employee->lastname,
                'position' => $employee->position,
                'adscription' => $employee->adscription,
                'checker_uid' => $employee->checker_uid,
            ],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'records' => $records,
        ]);
    }

    /**
     * Get daily attendance summary for an employee.
     */
    public function getDailySummary(Request $request, $id)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $employee = Employee::findOrFail($id);
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $summary = $employee->getDailyAttendanceSummary($startDate, $endDate);

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->fullname ?: $employee->name . ' ' . $employee->lastname,
                'position' => $employee->position,
                'adscription' => $employee->adscription,
                'entry_time' => $employee->entry_time,
                'exit_time' => $employee->exit_time,
            ],
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'daily_summary' => $summary,
        ]);
    }

    /**
     * Get monthly attendance for an employee.
     */
    public function getMonthlyAttendance(Request $request, $id)
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $employee = Employee::findOrFail($id);
        $year = $request->year;
        $month = $request->month;

        $monthlyData = $employee->getMonthlyAttendanceRecords($year, $month);

        // Calcular estadísticas mensuales
        $totalDays = count($monthlyData);
        $presentDays = 0;
        $absentDays = 0;
        $daysOff = 0;
        $totalWorkingHours = 0;

        foreach ($monthlyData as $day) {
            if (isset($day['status']) && $day['status'] === 'present') {
                $presentDays++;
                if (isset($day['working_hours'])) {
                    $totalWorkingHours += $day['working_hours'];
                }
            } elseif (isset($day['status']) && $day['status'] === 'absent') {
                $absentDays++;
            } elseif (isset($day['status']) && $day['status'] === 'day_off') {
                $daysOff++;
            }
        }

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->fullname ?: $employee->name . ' ' . $employee->lastname,
                'position' => $employee->position,
                'adscription' => $employee->adscription,
            ],
            'month_info' => [
                'year' => $year,
                'month' => $month,
                'month_name' => Carbon::createFromDate($year, $month, 1)->format('F'),
            ],
            'statistics' => [
                'total_days' => $totalDays,
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'days_off' => $daysOff,
                'total_working_hours' => round($totalWorkingHours, 2),
                'average_daily_hours' => $presentDays > 0 ? round($totalWorkingHours / $presentDays, 2) : 0,
            ],
            'daily_data' => $monthlyData,
        ]);
    }

    /**
     * Export employee attendance to CSV.
     */
    public function exportAttendance(Request $request, $id)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $employee = Employee::findOrFail($id);
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $summary = $employee->getDailyAttendanceSummary($startDate, $endDate);

        // Cabecera del CSV
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance_' .
                str_replace(' ', '_', strtolower($employee->name)) . '_' .
                Carbon::now()->format('Y-m-d') . '.csv"',
        ];

        // Crear el CSV
        $callback = function() use ($employee, $summary) {
            $file = fopen('php://output', 'w');

            // Encabezados
            fputcsv($file, [
                'Empleado',
                $employee->fullname ?: $employee->name . ' ' . $employee->lastname,
                'Posición',
                $employee->position,
                'ID',
                $employee->employee_id
            ]);

            fputcsv($file, []); // Línea en blanco
            fputcsv($file, ['Fecha', 'Entrada', 'Salida', 'Horas Trabajadas']);

            // Datos
            foreach ($summary as $date => $record) {
                fputcsv($file, [
                    $date,
                    $record['entry'] ?? 'N/A',
                    $record['exit'] ?? 'N/A',
                    $record['working_hours'] ? number_format($record['working_hours'], 2) : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Import employees from CSV file.
     */
    public function importEmployees(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt',
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();

        // Abrir archivo CSV
        $handle = fopen($path, 'r');

        // Obtener encabezados
        $headers = fgetcsv($handle);

        // Preparar mapeo de encabezados a campos del modelo
        $columnMap = [
            'user' => array_search('user', array_map('strtolower', $headers)),
            'rfc' => array_search('rfc', array_map('strtolower', $headers)),
            'phone' => array_search('phone', array_map('strtolower', $headers)),
            'position' => array_search('position', array_map('strtolower', $headers)),
            'adscription' => array_search('adscripción', array_map('strtolower', $headers)),
            'entry_time' => array_search('h. ent.', array_map('strtolower', $headers)),
            'exit_time' => array_search('h. sal.', array_map('strtolower', $headers)),
            'status' => array_search('status', array_map('strtolower', $headers)),
            'fullname' => array_search('fullname', array_map('strtolower', $headers)),
            'curp' => array_search('curp', array_map('strtolower', $headers)),
            'name' => array_search('name', array_map('strtolower', $headers)),
            'employee_id' => array_search('employee', array_map('strtolower', $headers)),
            'lastname' => array_search('lastname', array_map('strtolower', $headers)),
            'checker_uid' => array_search('checadorid', array_map('strtolower', $headers)),
            'checker_id' => array_search('checadas', array_map('strtolower', $headers)),
        ];

        // Filtrar mapeos inválidos (no encontrados)
        $columnMap = array_filter($columnMap, function($item) {
            return $item !== false;
        });

        DB::beginTransaction();

        try {
            $imported = 0;
            $updated = 0;
            $errors = [];

            // Procesar cada fila
            while (($row = fgetcsv($handle)) !== false) {
                // Preparar datos del empleado
                $employeeData = [];

                foreach ($columnMap as $field => $index) {
                    if (isset($row[$index])) {
                        $employeeData[$field] = $row[$index];
                    }
                }

                // Solo procesar si tenemos datos mínimos
                if (!empty($employeeData['employee_id']) || !empty($employeeData['checker_uid'])) {
                    try {
                        // Buscar empleado existente por ID o UID
                        $employee = null;

                        if (!empty($employeeData['employee_id'])) {
                            $employee = Employee::where('employee_id', $employeeData['employee_id'])->first();
                        }

                        if (!$employee && !empty($employeeData['checker_uid'])) {
                            $employee = Employee::where('checker_uid', $employeeData['checker_uid'])->first();
                        }

                        if ($employee) {
                            // Actualizar empleado existente
                            $employee->update($employeeData);
                            $updated++;
                        } else {
                            // Crear nuevo empleado
                            Employee::create($employeeData);
                            $imported++;
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Error en fila " . (count($errors) + $imported + $updated + 1) . ": " . $e->getMessage();
                    }
                }
            }

            fclose($handle);
            DB::commit();

            return response()->json([
                'success' => true,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);

            return response()->json([
                'success' => false,
                'message' => 'Error durante la importación: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get employee attendance statistics.
     */
    public function getAttendanceStats(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        // Periodo por defecto (último mes)
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subMonth();

        if ($request->has('start_date')) {
            $startDate = Carbon::parse($request->start_date);
        }

        if ($request->has('end_date')) {
            $endDate = Carbon::parse($request->end_date);
        }

        // Calcular días laborables en el período
        $workDays = 0;
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            // No contar fines de semana (0 = domingo, 6 = sábado)
            if ($currentDate->dayOfWeek !== 0 && $currentDate->dayOfWeek !== 6) {
                $workDays++;
            }

            $currentDate->addDay();
        }

        // Obtener resumen diario
        $dailySummary = $employee->getDailyAttendanceSummary($startDate->format('Y-m-d'), $endDate->format('Y-m-d'));

        // Calcular estadísticas
        $attendanceDays = count($dailySummary);
        $onTimeCount = 0;
        $lateCount = 0;
        $earlyExitCount = 0;
        $absentDays = $workDays - $attendanceDays;
        $totalWorkingHours = 0;

        foreach ($dailySummary as $day => $data) {
            // Sumar horas trabajadas
            if (isset($data['working_hours'])) {
                $totalWorkingHours += $data['working_hours'];
            }

            // Contar llegadas puntuales y tardías
            if (isset($data['entry']) && $employee->entry_time) {
                $entryTime = Carbon::parse($data['entry']);
                $scheduledEntry = Carbon::parse($day . ' ' . $employee->entry_time);

                if ($entryTime->gt($scheduledEntry->addMinutes(10))) {
                    $lateCount++;
                } else {
                    $onTimeCount++;
                }
            }

            // Contar salidas tempranas
            if (isset($data['exit']) && $employee->exit_time) {
                $exitTime = Carbon::parse($data['exit']);
                $scheduledExit = Carbon::parse($day . ' ' . $employee->exit_time);

                if ($exitTime->lt($scheduledExit->subMinutes(10))) {
                    $earlyExitCount++;
                }
            }
        }

        return response()->json([
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->fullname ?: $employee->name . ' ' . $employee->lastname,
                'position' => $employee->position,
                'adscription' => $employee->adscription,
            ],
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'total_days' => $endDate->diffInDays($startDate) + 1,
                'work_days' => $workDays,
            ],
            'attendance' => [
                'days_present' => $attendanceDays,
                'days_absent' => $absentDays,
                'attendance_rate' => $workDays > 0 ? round(($attendanceDays / $workDays) * 100, 2) : 0,
                'on_time_count' => $onTimeCount,
                'late_count' => $lateCount,
                'early_exit_count' => $earlyExitCount,
                'total_working_hours' => round($totalWorkingHours, 2),
                'avg_daily_hours' => $attendanceDays > 0 ? round($totalWorkingHours / $attendanceDays, 2) : 0,
            ],
        ]);
    }
    /**
     * Obtiene los empleados asociados a un dispositivo checador específico.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEmployeesByDevice(Request $request)
    {
        try {
            $request->validate([
                'device_id' => 'required|integer'
            ]);

            $deviceId = $request->device_id;

            // Buscar empleados que tengan el checker_id igual al device_id proporcionado
            $employees = Employee::where('checker_id', $deviceId)
                ->select([
                    'id',
                    'user',
                    'rfc',
                    'phone',
                    'position',
                    'adscription',
                    'entry_time',
                    'exit_time',
                    'status',
                    'fullname',
                    'curp',
                    'name',
                    'employee_id',
                    'lastname',
                    'checker_uid',
                    'checker_id'
                ])
                ->orderBy('fullname')
                ->get();

            // Registrar información para debug
            Log::info('Consulta de empleados por dispositivo', [
                'device_id' => $deviceId,
                'count' => $employees->count(),
                'sql' => Employee::where('checker_id', $deviceId)->toSql()
            ]);

            return response()->json([
                'device_id' => $deviceId,
                'total_employees' => $employees->count(),
                'employees' => $employees
            ]);
        } catch (\Exception $e) {
            Log::error('Error al buscar empleados por dispositivo', [
                'error' => $e->getMessage(),
                'device_id' => $request->device_id ?? 'no proporcionado'
            ]);

            return response()->json([
                'error' => 'Error al buscar empleados: ' . $e->getMessage()
            ], 500);
        }
    }
}
