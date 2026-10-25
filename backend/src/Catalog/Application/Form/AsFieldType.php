<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form;

/**
 * Marks a service as the implementation of one form field type (ADR 007). The name must be a value of
 * `FieldTypeName`; FieldTypeRegistryPass collects the tagged services and fails the build for a missing capability,
 * a duplicate name or a `FieldTypeName` case without implementation.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsFieldType
{
    public const string TAG = 'eventor.field_type';

    public function __construct(public string $name) {}
}
