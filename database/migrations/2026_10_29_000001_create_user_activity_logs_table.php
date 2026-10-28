<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail of account activity.
 *
 * Separate from `verification_logs`, which records credential enquiries. This
 * table answers the different question "who signed in, from where, and when" —
 * the record a Super Admin needs to spot a hijacked account or a departing
 * employee still using shared credentials.
 *
 * Deliberately NOT a foreign-key-only table: a failed sign-in has no user row,
 * and the account details are denormalised so the trail stays readable after a
 * user is deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();

            // Null for a failed sign-in against an address that does not exist.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot of who acted — survives deletion of the account.
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('role', 32)->nullable();

            // login | login_failed | logout | password_reset_requested |
            // password_reset | password_changed | account_created |
            // registration_submitted
            $table->string('action', 40);
            $table->string('description')->nullable();

            $table->string('ip_address', 45)->nullable();   // IPv6-safe
            $table->string('country', 80)->nullable();
            $table->string('country_code', 4)->nullable();
            $table->string('city', 80)->nullable();

            // Coarse client fingerprint — enough to spot "this is not my
            // usual device" without storing anything that identifies a person.
            $table->string('device', 20)->nullable();
            $table->string('browser', 40)->nullable();
            $table->string('platform', 40)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // The log is only ever read newest-first, filtered by person or by
            // event type.
            $table->index(['user_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};
