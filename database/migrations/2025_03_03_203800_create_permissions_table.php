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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->string('checker_uid')->nullable()->comment('ID interno del usuario en el dispositivo checador');
            $table->date('date');
            $table->string('reason');
            $table->enum('type', ['personal', 'médico', 'vacaciones', 'capacitación', 'otro'])->default('personal');
            $table->time('start_time')->nullable()->comment('Hora de inicio del permiso (opcional)');
            $table->time('end_time')->nullable()->comment('Hora de finalización del permiso (opcional)');
            $table->string('approved_by')->nullable()->comment('Persona que aprobó el permiso');
            $table->timestamps();

            // Índice compuesto para búsquedas eficientes
            $table->index(['employee_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
