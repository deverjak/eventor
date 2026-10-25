<?php

declare(strict_types=1);

namespace Eventor\Attention\Application\Action;

/**
 * One thing a human can do with an item. `command` actions are executed by Attention through the command bus;
 * `link` actions point the UI to the owning module (`target` = resource + id), because the state they change
 * belongs to another module.
 */
final readonly class AttentionAction
{
    public const string KIND_COMMAND = 'command';
    public const string KIND_LINK = 'link';

    /**
     * @param array{resource: string, id: string}|null $target
     */
    private function __construct(
        public string $key,
        public string $kind,
        public bool $noteAllowed,
        public ?array $target,
    ) {}

    public static function command(string $key, bool $noteAllowed = false): self
    {
        return new self($key, self::KIND_COMMAND, $noteAllowed, null);
    }

    public static function link(string $key, string $resource, string $id): self
    {
        return new self($key, self::KIND_LINK, false, ['resource' => $resource, 'id' => $id]);
    }

    /**
     * @return array{key: string, kind: string, noteAllowed: bool, target: array{resource: string, id: string}|null}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'kind' => $this->kind, 'noteAllowed' => $this->noteAllowed, 'target' => $this->target];
    }
}
