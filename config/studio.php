<?php

return [
    /*
    | Time zone the studio operates in. Used as the default for date-based queries.
    */
    'timezone' => env('STUDIO_TIMEZONE', 'America/New_York'),

    /*
    | Hours an Idempotency-Key response is replayed before the key can be reused.
    */
    'idempotency_ttl_hours' => (int) env('STUDIO_IDEMPOTENCY_TTL_HOURS', 24),
];
