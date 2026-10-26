<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\PlaceOrder;

use Eventor\Legal\Contract\ApplicableRequirement;
use Eventor\Legal\Contract\ApplicableRequirements;
use Eventor\Legal\Contract\RegistrationSlot;
use Eventor\Legal\Contract\SubmittedDecision;
use Eventor\Registration\Contract\Exception\InvalidOrderRequest;

/**
 * The bridge between the decisions of a request and Legal (REG-06, LEG-11): the slots Legal resolves the requirements
 * for (one per participation-to-be; `minor` from the birth date), and the decisions matched with the requirement they
 * answer. A decision for a requirement that does not apply to its slot is refused instead of recorded.
 */
final readonly class LegalDecisions
{
    /**
     * @param list<ResolvedItem> $items
     *
     * @return list<RegistrationSlot>
     */
    public function slots(array $items): array
    {
        return array_map(
            static fn(ResolvedItem $item): RegistrationSlot => new RegistrationSlot($item->itemRef, $item->activity->id, $item->variant->id, $item->minor),
            $items,
        );
    }

    /**
     * @param list<DecisionInput> $decisions
     * @param list<ResolvedItem>  $items
     *
     * @return list<ResolvedDecision>
     *
     * @throws InvalidOrderRequest
     */
    public function resolve(array $decisions, array $items, ApplicableRequirements $requirements): array
    {
        $byRef = [];
        $perPerson = [];
        foreach ($items as $item) {
            $byRef[$item->itemRef] = $item;
            $perPerson[$item->input->personRef][] = $item;
        }

        $resolved = [];
        $violations = [];
        foreach ($decisions as $i => $decision) {
            $path = 'decisions.'.$i;
            $item = null;
            if (null !== $decision->itemRef || null !== $decision->personRef) {
                $item = null !== $decision->itemRef ? ($byRef[$decision->itemRef] ?? null) : null;
                if (null === $item && null === $decision->itemRef && 1 === \count($perPerson[$decision->personRef] ?? [])) {
                    $item = $perPerson[$decision->personRef][0];
                }
                if (null === $item) {
                    $violations[] = ['path' => $path.'.itemRef', 'message' => 'The decision refers to an unknown or ambiguous item.', 'code' => 'unknown_item'];
                    continue;
                }
            }

            $applicable = $this->find(null === $item ? $requirements->orderLevel : ($requirements->perSlot[$item->itemRef] ?? []), $decision);
            if (null === $applicable) {
                $violations[] = ['path' => $path.'.requirementId', 'message' => 'This requirement or document version does not apply here.', 'code' => 'requirement_not_applicable'];
                continue;
            }
            $resolved[] = new ResolvedDecision($decision, $applicable, $item);
        }

        if ([] !== $violations) {
            throw new InvalidOrderRequest($violations);
        }

        return $resolved;
    }

    /**
     * @param list<ResolvedDecision> $decisions
     *
     * @return list<SubmittedDecision>
     */
    public function submitted(array $decisions): array
    {
        return array_map(
            static fn(ResolvedDecision $d): SubmittedDecision => new SubmittedDecision($d->input->requirementId, $d->input->versionId, $d->item?->itemRef, $d->input->decision),
            $decisions,
        );
    }

    /**
     * @param list<ApplicableRequirement> $candidates
     */
    private function find(array $candidates, DecisionInput $decision): ?ApplicableRequirement
    {
        foreach ($candidates as $candidate) {
            if ($candidate->requirementId->equals($decision->requirementId) && $candidate->versionId->equals($decision->versionId)) {
                return $candidate;
            }
        }

        return null;
    }
}
