# Testing

- **Framework:** PHPUnit 12. Tests de feature para los flujos HTTP; unitarios solo para lógica pura del dominio.
- **Base de datos:** Postgres (`studio_test`, configurada en `phpunit.xml`). Las reglas de concurrencia dependen de Postgres, así que no se prueba con SQLite.
- **Correr todo:** `php artisan test --compact`.
- **Correr un archivo o test:** `php artisan test --compact tests/Feature/Booking/BookSeatTest.php` o `--filter=test_nombre`.
- **Solo concurrencia:** `php artisan test --compact --group=concurrency`.

## Estructura

```
tests/
├── Feature/Booking/
│   ├── BookingApiTestCase.php     base de los tests de API
│   ├── ListClassesTest.php        GET /api/classes
│   ├── CreateClassTest.php        POST /api/classes
│   ├── BookSeatTest.php           POST /api/classes/{id}/bookings
│   ├── CancelBookingTest.php      DELETE /api/bookings/{id} y promoción
│   ├── MemberBookingsTest.php     GET /api/members/{id}/bookings y /cancelled
│   ├── IdempotencyTest.php        Idempotency-Key
│   └── ConcurrentBookingTest.php  carreras reales con procesos en paralelo
└── Unit/Booking/
    └── TimeRangeTest.php          traslapes de intervalos
```

## Convenciones

- **Heredar de `BookingApiTestCase`** para tests de API. Aporta:
  - `RefreshDatabase` y el reloj congelado en `2026-11-02 12:00:00 UTC`.
  - Helpers `api()`, `book()`, `cancel()`, `memberBookings()` y `classAt()`.
  - **Validación contra el contrato:** cada respuesta de `api()` se valida contra `resources/openapi/booking-api.yaml`. Si el código y el contrato no coinciden, el test falla aunque las aserciones pasen.
- **Nombres:** `test_` + la regla en inglés, en forma de frase (`test_member_cannot_book_the_same_class_twice`).
- **Datos:** factories (`FitnessClassModel::factory()->between(...)->capacity(...)->cancelled()`), nunca inserts a mano.
- **Tiempo:** controlarlo con `travelTo()`; el código lee la hora del puerto `Clock`.
- **Puertos de salida:** sustituir el adaptador en el contenedor (por ejemplo, un `BookingNotifier` falso) en vez de mockear Eloquent.
- **Aserciones:** comprobar el código de error (`assertJsonPath('error', ...)`) y el estado en BD (`assertDatabaseHas`, `assertDatabaseCount`), no solo el status HTTP.
- Correr `vendor/bin/pint` sobre los archivos tocados.

## Tests de concurrencia

`ConcurrentBookingTest` hace fork de procesos (requiere la extensión `pcntl`), cada uno con su propia conexión a Postgres, para probar los locks con carreras reales:

- Solo un miembro obtiene el último lugar.
- Un miembro no queda confirmado en dos clases que se traslapan aunque las pida al mismo tiempo.
- Peticiones duplicadas simultáneas crean una sola reserva.

Usa `DatabaseTruncation` porque los procesos hacen commit real, y en `tearDown` trunca `bookings`, `classes` e `idempotency_keys` para no contaminar los tests transaccionales que corren después. Se salta si el driver no es `pgsql`. Se verificó que los dos primeros fallan si se quitan los locks.

## Riesgos cubiertos

| Riesgo | Tests |
|---|---|
| Día con cambio de horario (2026-11-01 dura 25 h) | `ListClassesTest::test_day_boundaries_follow_the_dst_change_on_november_first` |
| `seatsLeft` negativo | `ListClassesTest::test_reports_occupancy_and_never_negative_seats` |
| Sobreventa del último lugar | `ConcurrentBookingTest::test_only_one_member_gets_the_last_seat` |
| Doble confirmación con traslape en paralelo | `ConcurrentBookingTest::test_a_member_cannot_be_confirmed_in_two_overlapping_classes_at_once` |
| Reintentos que duplican reservas | `IdempotencyTest`, `ConcurrentBookingTest::test_simultaneous_duplicates_create_a_single_booking` |
| Doble promoción al cancelar dos veces | `CancelBookingTest::test_cancelling_twice_is_idempotent_and_promotes_only_once` |
| Promover a alguien que quedaría con traslape | `CancelBookingTest::test_promotion_skips_members_confirmed_in_an_overlapping_class` |
| Fuga de información entre miembros | `CancelBookingTest::test_bookings_of_other_members_are_not_found`, `MemberBookingsTest::test_members_cannot_read_bookings_of_others` |
| Respuestas fuera de contrato | Validación OpenAPI automática en `BookingApiTestCase` |

## Al agregar o cambiar una regla

1. Actualizar el contrato (`booking-api.yaml`) si cambia la API.
2. Escribir el test de feature del flujo (camino feliz + cada error) en el archivo del endpoint.
3. Si la regla toca concurrencia, agregar un caso en `ConcurrentBookingTest`.
4. Si es lógica pura del dominio, un test unitario en `tests/Unit/Booking/`.
