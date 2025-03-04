<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'device',
        'uid',
        'date'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'device' => 'integer',
        'date' => 'datetime',
    ];

    /**
     * Get the employee that owns this attendance record
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'uid', 'checker_uid');
    }

    /**
     * Relación con el dispositivo ZKTeco
     */
    public function zteko()
    {
        return $this->belongsTo(Zteko::class, 'device', 'id');
    }

    /**
     * Determine if this is an entry record
     * (First record of the day for the employee)
     */
    public function isEntry()
    {
        $firstRecord = self::where('uid', $this->uid)
            ->whereDate('date', $this->date->toDateString())
            ->orderBy('date', 'asc')
            ->first();

        return $firstRecord && $firstRecord->id === $this->id;
    }

    /**
     * Determine if this is an exit record
     * (Last record of the day for the employee)
     */
    public function isExit()
    {
        $lastRecord = self::where('uid', $this->uid)
            ->whereDate('date', $this->date->toDateString())
            ->orderBy('date', 'desc')
            ->first();

        return $lastRecord && $lastRecord->id === $this->id;
    }

    /**
     * Scope a query to only include attendance records within date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [
            Carbon::parse($startDate)->startOfDay(),
            Carbon::parse($endDate)->endOfDay()
        ]);
    }

    /**
     * Get all entry records (first record of each day)
     */
    public static function getEntryRecords($uid, $startDate, $endDate)
    {
        return self::select('attendances.*')
            ->join(\DB::raw('(
                SELECT DATE(date) as day, MIN(date) as min_date
                FROM attendances
                WHERE uid = ?
                AND DATE(date) BETWEEN ? AND ?
                GROUP BY DATE(date)
            ) as first_records'), function($join) {
                $join->on(\DB::raw('DATE(attendances.date)'), '=', 'first_records.day')
                     ->on('attendances.date', '=', 'first_records.min_date');
            })
            ->where('attendances.uid', $uid)
            ->setBindings([$uid, $startDate, $endDate], 'join')
            ->orderBy('attendances.date')
            ->get();
    }

    /**
     * Get all exit records (last record of each day)
     */
    public static function getExitRecords($uid, $startDate, $endDate)
    {
        return self::select('attendances.*')
            ->join(\DB::raw('(
                SELECT DATE(date) as day, MAX(date) as max_date
                FROM attendances
                WHERE uid = ?
                AND DATE(date) BETWEEN ? AND ?
                GROUP BY DATE(date)
            ) as last_records'), function($join) {
                $join->on(\DB::raw('DATE(attendances.date)'), '=', 'last_records.day')
                     ->on('attendances.date', '=', 'last_records.max_date');
            })
            ->where('attendances.uid', $uid)
            ->setBindings([$uid, $startDate, $endDate], 'join')
            ->orderBy('attendances.date')
            ->get();
    }
}
