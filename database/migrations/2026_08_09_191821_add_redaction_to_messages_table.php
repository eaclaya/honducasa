<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Redaction hides an abusive message from participants without destroying
     * it: `body` is deliberately left intact so the message survives as
     * evidence for the report it came from.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('redacted_at')->nullable()->index();
            $table->foreignId('redacted_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('redacted_by');
            $table->dropColumn('redacted_at');
        });
    }
};
