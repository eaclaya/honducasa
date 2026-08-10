<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only record of every moderation action and impersonation session.
     *
     * Rows are never updated or deleted, so there is no `updated_at`. The
     * subject is nullable because the record it points at may later be hard
     * deleted by a data-retention job while the audit entry must survive.
     */
    public function up(): void
    {
        Schema::create('admin_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('action')->index();
            $table->nullableMorphs('subject');
            $table->string('reason')->nullable();
            $table->jsonb('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activities');
    }
};
