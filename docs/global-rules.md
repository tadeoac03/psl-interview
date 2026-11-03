# Reglas globales

Reglas de negocio y decisiones de producto acordadas para esta API. Cualquier cambio debe respetarlas o actualizar este documento primero.

## Negocio

1. **Zona del estudio:** `America/New_York`. Toda fecha en la API es ISO-8601 UTC.
2. **Miembros:** cualquier `X-Member-Id` no vacío (≤ 100 caracteres) es un miembro válido. Cualquier miembro puede reservar.
3. **Una reserva activa por clase:** un miembro no puede tener dos reservas activas (confirmada o en espera) en la misma clase → `409 already_booked`.
4. **Volver a reservar:** después de cancelar, el miembro puede reservar de nuevo la misma clase.
5. **Sin traslapes confirmados:** un miembro no puede estar **confirmado** en dos clases que se traslapan → `409 schedule_conflict` con la clase en conflicto.
6. **Clases seguidas no chocan:** los horarios son intervalos semiabiertos `[start, end)`; si una termina cuando empieza otra, no hay traslape.
7. **Lista de espera y traslapes:** sí se puede entrar a la lista de espera de una clase que se traslapa con una confirmada; la regla 5 solo aplica a confirmados.
8. **Lista de espera FIFO:** al cancelar un lugar confirmado se promueve automáticamente al primero de la lista **que no esté confirmado en una clase que se traslape**. Quien tenga conflicto conserva su lugar en la lista.
9. **Cancelar desde la lista de espera** no promueve a nadie; las posiciones de los demás se recorren.
10. **Clases no reservables:** no se puede reservar una clase que ya empezó (`409 class_already_started`) ni una cancelada (`409 class_cancelled`).
11. **Clases canceladas** no aparecen en `GET /api/classes`.
12. **El día se define en hora local:** `GET /api/classes` devuelve las clases que **empiezan** en la fecha local pedida, respetando días de 23/25 h por cambio de horario. Sin `date`, se usa hoy en la zona pedida.

## Acceso

13. `GET /api/classes` es público (el horario no depende de quién consulta). Todo lo demás exige `X-Member-Id` → `401 unauthenticated`.
14. **Reserva ajena:** cancelar la reserva de otro miembro responde `404 booking_not_found`, igual que si no existiera, para no revelar su existencia.
15. **Reservas de otro miembro:** consultar `/members/{otro}/bookings` (o `/cancelled`) responde `403 forbidden`.

## Reintentos

16. **Cancelar es idempotente:** cancelar dos veces la misma reserva propia responde `204` ambas veces y solo promueve una vez.
17. **Idempotency-Key en POST:** un reintento nunca crea una segunda reserva. Con la misma key se repite la respuesta original; con la key reutilizada para otra petición → `422 idempotency_key_mismatch`. Las keys son por miembro y duran 24 h.

## Formato y convenciones técnicas

18. **Contrato primero:** `resources/openapi/booking-api.yaml` es la fuente de verdad. Se actualiza antes que el código, y no se sirve por HTTP.
19. **Sobre de error único:** `{ "error": "<código estable>", "message": "<texto>", ...contexto }`. Los códigos son parte del contrato; no se renombran.
20. **Los ids internos (`bigint`) nunca salen en la API**; solo UUIDv7.
21. **Arquitectura hexagonal:** reglas de negocio en `Domain`/`Application`, sin dependencias de Laravel (ver `architecture.md`).
22. **Toda regla nueva lleva test** (ver `testing.md`).
