<?php

namespace App\Booking\Infrastructure\Http\Requests;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;

final class CreateClassRequest extends FormRequest
{
    private const UTC_FORMATS = 'date_format:Y-m-d\TH:i:s\Z,Y-m-d\TH:i:s.v\Z';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'start' => ['required', self::UTC_FORMATS],
            'end' => ['required', self::UTC_FORMATS, 'after:start'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function start(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->validated('start'), new DateTimeZone('UTC'));
    }

    public function end(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->validated('end'), new DateTimeZone('UTC'));
    }
}
