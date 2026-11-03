# Fitness Studio Class Booking API

A Laravel 13 API that lets members browse a studio's class schedule, book seats, join a waitlist when a class is full, and cancel bookings.

## Features

- **Seat booking with FIFO waitlist.** If a class has a free seat, the booking is confirmed. If not, the member joins the waitlist.
- **Automatic promotion.** Cancelling a confirmed seat promotes the first waitlisted member who is not already confirmed in an overlapping class.
- **Schedule conflict detection.** A member can't hold confirmed seats in two classes that overlap in time. They can still wait for an overlapping class.
- **Concurrency-safe.** Seats are allocated under a row lock on the class, and each member's overlap check runs under a Postgres advisory lock.
- **Idempotent writes.** `POST` requests accept an `Idempotency-Key` header. A retry replays the stored response, and reusing a key with a different payload is rejected.
- **Time zone aware.** Classes are listed by local calendar date, and DST days of 23 or 25 hours are handled correctly.
- **Consistent error envelope.** Every error has the shape `{ "error": "<code>", "message": "<text>", ...context }`.

## Requirements

- PHP 8.3+
- Composer
- PostgreSQL
- Node.js and npm (only for the Vite asset build)

## Setup

```bash
cp .env.example .env          # then set your DB_* credentials
createdb studio
createdb studio_test          # used by the test suite
composer setup                # install deps, generate key, migrate, build assets
php artisan serve
```

The API is served at `http://localhost:8000/api`.

### Configuration

| Variable | Default | Description |
| --- | --- | --- |
| `STUDIO_TIMEZONE` | `America/New_York` | Studio time zone; default for date-based queries |
| `STUDIO_IDEMPOTENCY_TTL_HOURS` | `24` | How long an idempotent response is replayed |

## API

The full contract is in [`resources/openapi/booking-api.yaml`](resources/openapi/booking-api.yaml) (OpenAPI 3.0). The test suite validates responses against this spec.

All times are ISO-8601 UTC (for example `2026-11-02T23:00:00Z`). Member-scoped endpoints require the `X-Member-Id` header.

| Method | Endpoint | Auth | Description |
| --- | --- | --- | --- |
| `GET` | `/api/classes?date=YYYY-MM-DD&tz=Zone` | Public | Classes starting on a local date |
| `POST` | `/api/classes` | `X-Member-Id` | Create a class |
| `POST` | `/api/classes/{classId}/bookings` | `X-Member-Id` | Book a seat or join the waitlist |
| `DELETE` | `/api/bookings/{bookingId}` | `X-Member-Id` | Cancel a booking (safe to repeat) |
| `GET` | `/api/members/{memberId}/bookings` | `X-Member-Id` | The member's active bookings |
| `GET` | `/api/members/{memberId}/bookings/cancelled` | `X-Member-Id` | The member's cancelled bookings |

### Example

```bash
curl -X POST http://localhost:8000/api/classes/{classId}/bookings \
  -H "X-Member-Id: member-123" \
  -H "Idempotency-Key: 7f1c9b2e-booking-1" \
  -H "Accept: application/json"
```

### Error codes

| Status | Code |
| --- | --- |
| 401 | `unauthenticated` |
| 403 | `forbidden` |
| 404 | `class_not_found`, `booking_not_found`, `not_found` |
| 409 | `already_booked`, `schedule_conflict`, `class_already_started`, `class_cancelled`, `idempotency_key_in_use` |
| 422 | `validation_error`, `idempotency_key_mismatch` |

## Architecture

The booking module lives in `app/Booking` and follows a hexagonal (ports and adapters) layout:

```
app/Booking/
├── Domain/          # Entities, value objects, domain exceptions and ports (interfaces)
├── Application/     # Use cases: BookSeat, CancelBooking, CreateClass, ListClasses, ListMemberBookings
└── Infrastructure/  # Laravel adapters: HTTP controllers, middleware, Eloquent repositories, locks
```

The domain and application layers don't depend on Laravel. Infrastructure binds the ports to their implementations in `BookingServiceProvider`.

## Testing

```bash
composer test
```

Feature tests cover booking, cancellation and promotion, idempotency, concurrent booking, time zone listing and member bookings. They run against the `studio_test` Postgres database.
