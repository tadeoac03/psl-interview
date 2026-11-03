<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name', 100);
            $table->timestampTz('starts_at')->index();
            $table->timestampTz('ends_at');
            $table->unsignedInteger('capacity');
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE classes ADD CONSTRAINT classes_valid_schedule CHECK (ends_at > starts_at)');
            DB::statement('ALTER TABLE classes ADD CONSTRAINT classes_positive_capacity CHECK (capacity > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
