<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

/**
 * One version of the form (draft or published) with its fields.
 */
final readonly class FormVersionView
{
    /**
     * @param list<FormFieldDefinitionView> $fields
     */
    public function __construct(
        public string $id,
        public int $number,
        public bool $published,
        public ?\DateTimeImmutable $publishedAt,
        public ?string $schemaSha256,
        public \DateTimeImmutable $updatedAt,
        public array $fields,
    ) {}
}
