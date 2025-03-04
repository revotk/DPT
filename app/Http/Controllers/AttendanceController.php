<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Zteko;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    /**
     * Mostrar todos los registros de asistencia con opciones de filtrado
     */
    public function index(Request $request)
    {
        $query = Attendance::query();

        // Filtros opcionales
        if ($request->has('device')) {
            $query->where('device', $request->device);
        }

        if ($request->has('uid')) {
            $query->where('uid', $request->uid);
        }

        if ($request->has('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        // Ordenar por fecha descendente (más reciente primero)
        $attendances = $query->orderBy('date', 'desc')
            ->paginate($request->per_page ?? 50);

        return response()->json($attendances);
    }

    /**
     * Sincronizar registros de asistencia desde un dispositivo ZKTeco
     */
    public function syncAttendance($id)
    {
        // Buscar el dispositivo
        $zteko = Zteko::findOrFail($id);

        // Obtener el último registro sincronizado para este dispositivo
        $lastRecord = Attendance::where('device', $id)
            ->orderBy('date', 'desc')
            ->first();

        $lastTimestamp = $lastRecord ? $lastRecord->date : null;

        // Obtener los registros desde el dispositivo
        $ztekoController = new ZtekoController();
        $response = $ztekoController->getAttendanceLogs($id);
        $attendanceLogs = json_decode($response->getContent(), true);

        if (!is_array($attendanceLogs)) {
            return response()->json([
                'error' => 'No se pudieron obtener los registros de asistencia',
                'device' => $id
            ], 500);
        }

        try {
            DB::beginTransaction();

            // Contador de nuevos registros
            $newRecordsCount = 0;
            $lastAddedRecord = null;

            // Procesar los registros
            foreach ($attendanceLogs as $log) {
                $timestamp = Carbon::parse($log['date']);

                // Verificar si este registro es posterior al último sincronizado
                if ($lastTimestamp && $timestamp <= $lastTimestamp) {
                    continue;
                }

                // Verificar duplicados
                $exists = Attendance::where('device', $log['device'])
                    ->where('uid', $log['uid'])
                    ->where('date', $timestamp)
                    ->exists();

                if (!$exists) {
                    // Crear nuevo registro
                    $attendance = new Attendance([
                        'device' => $log['device'],
                        'uid' => $log['uid'],
                        'date' => $timestamp
                    ]);

                    $attendance->save();
                    $newRecordsCount++;
                    $lastAddedRecord = $attendance;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sincronización completada',
                'device' => $id,
                'new_records_count' => $newRecordsCount,
                'last_record_before_sync' => $lastRecord ? [
                    'id' => $lastRecord->id,
                    'date' => $lastRecord->date->format('Y-m-d H:i:s'),
                    'uid' => $lastRecord->uid
                ] : null,
                'last_added_record' => $lastAddedRecord ? [
                    'id' => $lastAddedRecord->id,
                    'date' => $lastAddedRecord->date->format('Y-m-d H:i:s'),
                    'uid' => $lastAddedRecord->uid
                ] : null
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Error al sincronizar registros: ' . $e->getMessage(),
                'device' => $id
            ], 500);
        }
    }

    /**
     * Sincronizar asistencias de todos los dispositivos disponibles
     */
    public function syncAllDevices()
    {
        $devices = Zteko::all();
        $results = [];
        $totalNewRecords = 0;

        foreach ($devices as $device) {
            // Intentar sincronizar cada dispositivo
            try {
                $response = $this->syncAttendance($device->id);
                $content = json_decode($response->getContent(), true);

                $newRecords = $content['new_records_count'] ?? 0;
                $totalNewRecords += $newRecords;

                $results[] = [
                    'device' => $device->id,
                    'description' => $device->description,
                    'success' => isset($content['success']) && $content['success'],
                    'new_records' => $newRecords,
                    'message' => $content['message'] ?? $content['error'] ?? 'Unknown response'
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'device' => $device->id,
                    'description' => $device->description,
                    'success' => false,
                    'new_records' => 0,
                    'message' => 'Error: ' . $e->getMessage()
                ];
            }
        }

        return response()->json([
            'sync_date' => now()->format('Y-m-d H:i:s'),
            'devices_processed' => count($devices),
            'total_new_records' => $totalNewRecords,
            'results' => $results
        ]);
    }

    /**
     * Obtener estadísticas de asistencia
     */
    public function getStats(Request $request)
    {
        // Fecha de inicio y fin para estadísticas (por defecto el mes actual)
        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

        // Contador de registros por dispositivo
        $deviceCounts = DB::table('attendances')
            ->select('device', DB::raw('count(*) as total'))
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('device')
            ->get();

        // Contador de registros por día
        $dailyCounts = DB::table('attendances')
            ->select(DB::raw('DATE(date) as day'), DB::raw('count(*) as total'))
            ->whereBetween('date', [$startDate, $endDate])
            ->groupBy('day')
            ->get();

        // Último registro por dispositivo
        $lastRecords = DB::table('attendances as a')
            ->select('a.device', 'a.uid', 'a.date')
            ->join(DB::raw('(
                SELECT device, MAX(date) as max_date
                FROM attendances
                GROUP BY device
            ) as b'), function($join) {
                $join->on('a.device', '=', 'b.device')
                     ->on('a.date', '=', 'b.max_date');
            })
            ->get();

        // Total de registros
        $totalRecords = Attendance::count();

        return response()->json([
            'total_records' => $totalRecords,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'devices' => $deviceCounts,
            'daily' => $dailyCounts,
            'last_records' => $lastRecords
        ]);
    }

    /**
     * Exportar registros a CSV
     */
    public function export(Request $request)
    {
        $query = Attendance::query();

        // Aplicar filtros
        if ($request->has('device')) {
            $query->where('device', $request->device);
        }

        if ($request->has('uid')) {
            $query->where('uid', $request->uid);
        }

        if ($request->has('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        $attendances = $query->orderBy('date', 'desc')->get();

        // Cabecera del CSV
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance_export_' . now()->format('Y-m-d') . '.csv"',
        ];

        // Crear el CSV
        $callback = function() use ($attendances) {
            $file = fopen('php://output', 'w');

            // Encabezados
            fputcsv($file, ['ID', 'Dispositivo', 'UID', 'Fecha y Hora', 'Creado']);

            // Datos
            foreach ($attendances as $record) {
                fputcsv($file, [
                    $record->id,
                    $record->device,
                    $record->uid,
                    $record->date->format('Y-m-d H:i:s'),
                    $record->created_at->format('Y-m-d H:i:s')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
 * Obtener el primer y último registro de cada UID por día dentro de un rango de fechas
 */
/**
 * Get the first and last record of each UID by day within a date range
 */
public function getFirstLastByDay(Request $request)
{
    $request->validate([
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
        'device' => 'nullable|integer'
    ]);

    $startDate = $request->start_date;
    $endDate = $request->end_date;

    // Base query
    $query = DB::table('attendances')
        ->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate]);

    // Filter by device if provided
    if ($request->has('device')) {
        $query->where('device', $request->device);
    }

    // Subquery to get the first record (entry) of each uid by day
    $firstRecordsQuery = DB::table('attendances as a')
        ->select(
            'a.id',
            'a.device',
            'a.uid',
            'a.date',
            DB::raw("DATE(a.date) as day"),
            DB::raw("'entry' as record_type")
        )
        ->join(DB::raw('(
            SELECT uid, DATE(date) as day, MIN(date) as min_date
            FROM attendances
            WHERE DATE(date) BETWEEN ? AND ?
            ' . ($request->has('device') ? 'AND device = ?' : '') . '
            GROUP BY uid, DATE(date)
        ) as first_records'), function($join) {
            $join->on('a.uid', '=', 'first_records.uid')
                 ->on(DB::raw('DATE(a.date)'), '=', 'first_records.day')
                 ->on('a.date', '=', 'first_records.min_date');
        });

    // Parameters for first records subquery
    $firstRecordsParams = [$startDate, $endDate];
    if ($request->has('device')) {
        $firstRecordsParams[] = $request->device;
    }
    $firstRecordsQuery->addBinding($firstRecordsParams, 'join');

    // Subquery to get the last record (exit) of each uid by day
    $lastRecordsQuery = DB::table('attendances as a')
        ->select(
            'a.id',
            'a.device',
            'a.uid',
            'a.date',
            DB::raw("DATE(a.date) as day"),
            DB::raw("'exit' as record_type")
        )
        ->join(DB::raw('(
            SELECT uid, DATE(date) as day, MAX(date) as max_date
            FROM attendances
            WHERE DATE(date) BETWEEN ? AND ?
            ' . ($request->has('device') ? 'AND device = ?' : '') . '
            GROUP BY uid, DATE(date)
        ) as last_records'), function($join) {
            $join->on('a.uid', '=', 'last_records.uid')
                 ->on(DB::raw('DATE(a.date)'), '=', 'last_records.day')
                 ->on('a.date', '=', 'last_records.max_date');
        });

    // Parameters for last records subquery
    $lastRecordsParams = [$startDate, $endDate];
    if ($request->has('device')) {
        $lastRecordsParams[] = $request->device;
    }
    $lastRecordsQuery->addBinding($lastRecordsParams, 'join');

    // Combine the two subqueries
    $records = $firstRecordsQuery->union($lastRecordsQuery)
        ->orderBy('day')
        ->orderBy('uid')
        ->orderBy('date')
        ->get();

    // Group by days to facilitate frontend processing
    $recordsByDay = [];
    foreach ($records as $record) {
        $day = $record->day;
        $uid = $record->uid;

        if (!isset($recordsByDay[$day])) {
            $recordsByDay[$day] = [];
        }

        if (!isset($recordsByDay[$day][$uid])) {
            $recordsByDay[$day][$uid] = [];
        }

        // Use "entry" or "exit" as the key based on the record_type
        $recordKey = $record->record_type;

        $recordsByDay[$day][$uid][$recordKey] = [
            'id' => $record->id,
            'device' => $record->device,
            'uid' => $record->uid,
            'date' => $record->date
            // record_type is removed as it's now represented by the key
        ];
    }

    return response()->json([
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ],
        'device' => $request->device ?? 'all',
        'records_by_day' => $recordsByDay
    ]);
}
}
