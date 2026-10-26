<?php

declare(strict_types=1);

namespace Eventor\Communication\Contract;

/**
 * Who a campaign targets (COM-04), stored as JSON on the campaign. Empty lists mean "no restriction". The values are
 * the ids and enum values of other modules as plain strings: the application layer validates and maps them.
 */
final readonly class CampaignAudienceSpec
{
    /**
     * @param list<string> $activityIds
     * @param list<string> $variantIds
     * @param list<string> $participationStates empty = pending, confirmed and requires_attention
     * @param list<string> $financialStates
     */
    public function __construct(
        public array $activityIds = [],
        public array $variantIds = [],
        public array $participationStates = [],
        public array $financialStates = [],
        public ?bool $checkedIn = null,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            self::strings($data['activityIds'] ?? []),
            self::strings($data['variantIds'] ?? []),
            self::strings($data['participationStates'] ?? []),
            self::strings($data['financialStates'] ?? []),
            \is_bool($data['checkedIn'] ?? null) ? $data['checkedIn'] : null,
        );
    }

    /**
     * @return array{activityIds: list<string>, variantIds: list<string>, participationStates: list<string>, financialStates: list<string>, checkedIn: bool|null}
     */
    public function toArray(): array
    {
        return [
            'activityIds' => $this->activityIds,
            'variantIds' => $this->variantIds,
            'participationStates' => $this->participationStates,
            'financialStates' => $this->financialStates,
            'checkedIn' => $this->checkedIn,
        ];
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn(mixed $item): string => \is_scalar($item) ? (string) $item : '', $value));
    }
}
