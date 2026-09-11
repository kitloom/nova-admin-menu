<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('nova-menu-weights.table', 'nova_menu_overrides');

        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'type')) {
            return;
        }

        Schema::table($table, function (Blueprint $table) {
            // Set only on entries this package creates itself: section, group
            // or item. Null means the row only overrides something the
            // application already renders.
            $table->string('type', 16)->nullable()->after('identity');
            $table->string('path')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        $table = config('nova-menu-weights.table', 'nova_menu_overrides');

        if (Schema::hasTable($table) && Schema::hasColumn($table, 'type')) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn(['type', 'path']));
        }
    }
};
