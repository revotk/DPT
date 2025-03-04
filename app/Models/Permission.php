<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'checker_uid',
        'date',
        'reason',
        'type',
        'start_time',
        'end_time',
        'approved_by',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    /**
     * Relación con el empleado
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope para buscar permisos en un rango de fechas
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Scope para buscar permisos por tipo
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope para buscar permisos por empleado
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope para buscar permisos por UID del checador
     */
    public function scopeForCheckerUid($query, $checkerUid)
    {
        return $query->where('checker_uid', $checkerUid);
    }
}
