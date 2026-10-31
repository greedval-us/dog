<?php

namespace App\Http\Telegram\Menus;

use App\Modules\Pets\Exceptions\PetUnavailable;
use Illuminate\Support\Str;

final readonly class SavedMenuState
{
    /** @var array<array-key, mixed> */
    private array $values;

    /** @param array<string, mixed> $state */
    public function __construct(array $state, string $key)
    {
        $values = $state[$key] ?? null;
        if (! is_array($values)) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        $this->values = $values;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function integer(string $key, int $minimum = 1, ?int $default = null): int
    {
        $value = $this->values[$key] ?? $default;
        if (! is_int($value) || $value < $minimum) {
            throw new PetUnavailable('telegram.errors.stale');
        }

        return $value;
    }

    public function string(string $key): string
    {
        $value = $this->values[$key] ?? null;
        if (! is_string($value)) {
            throw new PetUnavailable('telegram.errors.stale');
        }

        return $value;
    }

    public function nullableInteger(string $key): ?int
    {
        return ($this->values[$key] ?? null) === null ? null : $this->integer($key);
    }

    public function nullableString(string $key): ?string
    {
        return ($this->values[$key] ?? null) === null ? null : $this->string($key);
    }

    public function uuid(string $key = 'token'): string
    {
        $value = $this->string($key);
        if (! Str::isUuid($value)) {
            throw new PetUnavailable('telegram.errors.stale');
        }

        return $value;
    }

    /** @return array<string, int> */
    public function integerMap(string $key): array
    {
        $values = $this->values[$key] ?? null;
        if (! is_array($values)) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        foreach ($values as $name => $value) {
            if (! is_string($name) || ! is_int($value) || $value < 1) {
                throw new PetUnavailable('telegram.errors.stale');
            }
        }

        return $values;
    }

    /** @return list<string> */
    public function stringList(string $key): array
    {
        $values = $this->values[$key] ?? null;
        if (! is_array($values) || ! array_is_list($values)) {
            throw new PetUnavailable('telegram.errors.stale');
        }
        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new PetUnavailable('telegram.errors.stale');
            }
        }

        return $values;
    }
}
