# API

Contrato fuente de verdad: `resources/openapi/booking-api.yaml` (OpenAPI 3.0.3). Si este documento y el YAML difieren, gana el YAML. Cualquier cambio de endpoint empieza por el YAML (contract-first) y los tests validan cada respuesta contra él.

Base URL local: `http://localhost:8000`.

## Convenciones

- **Identidad:** el header `X-Member-Id` identifica al miembro. Cualquier string no vacío de hasta 100 caracteres es válido; no hay registro previo de miembros. Sin header o con valor inválido → `401 unauthenticated`.
- **Tiempos:** todas las fechas de entrada y salida son ISO-8601 UTC con sufijo `Z` (`2026-11-02T23:00:00Z`).
- **IDs:** los endpoints exponen UUIDv7 (`id`, `classId`). Los ids enteros son internos y nunca salen en la API. Un UUID mal formado se trata como inexistente (404).
- **Idempotencia:** los POST aceptan `Idempotency-Key` (ver abajo).
- **Errores:** siempre el mismo sobre: `{ "error": "<código>", "message": "<texto>", ...contexto }`.
- **Respuestas:** JSON sin envoltorio `data` (`JsonResource::withoutWrapping()`), con campos en camelCase. Los campos opcionales (`position`, `cancelledAt`) solo aparecen cuando aplican.
- **Status:** `201` al crear, `204` al cancelar, `409` para conflictos de estado y `422` para validación.
- **Versionado:** no hay prefijo `/v1`; la versión vive en el YAML (`info.version`).
- **Paginación y rate limiting:** no hay en ningún endpoint (ver `post-mvp.md`).

## Endpoints

| Método | Ruta | Auth | Descripción |
|---|---|---|---|
| GET | `/api/classes?date=&tz=` | Público | Clases no canceladas que **empiezan** en la fecha local `date` en la zona `tz`, ordenadas por inicio |
| POST | `/api/classes` | `X-Member-Id` | Crear clase (fuera del contrato original; necesaria para poblar datos) |
| POST | `/api/classes/{classId}/bookings` | `X-Member-Id` | Reservar lugar o entrar a lista de espera |
| DELETE | `/api/bookings/{bookingId}` | `X-Member-Id` | Cancelar reserva propia; promueve al siguiente en espera |
| GET | `/api/members/{memberId}/bookings` | `X-Member-Id` | Reservas activas (confirmadas y en espera) |
| GET | `/api/members/{memberId}/bookings/cancelled` | `X-Member-Id` | Reservas canceladas |

### GET /api/classes

- `date` (`YYYY-MM-DD`, opcional): por defecto hoy en `tz`.
- `tz` (IANA, opcional): por defecto `America/New_York` (zona del estudio).
- `date` o `tz` inválidos → `422 validation_error`.
- Respuesta 200: `[{ id, name, start, end, capacity, booked, waitlisted, seatsLeft }]`. `booked` = confirmados; `seatsLeft` nunca es negativo.

### POST /api/classes

Validación en `CreateClassRequest`:

| Campo | Regla |
|---|---|
| `name` | obligatorio, string, máx. 100 |
| `start` | obligatorio, UTC `Y-m-d\TH:i:s\Z` (milisegundos opcionales) |
| `end` | obligatorio, mismo formato, posterior a `start` |
| `capacity` | obligatorio, entero entre 1 y 1000 |

Respuesta `201` con la misma forma que un elemento de `GET /api/classes`. Errores: `401 unauthenticated`, `422 validation_error`.

### POST /api/classes/{classId}/bookings

| Resultado | Respuesta |
|---|---|
| Hay cupo | `201 { id, classId, memberId, status: "confirmed" }` |
| Clase llena | `201 { id, classId, memberId, status: "waitlisted", position }` |
| Sin miembro | `401 unauthenticated` |
| Clase inexistente | `404 class_not_found` |
| Ya tiene reserva activa en la clase | `409 already_booked` |
| Confirmado en otra clase que se traslapa | `409 schedule_conflict` + `conflictsWith: { id, name, start, end }` |
| La clase ya empezó | `409 class_already_started` |
| La clase está cancelada | `409 class_cancelled` |
| Misma key aún en proceso | `409 idempotency_key_in_use` |
| Key reutilizada con otra petición, o key > 255 chars | `422 idempotency_key_mismatch` / `422 validation_error` |

### DELETE /api/bookings/{bookingId}

- `204` al cancelar. Si era un lugar confirmado, se promueve al primer miembro elegible de la lista de espera.
- Cancelar una reserva ya cancelada → `204` (idempotente, sin segunda promoción).
- Reserva inexistente o de otro miembro → `404 booking_not_found` (no se revela que existe).

### GET /api/members/{memberId}/bookings y /cancelled

- `memberId` debe coincidir con `X-Member-Id`; si no → `403 forbidden`.
- Activas: `[{ id, classId, status, position? }]`, la más antigua primero. `position` solo en `waitlisted`.
- Canceladas: `[{ id, classId, status: "cancelled", cancelledAt }]`, la más reciente primero.

## Idempotency-Key

- Aplica solo a POST. Se guarda **por miembro** durante 24 h (`STUDIO_IDEMPOTENCY_TTL_HOURS`).
- Un reintento con la misma key y la misma petición devuelve la respuesta original (mismo status y body, misma `position`) con el header `Idempotency-Replayed: true`.
- La huella de la petición es `método + ruta + body JSON` (SHA-256). Misma key con otra huella → `422 idempotency_key_mismatch`.
- Las respuestas 5xx no se guardan, para que el cliente pueda reintentar.
- Dos peticiones simultáneas con la misma key se serializan con un lock: la segunda espera hasta 5 s y, si la primera no termina, recibe `409 idempotency_key_in_use`.
- Sin key, un reintento tampoco crea una segunda reserva: recibe `409 already_booked`.

## Códigos de error

| HTTP | Código | Cuándo |
|---|---|---|
| 401 | `unauthenticated` | Falta `X-Member-Id` o no es válido |
| 403 | `forbidden` | Se piden las reservas de otro miembro |
| 404 | `class_not_found` | La clase no existe o el id no es un UUID |
| 404 | `booking_not_found` | La reserva no existe o es de otro miembro |
| 404 | `not_found` | La ruta no existe |
| 405 | `method_not_allowed` | Método HTTP no permitido |
| 409 | `already_booked` | Ya tiene una reserva activa en la clase |
| 409 | `schedule_conflict` | Confirmado en una clase que se traslapa (incluye `conflictsWith`) |
| 409 | `class_already_started` | La clase ya empezó |
| 409 | `class_cancelled` | La clase está cancelada |
| 409 | `idempotency_key_in_use` | Otra petición con la misma key sigue en proceso |
| 422 | `validation_error` | Datos inválidos (incluye `details` por campo) |
| 422 | `idempotency_key_mismatch` | Key reutilizada con otra petición |
| 500 | `internal_error` | Error inesperado; sin detalles cuando `APP_DEBUG=false` |

El mapeo excepción → HTTP vive en `app/Booking/Infrastructure/Http/ApiExceptionRenderer.php`.
