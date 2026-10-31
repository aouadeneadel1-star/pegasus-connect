<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Gabarits PHP. Une vue peut déclarer son gabarit de page avec $this->layout('agent'),
 * puis inclure des fragments avec $this->insert('partials/badge-statut', [...]).
 * Toute donnée affichée passe par e(). Un fragment peut recevoir l'ensemble des données
 * de la vue appelante en lui transmettant $__data.
 */
final class View
{
    private ?string $layout = null;

    /** @var array<string, mixed> */
    private array $layoutData = [];

    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(private readonly string $directory)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $this->layout = null;
        $this->layoutData = [];

        $data = [...$this->shared, ...$data];
        $content = $this->capture($template, $data);

        if ($this->layout === null) {
            return $content;
        }

        $layout = $this->layout;
        $this->layout = null;

        return $this->capture('layouts/' . $layout, [...$data, ...$this->layoutData, 'contenu' => $content]);
    }

    /**
     * @param array<string, mixed> $data données propres au gabarit de page (titre, onglet actif…)
     */
    public function layout(string $name, array $data = []): void
    {
        $this->layout = $name;
        $this->layoutData = $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(string $template, array $data = []): string
    {
        return $this->capture($template, [...$this->shared, ...$data]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function capture(string $template, array $data): string
    {
        $file = $this->directory . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException(sprintf('Vue introuvable : %s', $template));
        }

        $level = ob_get_level();
        ob_start();
        try {
            (function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })->call($this, $file, $data);
        } catch (Throwable $exception) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $exception;
        }

        return (string) ob_get_clean();
    }
}
