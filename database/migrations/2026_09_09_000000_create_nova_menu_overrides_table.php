<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('nova-admin-menu.table', 'nova_menu_overrides');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) {
            $table->id();

            // Class name, menu_path:… or menu_label:… - see MenuIdentity.
            $table->string('identity')->unique();

            // Set only on entries this package creates itself: section, group
            // or item, and where a link points. Null on a row that merely
            // overrides something the application already renders.
            $table->string('type', 16)->nullable();
            $table->string('path')->nullable();

            $table->integer('weight')->nullable();
            $table->string('parent_identity')->nullable()->index();
            $table->string('label')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('hidden')->default(false);

            // Opaque to this package: whatever the application's authorizer
            // makes of them. Empty means no restriction.
            $table->json('permissions')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('nova-admin-menu.table', 'nova_menu_overrides'));
    }
};
