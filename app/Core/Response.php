<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** @var array<string, string> */
    private array $headers;

    /** @var list<array{name: string, value: string, options: array<string, mixed>}> */
    private array $cookies = [];

    private ?string $filePath = null;

    private bool $deleteFileAfterSend = false;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $body = '',
        private int $status = 200,
        array $headers = [],
    ) {
        $this->headers = $headers;
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function text(string $text, int $status = 200): self
    {
        return new self($text, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * @param array<mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        $json = json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
        );

        return new self($json, $status, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    /**
     * Fichier envoyé en flux, sans le charger en mémoire.
     */
    public static function file(
        string $path,
        string $downloadName,
        string $mime,
        bool $inline = false,
        bool $deleteAfterSend = false,
    ): self {
        $safeName = preg_replace('/[^\w.\- ]+/u', '_', $downloadName) ?? 'fichier';
        $response = new self('', 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => sprintf(
                '%s; filename="%s"; filename*=UTF-8\'\'%s',
                $inline ? 'inline' : 'attachment',
                str_replace('"', '', $safeName),
                rawurlencode($downloadName),
            ),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->filePath = $path;
        $response->deleteFileAfterSend = $deleteAfterSend;

        return $response;
    }

    public static function download(string $content, string $downloadName, string $mime): self
    {
        return new self($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => sprintf('attachment; filename="%s"', str_replace('"', '', $downloadName)),
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function withoutHeader(string $name): self
    {
        unset($this->headers[$name]);

        return $this;
    }

    /**
     * @param array<string, mixed> $options options de setcookie()
     */
    public function withCookie(string $name, string $value, array $options): self
    {
        $this->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options];

        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
            foreach ($this->cookies as $cookie) {
                setcookie($cookie['name'], $cookie['value'], $cookie['options']);
            }
        }

        if ($this->filePath !== null) {
            readfile($this->filePath);
            if ($this->deleteFileAfterSend) {
                unlink($this->filePath);
            }

            return;
        }

        echo $this->body;
    }
}
