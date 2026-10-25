<?php

declare(strict_types=1);

namespace Eventor\Registration\Contract;

/**
 * `participation.answers_state`: does the participation have answers to every required field of the current form version?
 */
enum AnswersState: string
{
    case Complete = 'complete';
    case Incomplete = 'incomplete';
}
