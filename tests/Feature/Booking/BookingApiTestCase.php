<?php

namespace Tests\Feature\Booking;

use App\Booking\Infrastructure\Persistence\Eloquent\FitnessClassModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Tests\TestCase;

/**
 * Every request made through api() is also validated against the OpenAPI contract,
 * so the spec and the implementation cannot drift apart.
 */
abstract class BookingApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected const NOW = '2026-11-02 12:00:00';

    private static ?ResponseValidator $contract = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::NOW, 'UTC'));
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $data
     */
    protected function api(string $method, string $uri, array $headers = [], array $data = []): TestResponse
    {
        $response = $this->json($method, $uri, $data, $headers);

        $this->assertMatchesContract($method, $uri, $response);

        return $response;
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function book(FitnessClassModel $class, string $memberId, array $headers = []): TestResponse
    {
        return $this->api('POST', "/api/classes/{$class->uuid}/bookings", ['X-Member-Id' => $memberId, ...$headers]);
    }

    protected function cancel(string $bookingId, string $memberId): TestResponse
    {
        return $this->api('DELETE', "/api/bookings/{$bookingId}", ['X-Member-Id' => $memberId]);
    }

    protected function memberBookings(string $memberId): TestResponse
    {
        return $this->api('GET', "/api/members/{$memberId}/bookings", ['X-Member-Id' => $memberId]);
    }

    protected function classAt(string $startUtc, string $endUtc, int $capacity = 10, string $name = 'Class'): FitnessClassModel
    {
        return FitnessClassModel::factory()->between($startUtc, $endUtc)->capacity($capacity)->create(['name' => $name]);
    }

    private function assertMatchesContract(string $method, string $uri, TestResponse $response): void
    {
        self::$contract ??= (new ValidatorBuilder)
            ->fromYamlFile(resource_path('openapi/booking-api.yaml'))
            ->getResponseValidator();

        $psr17 = new Psr17Factory;
        $psrResponse = (new PsrHttpFactory($psr17, $psr17, $psr17, $psr17))->createResponse($response->baseResponse);
        $path = parse_url($uri, PHP_URL_PATH);

        self::$contract->validate(new OperationAddress($path, strtolower($method)), $psrResponse);
        $this->addToAssertionCount(1);
    }
}
