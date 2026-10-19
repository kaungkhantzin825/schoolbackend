<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give the registrar's decision its own column.
 *
 * Until now `resolve()` overwrote `notes` with the registrar's wording, which
 * destroyed the verifier's original enquiry note. Both are worth keeping: the
 * verifier should be able to see "what I asked" next to "why it was declined".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_logs', function (Blueprint $table) {
            $table->text('resolution_notes')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('verification_logs', function (Blueprint $table) {
            $table->dropColumn('resolution_notes');
        });
    }
};
