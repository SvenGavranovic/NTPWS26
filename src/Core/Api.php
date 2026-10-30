<?php

declare(strict_types=1);

namespace App\Core;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

/**
 * Odgovori i ulaz REST API-ja.
 *
 * Uspjeh:   {"data": ..., "meta": {...}}
 * Pogreška: {"greska": {"status": 400, "poruka": "...", "detalji": {"polje": "poruka"}}}
 */
final class Api
{
    public static function isApiPath(string $path): bool
    {
        return str_starts_with($path, '/api/');
    }

    public static function ok(mixed $data, ?array $meta = null, int $status = 200): void
    {
        $tijelo = ['data' => $data];
        if ($meta !== null) {
            $tijelo['meta'] = $meta;
        }
        json_response($tijelo, $status);
    }

    public static function created(mixed $data, string $location): void
    {
        header('Location: ' . url($location));
        self::ok($data, null, 201);
    }

    public static function noContent(): void
    {
        http_response_code(204);
    }

    /** @param array<string, string> $detalji pogreške po poljima */
    public static function error(int $status, string $poruka, array $detalji = []): void
    {
        $greska = ['status' => $status, 'poruka' => $poruka];
        if ($detalji !== []) {
            $greska['detalji'] = $detalji;
        }
        json_response(['greska' => $greska], $status);
    }

    /**
     * Tijelo zahtjeva kao JSON objekt. Vraća null (i šalje 400) ako tijelo
     * nije JSON objekt ili Content-Type nije application/json.
     */
    public static function body(): ?object
    {
        if (!str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
            self::error(400, 'Tijelo zahtjeva mora biti JSON (Content-Type: application/json).');
            return null;
        }
        try {
            $data = json_decode((string) file_get_contents('php://input'), false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            self::error(400, 'Tijelo zahtjeva nije ispravan JSON.');
            return null;
        }
        if (!$data instanceof \stdClass) {
            self::error(400, 'Tijelo zahtjeva mora biti JSON objekt.');
            return null;
        }
        return $data;
    }

    /**
     * Provjera prema JSON Schemi iz mape schemas/. Kod pogreške šalje 400
     * s popisom neispravnih polja i vraća false.
     */
    public static function validate(object $data, string $shema): bool
    {
        $schema = json_decode((string) file_get_contents(BASE_PATH . '/schemas/' . $shema . '.json'), false, 64, JSON_THROW_ON_ERROR);
        $validator = new Validator();
        $validator->validate($data, $schema, Constraint::CHECK_MODE_NORMAL);

        if ($validator->isValid()) {
            return true;
        }

        $detalji = [];
        foreach ($validator->getErrors() as $e) {
            $polje = $e['property'] !== '' ? $e['property'] : '(tijelo)';
            $detalji[$polje] ??= $e['message'];
        }
        self::error(400, 'Podaci ne odgovaraju shemi ' . $shema . '.', $detalji);
        return false;
    }

    /** Pozitivan cijeli broj iz query stringa unutar granica, inače zadana vrijednost. */
    public static function queryInt(string $kljuc, int $zadano, int $min, int $max): int
    {
        $v = filter_var(get($kljuc), FILTER_VALIDATE_INT);
        return $v === false ? $zadano : max($min, min($max, $v));
    }

    /** Datum iz baze u ISO 8601 (npr. 2026-10-05T08:00:00+02:00) ili null. */
    public static function date(?string $value): ?string
    {
        return $value === null ? null : (new \DateTimeImmutable($value))->format(DATE_ATOM);
    }

    public static function meta(Paginator $p): array
    {
        return [
            'stranica'        => $p->page,
            'po_stranici'     => $p->perPage,
            'ukupno'          => $p->total,
            'ukupno_stranica' => $p->pages,
        ];
    }
}
