# Arquitectura

Arquitectura hexagonal (puertos y adaptadores) en un único bounded context: `app/Booking/` (namespace `App\Booking`). Va dentro de `app/` y no en `src/` porque no se crean carpetas base nuevas sin aprobación.

## Capas

```
app/Booking/
├── Domain/           Reglas de negocio. Sin dependencias de Laravel.
│   ├── Model/        FitnessClass, Booking, BookingStatus (enum), MemberId, TimeRange
│   ├── Exception/    BookingDomainException y sus hijas (AlreadyBooked, ScheduleConflict, ...)
│   └── Port/         BookingRepository, FitnessClassRepository, LockManager,
│                     TransactionManager, Clock, UuidGenerator, BookingNotifier
├── Application/      Casos de uso. Sin dependencias de Laravel.
│   ├── BookSeat/             reservar o entrar a lista de espera
│   ├── CancelBooking/        cancelar + promover al siguiente elegible
│   ├── CreateClass/
│   ├── ListClasses/          ListClassesForLocalDate + puerto de lectura ClassCatalog
│   ├── ListMemberBookings/   + puerto de lectura MemberBookingsQuery
│   └── Shared/               DTOs de salida (BookingView, ClassView), IdempotencyStore
└── Infrastructure/   Adaptadores de Laravel.
    ├── Http/             Controllers, Middleware, FormRequests, Resources,
    │                     ApiError (sobre de error), ApiExceptionRenderer, Iso8601
    ├── Persistence/      Repositorios Eloquent, read model, locks, transacciones, idempotencia
    ├── Support/          SystemClock, LaravelUuidGenerator, LogBookingNotifier
    └── BookingServiceProvider.php   bindings puerto → adaptador
```

## Reglas de dependencia

- `Domain` no importa nada fuera de `Domain` (ni Laravel, ni Eloquent, ni Carbon de Laravel).
- `Application` solo depende de `Domain` y de sus propios puertos.
- `Infrastructure` depende de todo lo anterior y de Laravel. Es la única capa que conoce HTTP y la base de datos.
- Los controllers son delgados: validan entrada (FormRequest), llaman a un caso de uso y transforman la salida con un Resource. No contienen reglas de negocio.
- Las excepciones de dominio no conocen HTTP; `ApiExceptionRenderer` las traduce a status y código de error.
- Todo puerto nuevo se registra en `BookingServiceProvider::$singletons`.

## Dónde vive cada regla

| Regla | Ubicación |
|---|---|
| Traslape de horarios (intervalo semiabierto `[start, end)`, clases seguidas no chocan) | `Domain/Model/TimeRange::overlaps()` |
| No reservar clase empezada o cancelada | `Domain/Model/FitnessClass::assertBookableAt()` |
| Confirmar vs. lista de espera, `already_booked`, `schedule_conflict` | `Application/BookSeat/BookSeat` |
| Cancelación idempotente y promoción del primer elegible | `Application/CancelBooking/CancelBooking` |
| Rango UTC de un día local (respeta días de 23/25 h por DST) | `Application/ListClasses/ListClassesForLocalDate` |
| Identidad del miembro (401) | `Infrastructure/Http/Middleware/IdentifyMember` (alias `member`) |
| Idempotency-Key | `Infrastructure/Http/Middleware/EnsureIdempotency` (alias `idempotent`) |

## Lectura y escritura separadas (CQRS ligero)

- **Escritura:** los casos de uso trabajan con repositorios (`BookingRepository`, `FitnessClassRepository`) que reconstruyen entidades de dominio con sus invariantes.
- **Lectura:** los listados usan puertos de lectura (`ClassCatalog`, `MemberBookingsQuery`) implementados por `EloquentBookingReadModel`. Devuelven DTOs (`ClassView`, `BookingView`) armados con consultas agregadas (`withCount` para ocupación, subconsulta para `position`), sin pasar por entidades y sin N+1.

## Validación en capas

| Capa | Qué valida | Dónde |
|---|---|---|
| HTTP | Forma de la entrada (tipos, formatos, rangos) | FormRequests (`CreateClassRequest`, `ListClassesRequest`); los controllers solo comprueban que los ids de ruta sean UUID |
| Dominio | Invariantes de las entidades (fin > inicio, capacidad ≥ 1, `MemberId` no vacío) | Constructores de `TimeRange`, `FitnessClass`, `MemberId` |
| Aplicación | Reglas de negocio (cupo, traslapes, clase empezada o cancelada) | Casos de uso |
| Base de datos | Última red de seguridad | `CHECK` y el índice único parcial (ver `database.md`) |

## Concurrencia

Cada escritura corre dentro de `TransactionManager::run()` y toma locks vía el puerto `LockManager`:

- **Lock de clase:** `SELECT ... FOR UPDATE` sobre la fila de la clase. Serializa la asignación de lugares de una clase (nadie se queda con el "último lugar" dos veces).
- **Lock de miembro:** `pg_advisory_xact_lock` por `member_id`. Serializa las verificaciones de traslape de un mismo miembro entre clases distintas.
- **Orden de locks:** siempre clase primero, luego miembro, para evitar deadlocks.
- **Red de seguridad:** índice único parcial en BD para una reserva activa por miembro y clase (ver `database.md`).
- **Idempotencia:** lock de caché (`Cache::lock`, store `database`) por miembro + key mientras se procesa la petición.

## Notificaciones

`BookingNotifier` es un puerto con `bookingCancelled()` y `bookingPromoted()`. Se llama **después** del commit. El adaptador actual (`LogBookingNotifier`) solo escribe en el log; un adaptador real (correo, push, cola) se enchufa cambiando el binding.

## Tiempo

- La app y la conexión de Postgres están forzadas a UTC (`config/app.php`, `config/database.php` → `timezone: UTC`).
- La zona del estudio es configurable: `config/studio.php` (`STUDIO_TIMEZONE`, por defecto `America/New_York`).
- El tiempo "actual" se obtiene del puerto `Clock`, nunca de `now()` en dominio o aplicación, para que los tests lo controlen.
