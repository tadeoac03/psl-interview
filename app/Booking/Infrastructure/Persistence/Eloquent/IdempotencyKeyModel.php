<?php

namespace App\Booking\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKeyModel extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'idempotency_keys';

    protected $fillable = ['member_id', 'key', 'request_hash', 'status_code', 'response_body', 'created_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'status_code' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
