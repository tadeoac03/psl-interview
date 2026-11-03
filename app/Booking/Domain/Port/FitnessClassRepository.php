<?php

namespace App\Booking\Domain\Port;

use App\Booking\Domain\Model\FitnessClass;

interface FitnessClassRepository
{
    public function findByUuid(string $uuid): ?FitnessClass;

    public function findById(int $id): ?FitnessClass;

    public function save(FitnessClass $class): FitnessClass;
}
