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
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('description');
            $table->boolean('is_recurring')->default(false)->comment('Si es true, se repite cada año');
            $table->integer('recurring_month')->nullable()->comment('Mes de repetición (1-12)');
            $table->integer('recurring_day')->nullable()->comment('Día de repetición (1-31)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
