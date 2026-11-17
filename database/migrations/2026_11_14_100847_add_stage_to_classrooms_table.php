<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->string('stage')->default('elementary')->after('name')->comment('Etapa de ensino: early_childhood ou elementary');
        });

        DB::table('classrooms')
            ->where(function ($query) {
                foreach (['Berçário%', 'Maternal%', 'Pré%', 'Jardim%'] as $prefix) {
                    $query->orWhere('name', 'like', $prefix);
                }
            })
            ->update(['stage' => 'early_childhood']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('stage');
        });
    }
};
