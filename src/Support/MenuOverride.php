<?php

declare(strict_types=1);

namespace Kitloom\NovaAdminMenu\Support;

/**
 * What the admin has changed about one menu entry. Every field is optional:
 * a null means "leave whatever the menu already says".
 */
final class MenuOverride
{
    public function __construct(
        public readonly string $identity,
        public readonly ?int $weight = null,
        public readonly ?string $parentIdentity = null,
        public readonly ?string $label = null,
        public readonly ?string $icon = null,
        public readonly bool $hidden = false,
        /** @var array<int, string> */
        public readonly array $permissions = [],
        /** section, group or item - set only on entries this package creates */
        public readonly ?string $type = null,
        public readonly ?string $path = null,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(string $identity, array $attributes): self
    {
        return new self(
            identity: $identity,
            weight: isset($attributes['weight']) && is_numeric($attributes['weight'])
                ? (int) $attributes['weight']
                : null,
            parentIdentity: self::nullableString($attributes['parent_identity'] ?? null),
            label: self::nullableString($attributes['label'] ?? null),
            icon: self::nullableString($attributes['icon'] ?? null),
            hidden: (bool) ($attributes['hidden'] ?? false),
            permissions: self::stringList($attributes['permissions'] ?? null),
            type: self::nullableString($attributes['type'] ?? null),
            path: self::nullableString($attributes['path'] ?? null),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'weight' => $this->weight,
            'parent_identity' => $this->parentIdentity,
            'label' => $this->label,
            'icon' => $this->icon,
            'hidden' => $this->hidden,
            'permissions' => $this->permissions,
            'type' => $this->type,
            'path' => $this->path,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function with(array $attributes): self
    {
        return self::fromArray($this->identity, array_merge($this->toArray(), $attributes));
    }

    /**
     * @return array<int, string>
     */
    private static function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn ($item): string => trim((string) $item), $value),
            static fn (string $item): bool => $item !== '',
        )));
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
