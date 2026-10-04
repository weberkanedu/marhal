<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender');
            $table->date('birth_date')->nullable();
            $table->string('nationality', 2)->default('TR');
            $table->text('national_id')->nullable();
            $table->string('national_id_hash', 64)->nullable();
            $table->text('passport_no')->nullable();
            $table->string('passport_no_hash', 64)->nullable();
            $table->date('passport_issue_date')->nullable();
            $table->date('passport_expiry_date')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('kvkk_consent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'last_name', 'first_name']);
            $table->unique(['tenant_id', 'national_id_hash']);
            $table->index(['tenant_id', 'passport_no_hash']);
        });

        Schema::create('person_relations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignUuid('related_person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('relation');
            $table->timestamps();

            $table->unique(['person_id', 'related_person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_relations');
        Schema::dropIfExists('persons');
    }
};
