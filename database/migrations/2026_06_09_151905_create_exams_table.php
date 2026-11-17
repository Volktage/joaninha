<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $row) {
            $row->id();
            $row->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $row->string('title');
            $row->date('applied_at');
            $row->string('file_path')->nullable();
            $row->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
