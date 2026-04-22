<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('degrees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('university_id')->constrained()->onDelete('cascade');
            $table->string('code'); // e.g., MBBS, M.Med.Sc, B.E
            $table->string('name'); // e.g., Bachelor of Medicine
            $table->text('description')->nullable();
            $table->enum('level', ['bachelor', 'master', 'doctorate'])->default('bachelor');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('degrees');
    }
};
