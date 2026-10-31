<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\UtilisateurConnecte;
use JsonException;

final class Request
{
    /** @var array<string, string> */
    private array $params = [];

    private ?UtilisateurConnecte $user = null;

    /** @var array<string, mixed> données posées par un middleware (borne authentifiée…) */
    private array $attributes = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $files
     * @param array<string, mixed> $server
     * @param array<string, string> $cookies
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query = [],
        private array $body = [],
        private readonly array $files = [],
        private readonly array $server = [],
        private readonly array $cookies = [],
        private readonly string $rawBody = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';
        $rawBody = (string) file_get_contents('php://input');

        $request = new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path === '' ? '/' : $path,
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
            $_COOKIE,
            $rawBody,
        );

        if ($request->isJsonBody() && $rawBody !== '') {
            try {
                $decoded = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
                $request->body = is_array($decoded) ? $decoded : [];
            } catch (JsonException) {
                // Corps illisible : traité comme vide, la validation du contrôleur le refusera.
                $request->body = [];
            }
        }

        return $request;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function allQuery(): array
    {
        return $this->query;
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->body[$key] ?? null;
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /**
     * Valeur brute (tableau, nombre, booléen) d'un corps JSON ou d'un champ multiple.
     */
    public function raw(string $key): mixed
    {
        return $this->body[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->body;
    }

    /**
     * @param list<string> $keys
     * @return array<string, string>
     */
    public function only(array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->input($key) ?? '';
        }

        return $values;
    }

    public function boolean(string $key): bool
    {
        return in_array($this->input($key), ['1', 'on', 'true', 'oui'], true);
    }

    /**
     * @return array{name: string, type: string, tmp_name: string, error: int, size: int}|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            return null;
        }
        if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return [
            'name' => (string) $file['name'],
            'type' => (string) $file['type'],
            'tmp_name' => (string) $file['tmp_name'],
            'error' => (int) $file['error'],
            'size' => (int) $file['size'],
        ];
    }

    /**
     * Fichiers d'un champ multiple (name="photos[]").
     *
     * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    public function files(string $key): array
    {
        $field = $this->files[$key] ?? null;
        if (!is_array($field) || !is_array($field['name'] ?? null)) {
            $single = $this->file($key);

            return $single === null ? [] : [$single];
        }

        $files = [];
        foreach (array_keys($field['name']) as $index) {
            if ((int) $field['error'][$index] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name' => (string) $field['name'][$index],
                'type' => (string) $field['type'][$index],
                'tmp_name' => (string) $field['tmp_name'][$index],
                'error' => (int) $field['error'][$index],
                'size' => (int) $field['size'][$index],
            ];
        }

        return $files;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if ($name === 'Content-Type') {
            $key = 'CONTENT_TYPE';
        }
        $value = $this->server[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function ip(): string
    {
        // o2switch place l'application derrière un proxy local : REMOTE_ADDR reste la source fiable.
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? '') === 'on'
            || ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public function wantsJson(): bool
    {
        return str_starts_with($this->path, '/api/')
            || str_contains((string) $this->header('Accept'), 'application/json');
    }

    public function isJsonBody(): bool
    {
        return str_contains((string) $this->header('Content-Type'), 'application/json');
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    /**
     * @param array<string, string> $params
     */
    public function withParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $name): string
    {
        return $this->params[$name] ?? '';
    }

    public function intParam(string $name): int
    {
        $value = $this->param($name);
        if (preg_match('/^[1-9]\d{0,18}$/', $value) !== 1) {
            throw HttpException::notFound();
        }

        return (int) $value;
    }

    public function setUser(UtilisateurConnecte $user): void
    {
        $this->user = $user;
    }

    public function user(): ?UtilisateurConnecte
    {
        return $this->user;
    }

    public function requireUser(): UtilisateurConnecte
    {
        return $this->user ?? throw HttpException::forbidden();
    }

    public function withAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function attribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }
}
