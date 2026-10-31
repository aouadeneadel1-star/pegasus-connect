<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use LogicException;

/**
 * Validation des formulaires. Règles séparées par « | » :
 * required, email, max:n, min:n, int, between:a,b, decimal, in:a,b, date, datetime, time,
 * regex:/…/, digits:n, same:champ.
 * Des règles métier peuvent être ajoutées par appel (ex. « nir ») via $custom.
 * Un champ vide non « required » est accepté et renvoyé à null.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, ?string> */
    private array $validated = [];

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $rules
     * @param array<string, string> $labels libellés affichés dans les messages
     * @param array<string, array{0: callable(string): bool, 1: string}> $custom règle => [test, message]
     */
    private function __construct(
        private readonly array $input,
        private readonly array $rules,
        private readonly array $labels,
        private readonly array $custom,
    ) {
        $this->run();
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     * @param array<string, array{0: callable(string): bool, 1: string}> $custom
     */
    public static function make(array $input, array $rules, array $labels = [], array $custom = []): self
    {
        return new self($input, $rules, $labels, $custom);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, ?string>
     */
    public function validated(): array
    {
        return $this->validated;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $definition) {
            $raw = $this->input[$field] ?? null;
            $value = is_scalar($raw) ? trim((string) $raw) : '';
            $rules = explode('|', $definition);

            if ($value === '') {
                if (in_array('required', $rules, true)) {
                    $this->errors[$field] = sprintf('%s est obligatoire.', $this->label($field));
                }
                $this->validated[$field] = null;
                continue;
            }

            foreach ($rules as $rule) {
                [$name, $argument] = array_pad(explode(':', $rule, 2), 2, '');
                $message = $this->check($name, $argument, $value, $field);
                if ($message !== null) {
                    $this->errors[$field] = $message;
                    break;
                }
            }

            $this->validated[$field] = $value;
        }
    }

    private function check(string $rule, string $argument, string $value, string $field): ?string
    {
        $label = $this->label($field);

        return match ($rule) {
            'required' => null,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? sprintf('%s n\'est pas une adresse e-mail valide.', $label) : null,
            'max' => mb_strlen($value) > (int) $argument
                ? sprintf('%s ne doit pas dépasser %d caractères.', $label, (int) $argument) : null,
            'min' => mb_strlen($value) < (int) $argument
                ? sprintf('%s doit contenir au moins %d caractères.', $label, (int) $argument) : null,
            'int' => filter_var($value, FILTER_VALIDATE_INT) === false
                ? sprintf('%s doit être un nombre entier.', $label) : null,
            'decimal' => preg_match('/^-?\d+([.,]\d+)?$/', $value) !== 1
                ? sprintf('%s doit être un nombre.', $label) : null,
            'between' => $this->checkBetween($value, $argument, $label),
            'in' => !in_array($value, explode(',', $argument), true)
                ? sprintf('%s contient une valeur non autorisée.', $label) : null,
            'date' => !self::isDate($value, 'Y-m-d')
                ? sprintf('%s doit être une date valide.', $label) : null,
            'datetime' => !self::isDate($value, 'Y-m-d\TH:i')
                ? sprintf('%s doit être une date et une heure valides.', $label) : null,
            'time' => !self::isDate($value, 'H:i')
                ? sprintf('%s doit être une heure valide (HH:MM).', $label) : null,
            'regex' => preg_match($argument, $value) !== 1
                ? sprintf('%s n\'a pas le format attendu.', $label) : null,
            'digits' => preg_match('/^\d{' . (int) $argument . '}$/', $value) !== 1
                ? sprintf('%s doit comporter %d chiffres.', $label, (int) $argument) : null,
            'same' => $value !== (string) ($this->input[$argument] ?? '')
                ? sprintf('%s ne correspond pas.', $label) : null,
            default => $this->checkCustom($rule, $value),
        };
    }

    private function checkCustom(string $rule, string $value): ?string
    {
        if (!isset($this->custom[$rule])) {
            throw new LogicException(sprintf('Règle de validation inconnue : %s', $rule));
        }

        [$test, $message] = $this->custom[$rule];

        return $test($value) ? null : $message;
    }

    private function checkBetween(string $value, string $argument, string $label): ?string
    {
        [$min, $max] = array_map('floatval', explode(',', $argument));
        $nombre = str_replace(',', '.', $value);
        if (!is_numeric($nombre) || (float) $nombre < $min || (float) $nombre > $max) {
            return sprintf('%s doit être compris entre %s et %s.', $label, (string) $min, (string) $max);
        }

        return null;
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    private static function isDate(string $value, string $format): bool
    {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);

        return $date !== false && $date->format($format) === $value;
    }
}
