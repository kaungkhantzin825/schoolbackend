<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A third account role: an approved verifier-organization user who
        // gets their own dashboard (Verified Records / Pending Requests).
        DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin','university_admin','verifier') NOT NULL DEFAULT 'university_admin'");

        // A verification attempt can now sit as "pending" (queued for manual
        // registrar review) instead of only success/failed.
        DB::statement("ALTER TABLE verification_logs MODIFY status ENUM('success','failed','pending') NOT NULL DEFAULT 'failed'");

        Schema::table('verification_logs', function (Blueprint $table) {
            $table->string('request_ref')->nullable()->after('id');
            $table->timestamp('sla_due_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('verification_logs', function (Blueprint $table) {
            $table->dropColumn(['request_ref', 'sla_due_at']);
        });

        DB::statement("ALTER TABLE verification_logs MODIFY status ENUM('success','failed') NOT NULL DEFAULT 'failed'");
        DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin','university_admin') NOT NULL DEFAULT 'university_admin'");
    }
};
