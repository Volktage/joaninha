<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Calendário escolar: feriados, recessos, reuniões, festas e demais eventos (da escola toda ou de uma turma).
     */
    public function up(): void
    {
        Schema::create('school_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->comment('holiday, recess, pedagogical_day, parents_meeting, class_council, celebration, field_trip ou other');
            $table->date('starts_on');
            $table->date('ends_on')->nullable()->comment('Último dia, para eventos de vários dias');
            $table->foreignId('classroom_id')->nullable()->constrained()->cascadeOnDelete()->comment('Vazio = escola toda');
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('school_events');
    }
};
