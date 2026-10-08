<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adsterra Banner / Native Banner codes per ad position.
 * Additive only: existing AdSense slot data is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_slots', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_slots', 'adsterra_enabled')) {
                $table->boolean('adsterra_enabled')->default(false)->after('paragraph_offset');
            }
            if (! Schema::hasColumn('ad_slots', 'adsterra_code')) {
                $table->text('adsterra_code')->nullable()->after('adsterra_enabled');
            }
            if (! Schema::hasColumn('ad_slots', 'adsterra_code_mobile')) {
                $table->text('adsterra_code_mobile')->nullable()->after('adsterra_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ad_slots', function (Blueprint $table) {
            $table->dropColumn(['adsterra_enabled', 'adsterra_code', 'adsterra_code_mobile']);
        });
    }
};
