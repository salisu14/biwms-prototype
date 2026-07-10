<?php

declare(strict_types=1);

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
        if (! Schema::hasTable('employee_id_card_templates')) {
            Schema::create('employee_id_card_templates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->string('name');
                $table->string('orientation')->default('portrait');
                $table->decimal('width_mm', 8, 2)->default(85.60);
                $table->decimal('height_mm', 8, 2)->default(53.98);
                $table->json('colors')->nullable();
                $table->json('placement_presets')->nullable();
                $table->json('visible_fields')->nullable();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['business_id', 'is_default', 'is_active']);
            });
        }

        if (! Schema::hasTable('employee_id_cards')) {
            Schema::create('employee_id_cards', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->foreignId('template_id')->nullable()->constrained('employee_id_card_templates')->nullOnDelete();
                $table->string('card_number')->unique();
                $table->string('token')->unique();
                $table->string('status')->default('draft');
                $table->date('issue_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('issued_at')->nullable();
                $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('printed_at')->nullable();
                $table->unsignedInteger('print_count')->default(0);
                $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('revoked_at')->nullable();
                $table->text('revocation_reason')->nullable();
                $table->foreignId('replaced_card_id')->nullable()->constrained('employee_id_cards')->nullOnDelete();
                $table->timestamp('last_verified_at')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'status']);
                $table->index(['business_id', 'status']);
                $table->index(['template_id', 'status']);
            });
        }

        if (! Schema::hasTable('employee_id_card_print_batches')) {
            Schema::create('employee_id_card_print_batches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->foreignId('template_id')->nullable()->constrained('employee_id_card_templates')->nullOnDelete();
                $table->string('batch_number')->unique();
                $table->string('layout')->default('single');
                $table->string('status')->default('draft');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('printed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('employee_id_card_print_batch_items')) {
            Schema::create('employee_id_card_print_batch_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('batch_id')->constrained('employee_id_card_print_batches')->cascadeOnDelete();
                $table->foreignId('card_id')->constrained('employee_id_cards')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('status')->default('pending');
                $table->timestamp('printed_at')->nullable();
                $table->timestamps();

                $table->unique(['batch_id', 'card_id']);
            });
        }

        if (! Schema::hasTable('employee_id_card_histories')) {
            Schema::create('employee_id_card_histories', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('card_id')->nullable()->constrained('employee_id_cards')->cascadeOnDelete();
                $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event');
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->timestamps();

                $table->index(['card_id', 'event']);
                $table->index(['employee_id', 'event']);
            });
        }

        if (! Schema::hasTable('employee_id_card_verification_logs')) {
            Schema::create('employee_id_card_verification_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('card_id')->nullable()->constrained('employee_id_cards')->nullOnDelete();
                $table->timestamp('verified_at');
                $table->string('result');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('device')->nullable();
                $table->string('location_code')->nullable();
                $table->timestamps();

                $table->index(['card_id', 'verified_at']);
                $table->index(['result', 'verified_at']);
            });
        }

        $this->seedDefaultTemplate();
        $this->backfillEmployeeCards();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_id_card_verification_logs');
        Schema::dropIfExists('employee_id_card_histories');
        Schema::dropIfExists('employee_id_card_print_batch_items');
        Schema::dropIfExists('employee_id_card_print_batches');
        Schema::dropIfExists('employee_id_cards');
        Schema::dropIfExists('employee_id_card_templates');
    }

    private function seedDefaultTemplate(): void
    {
        if (! Schema::hasTable('employee_id_card_templates')) {
            return;
        }

        DB::table('employee_id_card_templates')->updateOrInsert(
            ['name' => 'Standard Employee ID Card', 'business_id' => null],
            [
                'orientation' => 'portrait',
                'width_mm' => 85.60,
                'height_mm' => 53.98,
                'colors' => json_encode([
                    'header' => '#172033',
                    'accent' => '#0f766e',
                    'badge' => '#fef3c7',
                ]),
                'placement_presets' => json_encode([
                    'logo' => 'header_left',
                    'photo' => 'body_left',
                    'qr' => 'footer_right',
                ]),
                'visible_fields' => json_encode([
                    'employee_number',
                    'full_name',
                    'department',
                    'job_title',
                    'issue_date',
                    'expiry_date',
                ]),
                'is_default' => true,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    private function backfillEmployeeCards(): void
    {
        if (! Schema::hasTable('employee_id_cards') || ! Schema::hasTable('employees')) {
            return;
        }

        if (! Schema::hasColumn('employees', 'id_card_number') || ! Schema::hasColumn('employees', 'id_card_token')) {
            return;
        }

        $defaultTemplateId = DB::table('employee_id_card_templates')
            ->where('is_default', true)
            ->value('id');

        DB::table('employees')
            ->whereNotNull('id_card_number')
            ->whereNotNull('id_card_token')
            ->orderBy('id')
            ->get()
            ->each(function (object $employee) use ($defaultTemplateId): void {
                DB::table('employee_id_cards')->updateOrInsert(
                    ['card_number' => $employee->id_card_number],
                    [
                        'employee_id' => $employee->id,
                        'business_id' => null,
                        'template_id' => $defaultTemplateId,
                        'token' => $employee->id_card_token,
                        'status' => $employee->id_card_status ?: 'active',
                        'issue_date' => $employee->id_card_issue_date,
                        'expiry_date' => $employee->id_card_expiry_date,
                        'issued_at' => $employee->id_card_issue_date ? now() : null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            });
    }
};
