<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Zteko;
use App\Models\Holiday; // Modelo para días de asueto
use App\Models\Permission; // Modelo para permisos
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceReportController extends Controller
{
    /**
     * Generar un reporte detallado por rango de fechas y dispositivo
     * Muestra la primera entrada y última salida por día para cada usuario
     */
    public function generateDetailedDeviceReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'device_id' => 'required|integer',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate = Carbon::parse($request->end_date)->endOfDay();
        $deviceId = $request->device_id;

        // Obtener el dispositivo
        $device = Zteko::findOrFail($deviceId);

        // Obtener todos los UIDs únicos que han registrado en este dispositivo
        $uniqueUids = Attendance::where('device', $deviceId)
            ->select('uid')
            ->distinct()
            ->pluck('uid')
            ->toArray();

        // Generar fechas en el rango seleccionado (para incluir días sin registros)
        $dateRange = [];
        $currentDate = clone $startDate;
        while ($currentDate <= $endDate) {
            $dateRange[] = $currentDate->format('Y-m-d');
            $currentDate->addDay();
        }

        // Obtener días de asueto en el rango de fechas
        $holidays = Holiday::whereBetween('date', [$startDate, $endDate])
            ->get()
            ->keyBy(function ($holiday) {
                return $holiday->date->format('Y-m-d');
            });

        // Obtener permisos para todos los usuarios en el rango de fechas
        $permissions = Permission::whereBetween('date', [$startDate, $endDate])
            ->get();

        // Obtener todos los registros de asistencia para este dispositivo en el periodo
        $allAttendanceRecords = Attendance::where('device', $deviceId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        // Procesar los registros para agruparlos por uid y fecha
        $attendanceByUidAndDay = [];
        foreach ($uniqueUids as $uid) {
            $attendanceByUidAndDay[$uid] = [];

            // Inicializar todos los días en el rango y verificar cada caso especial
            foreach ($dateRange as $date) {
                $dateObj = Carbon::parse($date);
                $status = 'Falta';
                $statusReason = null;

                // Caso 1: Verificar si es Descanso (sábado o domingo)
                if ($dateObj->isWeekend()) {
                    $status = 'Descanso';
                    $statusReason = $dateObj->isSaturday() ? 'Sábado' : 'Domingo';
                }

                $attendanceByUidAndDay[$uid][$date] = [
                    'date' => $date,
                    'first_check' => null,
                    'last_check' => null,
                    'status' => $status,
                    'status_reason' => $statusReason,
                    'working_hours' => 0
                ];
            }
        }

        // Llenar los registros de asistencia encontrados
        foreach ($allAttendanceRecords as $record) {
            try {
                $recordDate = Carbon::parse($record->date);
                $dateKey = $recordDate->format('Y-m-d');
                $timeKey = $recordDate->format('H:i:s');
                $uid = $record->uid;

                if (!isset($attendanceByUidAndDay[$uid])) {
                    // Si el UID no estaba inicializado, crearlo
                    $attendanceByUidAndDay[$uid] = [];
                    foreach ($dateRange as $date) {
                        $attendanceByUidAndDay[$uid][$date] = [
                            'date' => $date,
                            'first_check' => null,
                            'last_check' => null,
                            'status' => 'Falta',
                            'working_hours' => 0
                        ];
                    }
                }

                if (!isset($attendanceByUidAndDay[$uid][$dateKey])) {
                    // Si la fecha no estaba inicializada (caso poco probable)
                    $dateObj = Carbon::parse($dateKey);
                    $status = 'Falta';
                    $statusReason = null;

                    // Caso 1: Verificar si es Descanso (sábado o domingo)
                    if ($dateObj->isWeekend()) {
                        $status = 'Descanso';
                        $statusReason = $dateObj->isSaturday() ? 'Sábado' : 'Domingo';
                    }

                    $attendanceByUidAndDay[$uid][$dateKey] = [
                        'date' => $dateKey,
                        'first_check' => null,
                        'last_check' => null,
                        'status' => $status,
                        'status_reason' => $statusReason,
                        'working_hours' => 0
                    ];
                }

                // Si es el primer registro del día o es anterior al actual primer registro
                if (
                    $attendanceByUidAndDay[$uid][$dateKey]['first_check'] === null ||
                    $timeKey < $attendanceByUidAndDay[$uid][$dateKey]['first_check']
                ) {
                    $attendanceByUidAndDay[$uid][$dateKey]['first_check'] = $timeKey;
                    // Si solo hay entrada, actualizar el estado
                    $attendanceByUidAndDay[$uid][$dateKey]['status'] = 'Entrada';
                    $attendanceByUidAndDay[$uid][$dateKey]['status_reason'] = null;
                }

                // Si es el último registro del día o es posterior al actual último registro
                if (
                    $attendanceByUidAndDay[$uid][$dateKey]['last_check'] === null ||
                    $timeKey > $attendanceByUidAndDay[$uid][$dateKey]['last_check']
                ) {
                    $attendanceByUidAndDay[$uid][$dateKey]['last_check'] = $timeKey;

                    // Si hay entrada y salida, actualizar el estado
                    if ($attendanceByUidAndDay[$uid][$dateKey]['first_check'] !== null) {
                        $attendanceByUidAndDay[$uid][$dateKey]['status'] = 'Asistencia';
                        $attendanceByUidAndDay[$uid][$dateKey]['status_reason'] = null;

                        // Calcular horas trabajadas
                        $attendanceByUidAndDay[$uid][$dateKey]['working_hours'] = $this->calculateWorkingHours(
                            $attendanceByUidAndDay[$uid][$dateKey]['first_check'],
                            $attendanceByUidAndDay[$uid][$dateKey]['last_check']
                        );
                    }
                }
            } catch (\Exception $e) {
                // Ignorar registros con fechas inválidas
                continue;
            }
        }

        // Mapear empleados usando la regla checker_uid = uid (en formato string)
        $employeesByUid = [];
        $employees = Employee::where('status', 'active')->get();

        foreach ($employees as $employee) {
            if (!empty($employee->checker_uid)) {
                $checkerUidAsString = (string)$employee->checker_uid;

                if (in_array($checkerUidAsString, $uniqueUids)) {
                    $employeesByUid[$checkerUidAsString] = $employee;
                }
            }
        }

        // Ahora que tenemos los empleados mapeados, podemos procesar los días de asueto y permisos
        foreach ($attendanceByUidAndDay as $uid => $daysData) {
            // Verificar si hay un empleado asociado a este UID
            $employeeId = isset($employeesByUid[$uid]) ? $employeesByUid[$uid]->id : null;

            foreach ($daysData as $date => $dayData) {
                // Caso 2: Verificar si es Asueto
                if (isset($holidays[$date])) {
                    $attendanceByUidAndDay[$uid][$date]['status'] = 'Asueto';
                    $attendanceByUidAndDay[$uid][$date]['status_reason'] = $holidays[$date]->description;
                    continue;
                }

                // Caso 3: Verificar si hay un permiso para este empleado en esta fecha
                if ($employeeId) {
                    $permission = $permissions->first(function ($item) use ($employeeId, $date, $uid) {
                        return $item->employee_id == $employeeId &&
                               $item->date->format('Y-m-d') == $date &&
                               $item->checker_uid == $uid;
                    });

                    if ($permission) {
                        $attendanceByUidAndDay[$uid][$date]['status'] = 'Permiso';
                        $attendanceByUidAndDay[$uid][$date]['status_reason'] = $permission->reason;
                    }
                }
            }
        }

        // Preparar el reporte final
        $report = [
            'device' => [
                'id' => $device->id,
                'description' => $device->description
            ],
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'total_days' => count($dateRange)
            ],
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'total_users' => count($uniqueUids),
            'total_records' => $allAttendanceRecords->count(),
            'attendance' => []
        ];

        // Procesar la asistencia por usuario
        foreach ($attendanceByUidAndDay as $uid => $daysData) {
            $userData = [
                'uid' => $uid,
                'days' => []
            ];

            // Procesar días con asistencia/faltas
            foreach ($daysData as $date => $dayData) {
                $userData['days'][] = [
                    'date' => $date,
                    'first_check' => $dayData['first_check'],
                    'last_check' => $dayData['last_check'],
                    'status' => $dayData['status'],
                    'status_reason' => $dayData['status_reason'],
                    'working_hours' => $dayData['working_hours']
                ];
            }

            // Calcular estadísticas
            $completeCount = 0;
            $onlyEntryCount = 0;
            $missingCount = 0;
            $holidayCount = 0;
            $weekendCount = 0;
            $permissionCount = 0;

            foreach ($userData['days'] as $day) {
                if ($day['status'] === 'Asistencia') {
                    $completeCount++;
                } elseif ($day['status'] === 'Entrada') {
                    $onlyEntryCount++;
                } elseif ($day['status'] === 'Asueto') {
                    $holidayCount++;
                } elseif ($day['status'] === 'Descanso') {
                    $weekendCount++;
                } elseif ($day['status'] === 'Permiso') {
                    $permissionCount++;
                } else {
                    $missingCount++;
                }
            }

            // Calcular días laborables (excluyendo fines de semana y días de asueto)
            $workingDays = count($dateRange) - $weekendCount - $holidayCount;

            $userData['statistics'] = [
                'complete_days' => $completeCount,
                'only_entry_days' => $onlyEntryCount,
                'missing_days' => $missingCount,
                'holiday_days' => $holidayCount,
                'weekend_days' => $weekendCount,
                'permission_days' => $permissionCount,
                'working_days' => $workingDays,
                'total_days' => count($dateRange),
                // Tasa de asistencia basada en días laborables (excluyendo fines de semana, asuetos y permisos)
                'attendance_rate' => $workingDays > 0
                    ? round((($completeCount + $onlyEntryCount) / $workingDays) * 100, 2)
                    : 100
            ];

            $report['attendance'][] = $userData;
        }

        // Eliminamos la parte de procesar información de empleados ya que no se utiliza

        return response()->json($report);
    }

    /**
     * Calcular horas trabajadas entre entrada y salida
     */
    private function calculateWorkingHours($entryTime, $exitTime)
    {
        if ($entryTime === null || $exitTime === null) {
            return 0;
        }

        try {
            // Usar la fecha actual solo como referencia para calcular la diferencia de horas
            $today = Carbon::today()->format('Y-m-d');
            $entry = Carbon::parse("$today $entryTime");
            $exit = Carbon::parse("$today $exitTime");

            // Si la salida es anterior a la entrada (posible error o caso especial)
            if ($exit->lt($entry)) {
                return 0;
            }

            $minutes = $entry->diffInMinutes($exit);
            $hours = round($minutes / 60, 2);

            return $hours;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
