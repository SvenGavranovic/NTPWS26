<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Api;
use App\Services\PrometService;

/** /api/v1/promet – proxy prema TomTom Traffic API-ju (javno, s cacheom). */
final class PrometApiController
{
    public function show(): void
    {
        $simulacija = in_array(get('simulacija'), PrometService::SIMULACIJE, true) ? get('simulacija') : null;
        $rezultat = (new PrometService())->get($simulacija);

        if ($rezultat['status'] !== 200) {
            header('Retry-After: 60');
            Api::error($rezultat['status'], $rezultat['poruka'], ['zadnji_uspjesni_dohvat' => 'nema podataka']);
            return;
        }
        header('Cache-Control: private, max-age=30');
        Api::ok($rezultat['data']);
    }
}
