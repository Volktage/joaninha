<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data de nascimento, responsável e informações de saúde do aluno.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('registration_number');
            $table->string('guardian_name')->nullable()->after('birth_date');
            $table->string('guardian_relationship')->nullable()->after('guardian_name');
            $table->string('guardian_phone', 20)->nullable()->after('guardian_relationship');
            $table->string('guardian_email')->nullable()->after('guardian_phone');
            $table->string('allergies')->nullable()->after('guardian_email');
            $table->text('health_notes')->nullable()->after('allergies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'birth_date', 'guardian_name', 'guardian_relationship', 'guardian_phone',
                'guardian_email', 'allergies', 'health_notes',
            ]);
        });
    }
};
