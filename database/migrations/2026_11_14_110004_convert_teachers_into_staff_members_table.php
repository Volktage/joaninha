<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Os professores passam a fazer parte da equipe escolar, que também inclui direção, coordenação
     * e secretaria. Cada membro pode ter um login (user_id) cujas permissões dependem do cargo.
     * As chaves estrangeiras existentes (regente, especialistas e avaliações) seguem a tabela renomeada.
     */
    public function up(): void
    {
        Schema::rename('teachers', 'staff_members');

        Schema::table('staff_members', function (Blueprint $table) {
            $table->string('role')->default('teacher')->after('phone')->comment('direction, coordination, secretary ou teacher');
            $table->string('specialty')->nullable()->comment('Apenas professores: pedagogy, english, physical_education, arts ou music')->change();
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('role');
        });

        Schema::rename('staff_members', 'teachers');
    }
};
