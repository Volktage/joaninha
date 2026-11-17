<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $row) {
            $row->id();
            $row->foreignId('student_id')->constrained()->cascadeOnDelete();
            $row->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $row->decimal('score', 5, 2)->nullable();
            $row->text('comments')->nullable();
            $row->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
