<?php

namespace App\Booking\Application\CreateClass;

use App\Booking\Application\ListClasses\ClassCatalog;
use App\Booking\Application\Shared\ClassView;
use App\Booking\Domain\Model\FitnessClass;
use App\Booking\Domain\Model\TimeRange;
use App\Booking\Domain\Port\FitnessClassRepository;
use App\Booking\Domain\Port\UuidGenerator;
use DateTimeImmutable;

final readonly class CreateClass
{
    public function __construct(
        private FitnessClassRepository $classes,
        private ClassCatalog $catalog,
        private UuidGenerator $uuids,
    ) {}

    public function handle(string $name, DateTimeImmutable $start, DateTimeImmutable $end, int $capacity): ClassView
    {
        $class = $this->classes->save(
            FitnessClass::schedule($this->uuids->generate(), $name, new TimeRange($start, $end), $capacity),
        );

        return $this->catalog->findByUuid($class->uuid);
    }
}
