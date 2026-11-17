<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Garante uma única nota por aluno em cada avaliação, removendo duplicatas antigas antes de criar o índice.
     */
    public function up(): void
    {
        $duplicateIds = DB::table('grades as duplicate')
            ->join('grades as original', function ($join) {
                $join->on('duplicate.student_id', '=', 'original.student_id')
                    ->on('duplicate.exam_id', '=', 'original.exam_id')
                    ->on('duplicate.id', '>', 'original.id');
            })
            ->pluck('duplicate.id');

        DB::table('grades')->whereIn('id', $duplicateIds)->delete();

        Schema::table('grades', function (Blueprint $table) {
            $table->unique(['student_id', 'exam_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropUnique(['student_id', 'exam_id']);
        });
    }
};
