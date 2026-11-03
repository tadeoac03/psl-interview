# API de Reservas de Clases para un Estudio Fitness

API REST en Laravel 13 y PostgreSQL. Los miembros de un estudio consultan el horario, reservan lugares en clases, entran a una lista de espera cuando la clase está llena y cancelan reservas. Al cancelar un lugar confirmado, se promueve automáticamente al siguiente miembro elegible de la lista.

Este README resume las decisiones técnicas globales. El detalle está en [`docs/`](#documentación).

## Arranque rápido

Requisitos: PHP 8.3+, Composer, PostgreSQL y Node.js (solo para el build de Vite).

```bash
cp .env.example .env          # configura las credenciales DB_*
createdb studio && createdb studio_test
composer setup                # dependencias, key, migraciones y assets
php artisan db:seed           # horario de demo (opcional)
php artisan serve             # http://localhost:8000/api
composer test
```

| Variable | Por defecto | Uso |
|---|---|---|
| `STUDIO_TIMEZONE` | `America/New_York` | Zona horaria del estudio para listar clases por día |
| `STUDIO_IDEMPOTENCY_TTL_HOURS` | `24` | Vigencia de una `Idempotency-Key` |

## Qué expone

| Método | Ruta | Para qué |
|---|---|---|
| `GET` | `/api/classes` | Horario de un día local (público) |
| `POST` | `/api/classes` | Crear una clase |
| `POST` | `/api/classes/{classId}/bookings` | Reservar un lugar o entrar a la lista de espera |
| `DELETE` | `/api/bookings/{bookingId}` | Cancelar una reserva propia |
| `GET` | `/api/members/{memberId}/bookings` | Reservas activas del miembro |
| `GET` | `/api/members/{memberId}/bookings/cancelled` | Reservas canceladas del miembro |

Parámetros, respuestas y errores por endpoint están en [`docs/api.md`](docs/api.md).

## Decisiones técnicas

### Arquitectura

- **Hexagonal (puertos y adaptadores)** en un solo bounded context, `app/Booking/`, con tres capas: `Domain`, `Application` e `Infrastructure`. El dominio y los casos de uso no dependen de Laravel; `BookingServiceProvider` conecta cada puerto con su adaptador.
- **Un caso de uso por clase** (`BookSeat`, `CancelBooking`, …). Los controllers son delgados: validan, llaman al caso de uso y transforman la salida con un Resource. No contienen reglas de negocio.
- **CQRS ligero:** las escrituras pasan por entidades y repositorios; las lecturas usan un read model con consultas agregadas que devuelve DTOs.
- **Dependencias externas detrás de puertos:** reloj, UUIDs, transacciones, locks y notificaciones. Así el dominio se prueba sin infraestructura y cada adaptador se puede reemplazar.

### Contrato y forma de la API

- **Contract-first:** `resources/openapi/booking-api.yaml` es la fuente de verdad. Los tests validan cada respuesta contra el spec, así que el código no puede divergir del contrato.
- **Un solo sobre de error** para toda la API: `{ "error", "message", ...contexto }`, con códigos estables. Las excepciones de dominio no conocen HTTP; un renderer central las traduce.
- **Solo se exponen UUIDv7**; los ids enteros son internos. Todas las fechas son ISO-8601 UTC.
- **Validación en capas:** forma de la entrada en Form Requests, invariantes en las entidades, reglas de negocio en los casos de uso y constraints en la base como última red de seguridad.

### Consistencia y concurrencia

- **PostgreSQL por diseño, no por gusto:** se usan `SELECT … FOR UPDATE`, advisory locks e índices únicos parciales. Las pruebas corren contra Postgres, no contra SQLite.
- **Locks explícitos con orden fijo:** primero la clase (evita sobreventa del último lugar) y luego el miembro (evita quedar confirmado en dos clases traslapadas). Un índice único parcial garantiza una sola reserva activa por miembro y clase.
- **Reintentos seguros:** los `POST` aceptan `Idempotency-Key` con alcance por miembro, y cancelar dos veces no tiene efecto adicional.
- **Notificaciones después del commit**, para no avisar de algo que luego se revierte.

### Seguridad y acceso

- **Identidad por header `X-Member-Id`**, pensada para ir detrás de un gateway que ya autenticó. No hay tokens ni tabla de miembros.
- **Sin fuga de información entre miembros:** una reserva ajena responde `404`, como si no existiera.
- **Sin rate limiting ni roles por ahora:** cualquier miembro puede crear clases. Ambos están en el backlog.

### Tiempo

- La app y la conexión a la base trabajan en **UTC**. La zona del estudio solo se usa para definir qué es "un día" al listar clases, incluidos los días de 23 o 25 horas por cambio de horario.
- La hora actual sale del puerto `Clock`, nunca de `now()`, para que las pruebas la controlen.

## Documentación

| Documento | Contenido |
|---|---|
| [`docs/global-rules.md`](docs/global-rules.md) | Reglas de negocio acordadas: reservas, lista de espera, traslapes, acceso y reintentos |
| [`docs/api.md`](docs/api.md) | Endpoints, headers, validaciones, respuestas, idempotencia y códigos de error |
| [`docs/architecture.md`](docs/architecture.md) | Capas, reglas de dependencia, dónde vive cada regla, validación, concurrencia y tiempo |
| [`docs/database.md`](docs/database.md) | Tablas, índices, constraints, decisiones de modelado, seeders y factories |
| [`docs/testing.md`](docs/testing.md) | Estructura y convenciones de pruebas, validación OpenAPI, tests de concurrencia y riesgos cubiertos |
| [`docs/post-mvp.md`](docs/post-mvp.md) | Lo que quedó fuera del MVP, pendientes operativos y preguntas abiertas |
