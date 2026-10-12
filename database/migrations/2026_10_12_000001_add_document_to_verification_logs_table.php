<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verifications raised by uploading the certificate itself.
 *
 * Older Myanmar-language degrees often cannot be keyed into the typed search
 * form (the graduate's name may be transliterated inconsistently, or the
 * registrar's archive is the only source of truth). Those come in as an
 * uploaded scan instead, so the log needs somewhere to point at the file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_logs', function (Blueprint $table) {
            // Path relative to the backend (e.g. /uploads/verification-documents/..),
            // never an APP_URL-based absolute URL — same rule as student photos.
            $table->string('document_url')->nullable();
            // Original filename, kept so the registrar can recognise the scan.
            $table->string('document_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('verification_logs', function (Blueprint $table) {
            $table->dropColumn(['document_url', 'document_name']);
        });
    }
};
