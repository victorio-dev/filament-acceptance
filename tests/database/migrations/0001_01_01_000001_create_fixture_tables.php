<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixture_users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('fixture_tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('fixture_int_subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('fixture_soft_subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fixture_ulid_subjects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('fixture_uuid_subjects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixture_uuid_subjects');
        Schema::dropIfExists('fixture_ulid_subjects');
        Schema::dropIfExists('fixture_soft_subjects');
        Schema::dropIfExists('fixture_int_subjects');
        Schema::dropIfExists('fixture_tenants');
        Schema::dropIfExists('fixture_users');
    }
};
