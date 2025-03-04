<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->integer('device');
            $table->string('uid')->nullable();
            $table->dateTime('date');
            $table->timestamps();

            // Índice único para evitar duplicados
            $table->unique(['device', 'uid', 'date']);

            // Índice para búsquedas frecuentes
            $table->index(['device', 'date']);
            $table->index(['uid', 'date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendances');
    }
};
