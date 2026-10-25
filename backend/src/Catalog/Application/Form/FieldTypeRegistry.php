<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Form;

use Eventor\Catalog\Domain\Form\FieldTypeName;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * Field types by name. The locator is filled by FieldTypeRegistryPass from the `#[AsFieldType]` services.
 */
final readonly class FieldTypeRegistry
{
    /**
     * @param ServiceLocator<ConstraintFactory&AnswerNormalizer&JsonSchemaFragment> $types
     */
    public function __construct(private ServiceLocator $types) {}

    public function get(FieldTypeName $name): ConstraintFactory&AnswerNormalizer&JsonSchemaFragment
    {
        $type = $this->types->get($name->value);
        \assert($type instanceof ConstraintFactory && $type instanceof AnswerNormalizer && $type instanceof JsonSchemaFragment);

        return $type;
    }
}
