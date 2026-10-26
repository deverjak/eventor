<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PlaceOrder;

use Eventor\Catalog\Contract\PublishedEventSnapshot;
use Eventor\Registration\Contract\Exception\InvalidOrderRequest;
use Eventor\Registration\Contract\Exception\RegistrationNotOpen;
use Eventor\Shared\Domain\Id\ParticipationId;

/**
 * Matches the items of a request with the published offer and checks the structure of the request: every person has
 * an item and every item a person, the variant belongs to the activity, the activity is open, no person registers
 * twice for one activity. The answers are judged by the form, not here.
 */
final readonly class ItemResolver
{
    public const int MAX_ITEMS = 50;

    /**
     * @return list<ResolvedItem>
     *
     * @throws InvalidOrderRequest
     * @throws RegistrationNotOpen
     */
    public function resolve(PlaceOrder $command, PublishedEventSnapshot $offer): array
    {
        $violations = [];
        if ([] === $command->items || \count($command->items) > self::MAX_ITEMS) {
            throw InvalidOrderRequest::at('items', 'items_count', \sprintf('Choose between 1 and %d items.', self::MAX_ITEMS));
        }

        $personRefs = [];
        foreach ($command->persons as $i => $person) {
            if ('' === $person->personRef || isset($personRefs[$person->personRef]) || SubmissionValidator::ORDER_REF === $person->personRef) {
                $violations[] = self::violation('persons.'.$i.'.personRef', 'person_ref', 'Every person needs a unique reference.');
            }
            $personRefs[$person->personRef] = 0;
        }
        foreach ($command->items as $item) {
            if (isset($personRefs[$item->personRef])) {
                ++$personRefs[$item->personRef];
            }
        }

        $resolved = [];
        $seenRefs = [];
        $seenPairs = [];
        foreach ($command->items as $i => $item) {
            $path = 'items.'.$i;
            if (!isset($personRefs[$item->personRef])) {
                $violations[] = self::violation($path.'.personRef', 'unknown_person', 'The item refers to a person that is not in the order.');
                continue;
            }
            if (null !== $item->waveId) {
                $violations[] = self::violation($path.'.waveId', 'not_supported', 'Registration waves are not supported yet.');
                continue;
            }

            $activity = $offer->activity($item->activityId);
            $variant = $offer->variant($item->variantId);
            if (null === $activity || null === $variant || !$variant->activityId->equals($activity->id)) {
                $violations[] = self::violation($path.'.variantId', 'not_in_offer', 'The activity or variant is not part of this event.');
                continue;
            }
            if (!$activity->acceptsRegistrations()) {
                throw RegistrationNotOpen::forActivity($activity->id);
            }

            $ref = $item->itemRef ?? $item->personRef;
            if (null === $item->itemRef && $personRefs[$item->personRef] > 1) {
                $violations[] = self::violation($path.'.itemRef', 'item_ref_required', 'A person with several items needs an itemRef on every item.');
                continue;
            }
            if (isset($seenRefs[$ref])) {
                $violations[] = self::violation($path.'.itemRef', 'duplicate_item_ref', 'Item references must be unique.');
                continue;
            }
            $seenRefs[$ref] = true;

            $pair = $item->personRef.'|'.$activity->id->toString();
            if (isset($seenPairs[$pair])) {
                $violations[] = self::violation($path.'.activityId', 'duplicate_item', 'A person cannot register for the same activity twice.');
                continue;
            }
            $seenPairs[$pair] = true;

            $resolved[] = new ResolvedItem($item, $ref, $activity, $variant, ParticipationId::generate());
        }

        foreach ($personRefs as $ref => $count) {
            if (0 === $count) {
                $violations[] = self::violation('persons.'.$ref, 'person_without_item', 'The person has no item in the order.');
            }
        }

        if ([] !== $violations) {
            throw new InvalidOrderRequest($violations);
        }

        return $resolved;
    }

    /**
     * @return array{path: string, message: string, code: string}
     */
    private static function violation(string $path, string $code, string $message): array
    {
        return ['path' => $path, 'message' => $message, 'code' => $code];
    }
}
