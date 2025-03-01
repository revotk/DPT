<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zteko extends Model
{
    protected $table = 'zteko'; // Nombre de la tabla en la BD

    protected $fillable = [
        'ip',
        'port',
        'description',
        'device_version',
        'device_os_version',
        'platform',
        'firmware_version',
        'work_code',
        'serial_number',
        'device_name',
    ];
}
