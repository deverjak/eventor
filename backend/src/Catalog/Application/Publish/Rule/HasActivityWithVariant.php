<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Publish\Rule;

use Eventor\Catalog\Application\Publish\PublishCandidate;
use Eventor\Catalog\Application\Publish\PublishRule;

final readonly class HasActivityWithVariant implements PublishRule
{
    public function check(PublishCandidate $candidate): array
    {
        if ([] === $candidate->activities) {
            return [['path' => 'activities', 'message' => 'Add at least one activity.', 'code' => 'no_activity']];
        }
        $withVariant = [];
        foreach ($candidate->variants as $variant) {
            $withVariant[$variant->activityId()->toString()] = true;
        }
        $violations = [];
        foreach ($candidate->activities as $activity) {
            if (!isset($withVariant[$activity->id()->toString()])) {
                $violations[] = ['path' => 'activities.'.$activity->id()->toString(), 'message' => \sprintf('The activity "%s" has no variant, so nobody could register for it.', $activity->name()), 'code' => 'activity_without_variant'];
            }
        }

        return $violations;
    }
}
