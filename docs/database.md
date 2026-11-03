# Base de datos

- **Motor:** PostgreSQL 16 (elegido por la alta concurrencia: `FOR UPDATE`, advisory locks e índices parciales).
- **Bases:** `studio` (desarrollo) y `studio_test` (tests, definida en `phpunit.xml`).
- **Zona horaria:** la conexión está forzada a `UTC` en `config/database.php`. Todas las columnas de tiempo son `timestamptz`.
- **Reiniciar datos:** `php artisan migrate:fresh --seed`.

## Identificadores

- `id` (`bigint`): clave primaria interna. Se usa para relaciones (FK) y consultas internas.
- `uuid` (UUIDv7, único): lo único que se expone en la API.
- `member_id` es un string opaco que llega en `X-Member-Id`; no hay tabla de miembros.

## Tablas

### `classes`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `uuid` | uuid, único | |
| `name` | varchar(100) | |
| `starts_at` | timestamptz, indexado | UTC |
| `ends_at` | timestamptz | UTC |
| `capacity` | unsigned int | |
| `cancelled_at` | timestamptz, nullable | clase cancelada si no es null |
| `created_at`, `updated_at` | timestamptz | |

Constraints (solo Postgres): `CHECK (ends_at > starts_at)`, `CHECK (capacity > 0)`.

### `bookings`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | también define el orden FIFO de la lista de espera |
| `uuid` | uuid, único | |
| `class_id` | FK → `classes.id`, cascade on delete | |
| `member_id` | varchar(100) | |
| `status` | varchar(16) | `confirmed`, `waitlisted`, `cancelled` (enum `BookingStatus`) |
| `cancelled_at` | timestamptz, nullable | |
| `created_at`, `updated_at` | timestamptz | |

Índices:
- `(class_id, status, id)`: contar confirmados y recorrer la lista de espera en orden.
- `(member_id, status)`: reservas de un miembro y búsqueda de traslapes.
- **Único parcial** `bookings_one_active_per_member_and_class ON (class_id, member_id) WHERE status <> 'cancelled'`: un miembro tiene como máximo una reserva activa por clase. Las canceladas no cuentan, así que puede volver a reservar.

### `idempotency_keys`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `member_id` | varchar(100) | |
| `key` | varchar(255) | |
| `request_hash` | char(64) | SHA-256 de método + ruta + body |
| `status_code` | unsigned smallint | |
| `response_body` | json | respuesta original para replay |
| `created_at` | timestamptz, indexado | se usa para la expiración de 24 h |

Único `(member_id, key)`: las keys son por miembro.

## Decisiones de modelado

- **`position` no se guarda.** Se calcula al vuelo contando las reservas `waitlisted` de la clase con `id <=` al de la reserva. Así no hay que renumerar al cancelar.
- **Las reservas no se borran.** Cancelar cambia `status` a `cancelled` y llena `cancelled_at`; el historial alimenta `/bookings/cancelled`.
- **Ocupación calculada.** `booked`, `waitlisted` y `seatsLeft` se calculan con agregados al listar; no hay contadores desnormalizados.
- Otras tablas (`users`, `cache`, `jobs`, `personal_access_tokens`) vienen del esqueleto de Laravel. Solo `cache` se usa (locks de idempotencia).

## Seeders y factories

- `ClassScheduleSeeder`: horario de demo alrededor del 2026-11-02 en hora local del estudio, con clases que se traslapan, capacidades de 1–2 para probar la lista de espera, una clase el día del cambio de horario (2026-11-01) y una clase cancelada. Usa fechas fijas.
- Factories: `FitnessClassModelFactory` (estados `between()`, `capacity()`, `cancelled()`) y `BookingModelFactory`.
