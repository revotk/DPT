<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('zteko', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45); // Soporta IPv4 e IPv6
            $table->integer('port');
            $table->string('description')->nullable();
            $table->string('device_version')->nullable();
            $table->string('device_os_version')->nullable();
            $table->string('platform')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('work_code')->nullable();
            $table->string('serial_number')->unique();
            $table->string('device_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zteko');
    }
};
