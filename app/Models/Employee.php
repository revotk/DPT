<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Employee extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
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
        'checker_uid', // Este es el UID que se relaciona con los registros de asistencia
        'checker_id'   // Este es el ID del dispositivo checador
    ];

    /**
     * Obtener todos los registros de asistencia para este empleado
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'uid', 'checker_uid');
    }

    /**
     * Obtener el dispositivo checador para este empleado
     */
    public function checkerDevice()
    {
        return $this->belongsTo(Zteko::class, 'checker_id', 'id');
    }

    /**
     * Obtener los registros de asistencia para un rango de fechas específico
     *
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAttendanceRecords($startDate, $endDate)
    {
        return $this->attendances()
            ->whereBetween(DB::raw('DATE(date)'), [$startDate, $endDate])
            ->orderBy('date')
            ->get();
    }

    /**
     * Obtener asistencias por día (primera entrada y última salida) para un rango de fechas
     *
     * @param string $startDate Formato Y-m-d
     * @param string $endDate Formato Y-m-d
     * @return array
     */
    public function getDailyAttendanceSummary($startDate, $endDate)
    {
        // Convertir fechas a objetos Carbon si son strings
        if (is_string($startDate)) {
            $startDate = Carbon::parse($startDate)->format('Y-m-d');
        }

        if (is_string($endDate)) {
            $endDate = Carbon::parse($endDate)->format('Y-m-d');
        }

        $uid = $this->checker_uid;

        // Obtener entradas (primer registro del día)
        $firstEntries = Attendance::getEntryRecords($uid, $startDate, $endDate);

        // Obtener salidas (último registro del día)
        $lastExits = Attendance::getExitRecords($uid, $startDate, $endDate);

        // Combinar y organizar por día
        $summary = [];

        foreach ($firstEntries as $entry) {
            $day = $entry->date->format('Y-m-d');

            if (!isset($summary[$day])) {
                $summary[$day] = [
                    'date' => $day,
                    'entry' => null,
                    'exit' => null,
                    'entry_record' => null,
                    'exit_record' => null,
                    'working_hours' => null,
                ];
            }

            $summary[$day]['entry'] = $entry->date->format('H:i:s');
            $summary[$day]['entry_record'] = $entry;
        }

        foreach ($lastExits as $exit) {
            $day = $exit->date->format('Y-m-d');

            if (!isset($summary[$day])) {
                $summary[$day] = [
                    'date' => $day,
                    'entry' => null,
                    'exit' => null,
                    'entry_record' => null,
                    'exit_record' => null,
                    'working_hours' => null,
                ];
            }

            $summary[$day]['exit'] = $exit->date->format('H:i:s');
            $summary[$day]['exit_record'] = $exit;

            // Calcular horas trabajadas si hay entrada y salida
            if ($summary[$day]['entry'] && $summary[$day]['exit']) {
                $entryTime = Carbon::parse($day . ' ' . $summary[$day]['entry']);
                $exitTime = Carbon::parse($day . ' ' . $summary[$day]['exit']);

                // Asegurarse que la salida sea después de la entrada
                if ($exitTime->gt($entryTime)) {
                    $summary[$day]['working_hours'] = $exitTime->floatDiffInHours($entryTime);
                }
            }
        }

        return $summary;
    }

    /**
     * Obtener el estado de asistencia para una fecha específica
     *
     * @param string $date Formato Y-m-d
     * @return string|null
     */
    public function getAttendanceStatusForDate($date)
    {
        $attendances = $this->attendances()
            ->whereDate('date', $date)
            ->count();

        if ($attendances > 0) {
            return 'present';
        }

        return null; // No hay registros
    }

    /**
     * Obtener registros de asistencia formateados por mes
     *
     * @param int $year
     * @param int $month
     * @return array
     */
    public function getMonthlyAttendanceRecords($year, $month)
    {
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        $dailySummary = $this->getDailyAttendanceSummary($startDate, $endDate);

        // Inicializar array con todos los días del mes
        $result = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dayStr = $currentDate->format('Y-m-d');
            $dayNum = $currentDate->format('d'); // Formato dd

            if (isset($dailySummary[$dayStr])) {
                $result[$dayNum] = $dailySummary[$dayStr];
            } else {
                $result[$dayNum] = [
                    'date' => $dayStr,
                    'entry' => null,
                    'exit' => null,
                    'working_hours' => null,
                    'status' => $this->isDayOff($currentDate) ? 'day_off' : 'absent'
                ];
            }

            $currentDate->addDay();
        }

        return $result;
    }

    /**
     * Verificar si un día específico es día libre (fin de semana o festivo)
     *
     * @param \Carbon\Carbon $date
     * @return bool
     */
    private function isDayOff(Carbon $date)
    {
        // Verificar si es fin de semana (0 domingo, 6 sábado)
        if ($date->dayOfWeek === 0 || $date->dayOfWeek === 6) {
            return true;
        }

        // Aquí podrías agregar lógica para verificar días festivos

        return false;
    }
}
