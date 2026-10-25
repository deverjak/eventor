<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Publish;

/**
 * One publication rule of an event (chapter 04, path 1). Rules are tagged services; a new rule is a new class.
 */
interface PublishRule
{
    public const string TAG = 'eventor.catalog.publish_rule';

    /**
     * @return list<array{path: string, message: string, code: string}> the broken rules, empty when satisfied
     */
    public function check(PublishCandidate $candidate): array;
}
