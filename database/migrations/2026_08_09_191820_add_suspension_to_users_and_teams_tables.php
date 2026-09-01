<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suspension gives moderators a reversible alternative to deletion: a
     * suspended account keeps all of its data but cannot sign in or publish.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->index();
            $table->string('suspension_reason')->nullable();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->index();
            $table->string('suspension_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });
    }
};
