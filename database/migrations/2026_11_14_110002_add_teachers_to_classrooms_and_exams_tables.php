<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Professora regente da turma e professor(a) responsável por cada avaliação.
     */
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->foreignId('homeroom_teacher_id')->nullable()->after('period')->constrained('teachers')->nullOnDelete();
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->after('classroom_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teacher_id');
        });

        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('homeroom_teacher_id');
        });
    }
};
