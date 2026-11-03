<?php

namespace App\Booking\Infrastructure\Persistence\Eloquent;

use App\Booking\Domain\Model\BookingStatus;
use Database\Factories\BookingModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $class_id
 * @property string $member_id
 * @property BookingStatus $status
 * @property Carbon|null $cancelled_at
 */
class BookingModel extends Model
{
    /** @use HasFactory<BookingModelFactory> */
    use HasFactory;

    protected $table = 'bookings';

    protected $fillable = ['uuid', 'class_id', 'member_id', 'status', 'cancelled_at'];

    protected static function newFactory(): BookingModelFactory
    {
        return BookingModelFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<FitnessClassModel, $this>
     */
    public function fitnessClass(): BelongsTo
    {
        return $this->belongsTo(FitnessClassModel::class, 'class_id');
    }
}
