<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Público da Educação Especial (atendido pelo AEE) e as adaptações combinadas para o aluno.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('special_education')->nullable()->after('health_notes')
                ->comment('autism, intellectual, physical, hearing, visual ou gifted');
            $table->text('special_education_notes')->nullable()->after('special_education');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['special_education', 'special_education_notes']);
        });
    }
};
