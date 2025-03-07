<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\Permission;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AttendanceSettingsController extends Controller
{
    /**
     * =============================================
     * MÉTODOS PARA DÍAS FESTIVOS (HOLIDAYS)
     * =============================================
     */

    /**
     * Obtener lista de días festivos
     */
    public function listHolidays(Request $request)
    {
        $year = $request->input('year', Carbon::now()->year);

        $holidays = Holiday::where(function($query) use ($year) {
                // Obtener días festivos específicos para este año
                $query->whereYear('date', $year)
                    // O días festivos recurrentes (que se repiten cada año)
                    ->orWhere('is_recurring', true);
            })
            ->orderBy('date')
            ->get();

        // Formatear la salida para mostrar la fecha correcta para los recurrentes
        $formattedHolidays = $holidays->map(function($holiday) use ($year) {
            $data = $holiday->toArray();

            if ($holiday->is_recurring) {
                // Para los recurrentes, mostrar la fecha en el año solicitado
                $data['display_date'] = Carbon::createFromDate(
                    $year,
                    $holiday->recurring_month,
                    $holiday->recurring_day
                )->format('Y-m-d');
            } else {
                $data['display_date'] = $holiday->date->format('Y-m-d');
            }

            return $data;
        });

        return response()->json([
            'year' => $year,
            'holidays' => $formattedHolidays
        ]);
    }

    /**
     * Crear un nuevo día festivo
     */
    public function storeHoliday(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'description' => 'required|string|max:255',
            'is_recurring' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Procesar datos para día festivo
        $holidayData = $request->only(['date', 'description', 'is_recurring']);

        // Si es recurrente, extraer mes y día
        if ($request->input('is_recurring', false)) {
            $dateObj = Carbon::parse($request->date);
            $holidayData['recurring_month'] = $dateObj->month;
            $holidayData['recurring_day'] = $dateObj->day;
        }

        $holiday = Holiday::create($holidayData);

        return response()->json([
            'message' => 'Día festivo creado exitosamente',
            'holiday' => $holiday
        ], 201);
    }

    /**
     * Mostrar detalles de un día festivo específico
     */
    public function showHoliday($id)
    {
        $holiday = Holiday::findOrFail($id);
        return response()->json($holiday);
    }

    /**
     * Actualizar un día festivo existente
     */
    public function updateHoliday(Request $request, $id)
    {
        $holiday = Holiday::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'date' => 'sometimes|required|date',
            'description' => 'sometimes|required|string|max:255',
            'is_recurring' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Actualizar data básica
        if ($request->has('date')) {
            $holiday->date = $request->date;
        }

        if ($request->has('description')) {
            $holiday->description = $request->description;
        }

        if ($request->has('is_recurring')) {
            $holiday->is_recurring = $request->is_recurring;
        }

        // Si es recurrente, actualizar mes y día
        if ($holiday->is_recurring) {
            $dateObj = Carbon::parse($holiday->date);
            $holiday->recurring_month = $dateObj->month;
            $holiday->recurring_day = $dateObj->day;
        } else {
            $holiday->recurring_month = null;
            $holiday->recurring_day = null;
        }

        $holiday->save();

        return response()->json([
            'message' => 'Día festivo actualizado exitosamente',
            'holiday' => $holiday
        ]);
    }

    /**
     * Eliminar un día festivo
     */
    public function deleteHoliday($id)
    {
        $holiday = Holiday::findOrFail($id);
        $holiday->delete();

        return response()->json([
            'message' => 'Día festivo eliminado exitosamente'
        ]);
    }

    /**
     * =============================================
     * MÉTODOS PARA PERMISOS (PERMISSIONS)
     * =============================================
     */

    /**
     * Obtener lista de permisos con filtros
     */
    public function listPermissions(Request $request)
    {
        $query = Permission::with('employee');

        // Filtrar por empleado si se proporciona
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        // Filtrar por tipo de permiso
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filtrar por rango de fechas
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        } elseif ($request->has('start_date')) {
            $query->where('date', '>=', $request->start_date);
        } elseif ($request->has('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }

        // Ordenar resultados
        $query->orderBy('date', 'desc');

        // Paginar resultados si se solicita
        if ($request->has('per_page')) {
            $permissions = $query->paginate($request->per_page);
        } else {
            $permissions = $query->get();
        }

        return response()->json($permissions);
    }

    /**
     * Crear un nuevo permiso
     */
    public function storePermission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'checker_uid' => 'nullable|string',
            'date' => 'required|date',
            'reason' => 'required|string',
            'type' => [
                'required',
                Rule::in(['personal', 'médico', 'vacaciones', 'capacitación', 'otro', 'home office']),
            ],
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'approved_by' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Si no se proporcionó checker_uid, intentar obtenerlo del empleado
        if (!$request->has('checker_uid') || empty($request->checker_uid)) {
            $employee = Employee::find($request->employee_id);
            if ($employee && !empty($employee->checker_uid)) {
                $request->merge(['checker_uid' => $employee->checker_uid]);
            }
        }

        $permission = Permission::create($request->all());

        return response()->json([
            'message' => 'Permiso creado exitosamente',
            'permission' => $permission
        ], 201);
    }

    /**
     * Mostrar detalles de un permiso específico
     */
    public function showPermission($id)
    {
        $permission = Permission::with('employee')->findOrFail($id);
        return response()->json($permission);
    }

    /**
     * Actualizar un permiso existente
     */
    public function updatePermission(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'employee_id' => 'sometimes|required|exists:employees,id',
            'checker_uid' => 'nullable|string',
            'date' => 'sometimes|required|date',
            'reason' => 'sometimes|required|string',
            'type' => [
                'sometimes',
                'required',
                Rule::in(['personal', 'médico', 'vacaciones', 'capacitación', 'otro', 'home office']),
            ],
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'approved_by' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Si se cambia el empleado y no se proporciona checker_uid, intentar obtenerlo
        if ($request->has('employee_id') && $request->employee_id != $permission->employee_id &&
            (!$request->has('checker_uid') || empty($request->checker_uid))) {

            $employee = Employee::find($request->employee_id);
            if ($employee && !empty($employee->checker_uid)) {
                $request->merge(['checker_uid' => $employee->checker_uid]);
            }
        }

        $permission->update($request->all());

        return response()->json([
            'message' => 'Permiso actualizado exitosamente',
            'permission' => $permission
        ]);
    }

    /**
     * Eliminar un permiso
     */
    public function deletePermission($id)
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();

        return response()->json([
            'message' => 'Permiso eliminado exitosamente'
        ]);
    }

    /**
     * Crear múltiples permisos (útil para vacaciones)
     */
    public function storeBulkPermissions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string',
            'type' => [
                'required',
                Rule::in(['personal', 'médico', 'vacaciones', 'capacitación', 'otro', 'home office']),
            ],
            'approved_by' => 'nullable|string|max:255',
            'skip_weekends' => 'boolean',
            'skip_holidays' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Obtener el empleado para su checker_uid
        $employee = Employee::find($request->employee_id);
        $checkerUid = !empty($employee->checker_uid) ? $employee->checker_uid : null;

        // Generar todas las fechas en el rango
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $currentDate = clone $startDate;

        $createdPermissions = [];
        $skippedDates = [];

        // Si debemos omitir días festivos, obtenerlos
        $holidays = [];
        if ($request->input('skip_holidays', false)) {
            $holidays = Holiday::whereBetween('date', [$startDate, $endDate])
                ->orWhere(function($query) use ($startDate, $endDate) {
                    // También considerar días festivos recurrentes
                    $query->where('is_recurring', true)
                        ->whereRaw('recurring_month BETWEEN ? AND ?', [
                            $startDate->month,
                            $endDate->month
                        ]);
                })
                ->get();
        }

        while ($currentDate <= $endDate) {
            $skipDate = false;
            $skipReason = null;

            // Verificar si debemos omitir fines de semana
            if ($request->input('skip_weekends', false) && $currentDate->isWeekend()) {
                $skipDate = true;
                $skipReason = 'Fin de semana';
            }

            // Verificar si debemos omitir días festivos
            if (!$skipDate && $request->input('skip_holidays', false)) {
                foreach ($holidays as $holiday) {
                    if ($holiday->matchesDate($currentDate)) {
                        $skipDate = true;
                        $skipReason = "Día festivo: {$holiday->description}";
                        break;
                    }
                }
            }

            if ($skipDate) {
                $skippedDates[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'reason' => $skipReason
                ];
            } else {
                // Crear el permiso para esta fecha
                $permission = Permission::create([
                    'employee_id' => $request->employee_id,
                    'checker_uid' => $checkerUid,
                    'date' => $currentDate->format('Y-m-d'),
                    'reason' => $request->reason,
                    'type' => $request->type,
                    'approved_by' => $request->approved_by,
                ]);

                $createdPermissions[] = $permission;
            }

            $currentDate->addDay();
        }

        return response()->json([
            'message' => 'Permisos creados exitosamente',
            'total_created' => count($createdPermissions),
            'total_skipped' => count($skippedDates),
            'permissions' => $createdPermissions,
            'skipped_dates' => $skippedDates
        ], 201);
    }
}
