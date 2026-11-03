<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('member_id', 100);
            $table->string('status', 16);
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();

            $table->index(['class_id', 'status', 'id']);
            $table->index(['member_id', 'status']);
        });

        /** A member holds at most one active (confirmed or waitlisted) booking per class; cancelled ones don't count. */
        DB::statement("CREATE UNIQUE INDEX bookings_one_active_per_member_and_class ON bookings (class_id, member_id) WHERE status <> 'cancelled'");
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
