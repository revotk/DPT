<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'date',
        'description',
        'is_recurring',
        'recurring_month',
        'recurring_day',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
        'recurring_month' => 'integer',
        'recurring_day' => 'integer',
    ];

    /**
     * Verificar si el día festivo corresponde a una fecha específica
     * (considerando repetición anual si aplica)
     *
     * @param \Carbon\Carbon|string $date
     * @return bool
     */
    public function matchesDate($date)
    {
        $checkDate = \Carbon\Carbon::parse($date);

        // Si es un día festivo específico (no recurrente)
        if (!$this->is_recurring) {
            return $this->date->isSameDay($checkDate);
        }

        // Si es recurrente, verificar solo mes y día
        return $this->recurring_month == $checkDate->month &&
               $this->recurring_day == $checkDate->day;
    }

    /**
     * Scope para filtrar días festivos que aplican a una fecha específica
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon|string $date
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeApplicableToDate($query, $date)
    {
        $checkDate = \Carbon\Carbon::parse($date);

        return $query->where(function ($query) use ($checkDate) {
            // Días festivos específicos para esta fecha exacta
            $query->where('date', $checkDate->format('Y-m-d'))
                // O días festivos recurrentes que coinciden con mes y día
                ->orWhere(function ($query) use ($checkDate) {
                    $query->where('is_recurring', true)
                          ->where('recurring_month', $checkDate->month)
                          ->where('recurring_day', $checkDate->day);
                });
        });
    }
}
