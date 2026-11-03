# Post-MVP

Lo que quedó fuera del MVP de forma consciente, con el motivo y lo que habría que hacer. El MVP priorizó el orden de los checks del ejercicio: listar clases → reservar → cancelar con promoción → reservas del miembro → traslapes → idempotencia → robustez y concurrencia.

## Funcionalidad pendiente

| Tema | Estado actual | Qué falta |
|---|---|---|
| **Cancelar una clase** | `cancelled_at` solo se llena desde seeder o factories | Endpoint para cancelar una clase, cancelar sus reservas y notificar a los afectados |
| **Re-evaluar la lista de espera tras cancelar** | Si un miembro cancela una clase confirmada, su lugar en la lista de espera de una clase que se traslapaba **no** se re-evalúa | Al cancelar, intentar promover al miembro en las listas de espera donde antes tenía conflicto |
| **Notificaciones reales** | `LogBookingNotifier` solo escribe en el log | Adaptador de correo o push, idealmente encolado (job) |
| **Autenticación real** | `X-Member-Id` se acepta tal cual, sin verificar | Autenticación con token (Sanctum ya está instalado y sin uso) y una entidad de miembro |
| **Autorización para crear clases** | Cualquier miembro puede usar `POST /api/classes` | Rol de administrador o staff |
| **Editar clases** | No existe | Cambiar horario o capacidad, con reglas para las reservas existentes (por ejemplo, capacidad menor que los confirmados) |
| **Ventana de cancelación** | Se puede cancelar en cualquier momento, incluso con la clase empezada | Definir si hay un límite (por ejemplo, hasta X horas antes) |
| **Reservas de clases pasadas** | `/members/{id}/bookings` lista activas sin filtrar por fecha | Separar próximas e historial, o filtrar por rango |
| **Paginación** | Las listas devuelven todo | Paginación en las listas de reservas |

## Operación

| Tema | Estado actual | Qué falta |
|---|---|---|
| **Purgar keys de idempotencia** | Las keys expiradas se ignoran, pero nunca se borran | Comando programado (`schedule`) que borre filas con más de 24 h |
| **Seeder con fechas fijas** | Alrededor del 2026-11-02 | Generar clases relativas a la fecha actual |
| **Rate limiting** | No hay | `throttle` por miembro en los POST |
| **Observabilidad** | Solo logs | Métricas de reservas, promociones y conflictos de lock |
| **Locks de idempotencia** | `Cache::lock` con store `database` | Redis si crece el tráfico |

## Dependencias por revisar

- `laravel/sanctum` vino con `php artisan install:api` y no se usa. Quitarlo o usarlo para la autenticación real.
- La tabla `users` y el modelo `User` del esqueleto no participan en el dominio.

## Preguntas abiertas

- ¿Los checks del cliente esperan `401` también en `GET /api/classes`? Hoy es público; cambiarlo es una línea en `routes/api.php`.
- ¿Los checks comparan el body de error exacto (`{"error":"already_booked"}`)? El campo `message` adicional podría romperlos.
