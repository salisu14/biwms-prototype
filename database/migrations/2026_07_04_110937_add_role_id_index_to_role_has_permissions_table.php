<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('role_has_permissions')) {
            return;
        }

        Schema::table('role_has_permissions', function (Blueprint $table): void {
            if (! Schema::hasIndex('role_has_permissions', 'role_has_permissions_role_id_index')) {
                $table->index('role_id', 'role_has_permissions_role_id_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('role_has_permissions')) {
            return;
        }

        Schema::table('role_has_permissions', function (Blueprint $table): void {
            if (Schema::hasIndex('role_has_permissions', 'role_has_permissions_role_id_index')) {
                $table->dropIndex('role_has_permissions_role_id_index');
            }
        });
    }
};
