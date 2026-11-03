<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('member_id', 100);
            $table->string('key', 255);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('status_code');
            $table->json('response_body');
            $table->timestampTz('created_at')->index();

            $table->unique(['member_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
