<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

use Eventor\Catalog\Contract\Exception\FormValidationFailed;
use Eventor\Catalog\Contract\Exception\FormVersionNotFound;
use Eventor\Shared\Domain\Id\FormVersionId;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Server-side validation of submitted answers against a form version (FRM-05, FRM-07). Visibility conditions are
 * evaluated here, never trusted from the client.
 */
interface FormValidator
{
    /**
     * @throws FormVersionNotFound
     * @throws FormValidationFailed with the path of every offending field
     */
    public function validate(OrganizerId $organizer, FormVersionId $version, FormSubmission $submission): ValidatedAnswers;

    /**
     * Required fields of the scope that apply to the selection and have no answer yet (S17: answers added after a
     * new form version). Used to compute `answers_state`.
     *
     * @param list<string> $answeredFieldKeys
     *
     * @return list<string> field keys
     *
     * @throws FormVersionNotFound
     */
    public function missingRequiredFields(OrganizerId $organizer, FormVersionId $version, AnswerScope $scope, FormSelection $selection, array $answeredFieldKeys): array;
}
