<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('status')->default('draft')->after('listing_type');
            $table->timestamp('published_at')->nullable()->after('status');
            $table->index(['status', 'published_at']);
        });

        DB::table('properties')->update([
            'status' => 'published',
            'published_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['status', 'published_at']);
            $table->dropColumn(['status', 'published_at']);
        });
    }
};
