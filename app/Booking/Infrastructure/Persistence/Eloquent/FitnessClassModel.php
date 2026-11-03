<?php

namespace App\Booking\Infrastructure\Persistence\Eloquent;

use Database\Factories\FitnessClassModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property int $capacity
 * @property Carbon|null $cancelled_at
 */
class FitnessClassModel extends Model
{
    /** @use HasFactory<FitnessClassModelFactory> */
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = ['uuid', 'name', 'starts_at', 'ends_at', 'capacity', 'cancelled_at'];

    protected static function newFactory(): FitnessClassModelFactory
    {
        return FitnessClassModelFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'capacity' => 'integer',
        ];
    }

    /**
     * @return HasMany<BookingModel, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(BookingModel::class, 'class_id');
    }
}
