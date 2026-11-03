<?php

namespace App\Booking\Infrastructure\Http\Controllers;

use App\Booking\Application\CreateClass\CreateClass;
use App\Booking\Application\ListClasses\ListClassesForLocalDate;
use App\Booking\Infrastructure\Http\Requests\CreateClassRequest;
use App\Booking\Infrastructure\Http\Requests\ListClassesRequest;
use App\Booking\Infrastructure\Http\Resources\ClassSummaryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ClassController
{
    public function index(ListClassesRequest $request, ListClassesForLocalDate $listClasses): AnonymousResourceCollection
    {
        return ClassSummaryResource::collection($listClasses->handle($request->localDate(), $request->timeZone()));
    }

    public function store(CreateClassRequest $request, CreateClass $createClass): JsonResponse
    {
        $class = $createClass->handle(
            $request->validated('name'),
            $request->start(),
            $request->end(),
            (int) $request->validated('capacity'),
        );

        return (new ClassSummaryResource($class))->response()->setStatusCode(201);
    }
}
