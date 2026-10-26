<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Answers;

use Eventor\Catalog\Contract\AnswerScope;
use Eventor\Catalog\Contract\FormSelection;
use Eventor\Catalog\Contract\FormValidator;
use Eventor\Registration\Contract\AnswersState;
use Eventor\Registration\Domain\Answer\AnswerSetRepository;
use Eventor\Registration\Domain\Participation\Participation;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * `participation.answers_state` (S17): does the person have an answer to every required field of a form version that
 * applies to this participation? Judged against the version named by the caller (the current one after a new field was
 * published, the current one again after a correction), with the person's current answer set.
 */
final readonly class AnswersCompleteness
{
    public function __construct(
        private FormValidator $forms,
        private AnswerSetRepository $answerSets,
    ) {}

    /**
     * @return list<string> the keys of the required person fields without an answer
     */
    public function missing(OrganizerId $organizer, FormVersionId $version, Participation $participation): array
    {
        $current = $this->answerSets->current('person', $participation->personId()->toString());

        return $this->forms->missingRequiredFields(
            $organizer,
            $version,
            AnswerScope::Person,
            new FormSelection([$participation->activityId()], [$participation->variantId()]),
            null === $current ? [] : array_map(strval(...), array_keys($current->answers())),
        );
    }

    /**
     * Sets the dimension from the result and tells whether the participation is incomplete now.
     */
    public function apply(OrganizerId $organizer, FormVersionId $version, Participation $participation): bool
    {
        $incomplete = [] !== $this->missing($organizer, $version, $participation);
        $participation->changeAnswersState($incomplete ? AnswersState::Incomplete : AnswersState::Complete, $incomplete ? 'A required field has no answer' : null);

        return $incomplete;
    }
}
