<?php

declare(strict_types=1);

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
        if (! Schema::hasColumn('employees', 'photo_path')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->string('photo_path')->nullable()->after('phone');
            });
        }

        if (! Schema::hasColumn('employees', 'id_card_number')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->string('id_card_number')->nullable()->unique()->after('is_active');
            });
        }

        if (! Schema::hasColumn('employees', 'id_card_issue_date')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->date('id_card_issue_date')->nullable()->after('id_card_number');
            });
        }

        if (! Schema::hasColumn('employees', 'id_card_expiry_date')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->date('id_card_expiry_date')->nullable()->after('id_card_issue_date');
            });
        }

        if (! Schema::hasColumn('employees', 'id_card_status')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->string('id_card_status')->nullable()->default('active')->after('id_card_expiry_date');
            });
        }

        if (! Schema::hasColumn('employees', 'id_card_token')) {
            Schema::table('employees', function (Blueprint $table): void {
                $table->string('id_card_token')->nullable()->unique()->after('id_card_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            foreach ([
                'photo_path',
                'id_card_number',
                'id_card_issue_date',
                'id_card_expiry_date',
                'id_card_status',
                'id_card_token',
            ] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
