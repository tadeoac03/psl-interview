<?php

namespace App\Booking\Application\Shared;

final readonly class StoredResponse
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public string $requestHash,
        public int $statusCode,
        public array $body,
    ) {}
}
