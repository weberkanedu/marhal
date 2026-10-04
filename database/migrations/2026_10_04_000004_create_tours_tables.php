<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('umre');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('taslak');
            $table->unsignedInteger('capacity')->nullable();
            $table->decimal('default_price', 12, 2)->nullable();
            $table->string('currency', 3);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status', 'end_date']);
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tour_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('guide_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('guide_name')->nullable();
            $table->string('guide_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tour_id', 'name']);
        });

        Schema::create('registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('person_id')->constrained('persons')->restrictOnDelete();
            $table->string('room_type')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->string('currency', 3);
            $table->string('status')->default('on_kayit');
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('registered_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tour_id', 'person_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('registration_id')->constrained()->restrictOnDelete();
            $table->string('type')->default('tahsilat');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->decimal('exchange_rate', 12, 6)->default(1);
            $table->decimal('amount_in_registration_currency', 12, 2);
            $table->string('method');
            $table->date('paid_at');
            $table->string('reference')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('registrations');
        Schema::dropIfExists('groups');
        Schema::dropIfExists('tours');
    }
};
