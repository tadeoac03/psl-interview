<?php

namespace App\Booking\Infrastructure\Http\Requests;

use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;

final class ListClassesRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'tz' => ['nullable', 'timezone:all'],
        ];
    }

    public function localDate(): ?string
    {
        return $this->validated('date');
    }

    public function timeZone(): DateTimeZone
    {
        return new DateTimeZone($this->validated('tz') ?? config('studio.timezone'));
    }
}
