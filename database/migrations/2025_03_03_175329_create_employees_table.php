<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('user')->nullable();
            $table->string('rfc')->nullable();
            $table->string('phone')->nullable();
            $table->string('position')->nullable();
            $table->string('adscription')->nullable();
            $table->string('entry_time')->nullable();
            $table->string('exit_time')->nullable();
            $table->string('status')->nullable();
            $table->string('fullname')->nullable();
            $table->string('curp')->nullable();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('lastname')->nullable();
            $table->unsignedBigInteger('checker_uid')->nullable()->comment('UID that maps to attendance records');
            $table->unsignedBigInteger('checker_id')->nullable()->comment('ID of the checker device');
            $table->timestamps();

            // Añadir índices para mejorar el rendimiento
            $table->index('checker_uid');
            $table->index('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
