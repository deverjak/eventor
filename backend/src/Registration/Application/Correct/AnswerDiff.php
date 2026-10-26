<?php

declare(strict_types=1);

namespace Eventor\Registration\Application\Correct;

/**
 * Which form fields differ between two answer sets: the only thing about a correction that may enter the audit.
 */
final class AnswerDiff
{
    /**
     * @param array<string, array{t: string, v: mixed}> $before
     * @param array<string, array{t: string, v: mixed}> $after
     *
     * @return list<string> field keys, sorted
     */
    public static function changedKeys(array $before, array $after): array
    {
        $changed = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            $key = (string) $key;
            if (($before[$key]['v'] ?? null) !== ($after[$key]['v'] ?? null) || \array_key_exists($key, $before) !== \array_key_exists($key, $after)) {
                $changed[] = $key;
            }
        }
        sort($changed);

        return $changed;
    }
}
