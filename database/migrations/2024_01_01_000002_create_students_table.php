<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_id')->constrained()->onDelete('cascade');
            $table->string('graduate_name');
            $table->string('father_name');
            $table->enum('gender', ['Male', 'Female', 'Other']);
            $table->date('date_of_birth');
            $table->string('nrc_number')->unique();
            $table->string('student_id')->nullable();
            $table->string('degree');
            $table->string('specialization')->nullable();
            $table->year('graduation_year');
            $table->string('photo_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
