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
            'end_date'   => 'required|date|after_or_equal:start_date',
            'device_id'  => 'required|integer',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate   = Carbon::parse($request->end_date)->endOfDay();
        $deviceId  = $request->device_id;

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
            foreach ($dateRange as $date) {
                $dateObj = Carbon::parse($date);
                $status = 'Falta';
                $statusReason = null;
                if ($dateObj->isWeekend()) {
                    $status = 'Descanso';
                    $statusReason = $dateObj->isSaturday() ? 'Sábado' : 'Domingo';
                }
                $attendanceByUidAndDay[$uid][$date] = [
                    'date'          => $date,
                    'first_check'   => null,
                    'last_check'    => null,
                    'status'        => $status,
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
                    $attendanceByUidAndDay[$uid] = [];
                    foreach ($dateRange as $date) {
                        $attendanceByUidAndDay[$uid][$date] = [
                            'date'          => $date,
                            'first_check'   => null,
                            'last_check'    => null,
                            'status'        => 'Falta',
                            'status_reason' => null,
                            'working_hours' => 0
                        ];
                    }
                }

                if (!isset($attendanceByUidAndDay[$uid][$dateKey])) {
                    $dateObj = Carbon::parse($dateKey);
                    $status = 'Falta';
                    $statusReason = null;
                    if ($dateObj->isWeekend()) {
                        $status = 'Descanso';
                        $statusReason = $dateObj->isSaturday() ? 'Sábado' : 'Domingo';
                    }
                    $attendanceByUidAndDay[$uid][$dateKey] = [
                        'date'          => $dateKey,
                        'first_check'   => null,
                        'last_check'    => null,
                        'status'        => $status,
                        'status_reason' => $statusReason,
                        'working_hours' => 0
                    ];
                }

                // Actualizar el primer registro del día
                if (
                    $attendanceByUidAndDay[$uid][$dateKey]['first_check'] === null ||
                    $timeKey < $attendanceByUidAndDay[$uid][$dateKey]['first_check']
                ) {
                    $attendanceByUidAndDay[$uid][$dateKey]['first_check'] = $timeKey;
                    $attendanceByUidAndDay[$uid][$dateKey]['status'] = 'Entrada';
                    $attendanceByUidAndDay[$uid][$dateKey]['status_reason'] = null;
                }

                // Actualizar el último registro del día
                if (
                    $attendanceByUidAndDay[$uid][$dateKey]['last_check'] === null ||
                    $timeKey > $attendanceByUidAndDay[$uid][$dateKey]['last_check']
                ) {
                    $attendanceByUidAndDay[$uid][$dateKey]['last_check'] = $timeKey;
                    if ($attendanceByUidAndDay[$uid][$dateKey]['first_check'] !== null) {
                        $attendanceByUidAndDay[$uid][$dateKey]['status'] = 'Asistencia';
                        $attendanceByUidAndDay[$uid][$dateKey]['status_reason'] = null;
                        $attendanceByUidAndDay[$uid][$dateKey]['working_hours'] = $this->calculateWorkingHours(
                            $attendanceByUidAndDay[$uid][$dateKey]['first_check'],
                            $attendanceByUidAndDay[$uid][$dateKey]['last_check']
                        );
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Mapear empleados usando la regla: checker_uid (como string) = uid
        // Se ajusta la consulta para usar "Activo" según tu muestra
        $employeesByUid = [];
        $employees = Employee::where('status', 'Activo')->get();
        foreach ($employees as $employee) {
            if (!empty($employee->checker_uid)) {
                $checkerUidAsString = (string)$employee->checker_uid;
                if (in_array($checkerUidAsString, $uniqueUids)) {
                    $employeesByUid[$checkerUidAsString] = $employee;
                }
            }
        }

        // Procesar días de asueto y permisos
        foreach ($attendanceByUidAndDay as $uid => $daysData) {
            $employeeFromMapping = isset($employeesByUid[$uid]) ? $employeesByUid[$uid] : null;
            foreach ($daysData as $date => $dayData) {
                // Caso: Día festivo (Asueto) tiene prioridad
                if (isset($holidays[$date])) {
                    $attendanceByUidAndDay[$uid][$date]['status'] = 'Asueto';
                    $attendanceByUidAndDay[$uid][$date]['status_reason'] = $holidays[$date]->description;
                    continue;
                }
                // Si existe un empleado mapeado, buscar permiso para ese día
                if ($employeeFromMapping) {
                    $permission = $permissions->first(function ($item) use ($employeeFromMapping, $date, $uid) {
                        return $item->employee_id == $employeeFromMapping->employee_id &&
                               $item->date->format('Y-m-d') == $date &&
                               (string)$item->checker_uid === (string)$uid;
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
                'id'          => $device->id,
                'description' => $device->description
            ],
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date'   => $endDate->format('Y-m-d'),
                'total_days' => count($dateRange)
            ],
            'generated_at'  => now()->format('Y-m-d H:i:s'),
            'total_users'   => count($uniqueUids),
            'total_records' => $allAttendanceRecords->count(),
            'attendance'    => []
        ];

        foreach ($attendanceByUidAndDay as $uid => $daysData) {
            $userData = [
                'uid'  => $uid,
                'days' => []
            ];

            foreach ($daysData as $date => $dayData) {
                $userData['days'][] = [
                    'date'          => $date,
                    'first_check'   => $dayData['first_check'],
                    'last_check'    => $dayData['last_check'],
                    'status'        => $dayData['status'],
                    'status_reason' => $dayData['status_reason'],
                    'working_hours' => $dayData['working_hours']
                ];
            }

            $completeCount   = 0;
            $onlyEntryCount  = 0;
            $missingCount    = 0;
            $holidayCount    = 0;
            $weekendCount    = 0;
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

            $workingDays = count($dateRange) - $weekendCount - $holidayCount -$permissionCount;
            $userData['statistics'] = [
                'complete_days'   => $completeCount,
                'only_entry_days' => $onlyEntryCount,
                'missing_days'    => $missingCount,
                'holiday_days'    => $holidayCount,
                'weekend_days'    => $weekendCount,
                'permission_days' => $permissionCount,
                'working_days'    => $workingDays,
                'total_days'      => count($dateRange),
                'attendance_rate' => $workingDays > 0
                    ? round((($completeCount + $onlyEntryCount) / $workingDays) * 100, 2)
                    : 100
            ];

            $report['attendance'][] = $userData;
        }

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
            $today = Carbon::today()->format('Y-m-d');
            $entry = Carbon::parse("$today $entryTime");
            $exit  = Carbon::parse("$today $exitTime");

            if ($exit->lt($entry)) {
                return 0;
            }

            $minutes = $entry->diffInMinutes($exit);
            return round($minutes / 60, 2);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
