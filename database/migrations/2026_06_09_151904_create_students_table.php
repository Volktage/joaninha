<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $row) {
            $row->id();
            $row->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $row->string('name');
            $row->string('registration_number')->unique();
            $row->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
