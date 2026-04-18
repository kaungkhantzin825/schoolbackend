<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['super_admin', 'university_admin'])->default('university_admin')->after('email');
            $table->foreignId('university_id')->nullable()->constrained()->onDelete('cascade')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['university_id']);
            $table->dropColumn(['role', 'university_id']);
        });
    }
};
