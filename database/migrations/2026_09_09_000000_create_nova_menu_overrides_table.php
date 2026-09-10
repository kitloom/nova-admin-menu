<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('nova-menu-weights.table', 'nova_menu_overrides');

        if (Schema::hasTable($table)) {
            return;
        }

        Schema::create($table, function (Blueprint $table) {
            $table->id();

            // Class name, menu_path:… or menu_label:… - see MenuIdentity.
            $table->string('identity')->unique();

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
        Schema::dropIfExists(config('nova-menu-weights.table', 'nova_menu_overrides'));
    }
};
