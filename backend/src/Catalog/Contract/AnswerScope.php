<?php

declare(strict_types=1);

namespace Eventor\Catalog\Contract;

/**
 * To whom an answer of the form belongs (FRM-02, chapter 03 `answer_set.scope`).
 */
enum AnswerScope: string
{
    case Order = 'order';
    case Person = 'person';
    case Participation = 'participation';
    case Group = 'group';
}
