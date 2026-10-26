<?php

declare(strict_types=1);

namespace Eventor\Communication\Domain\Template;

use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;

interface MessageTemplateRepository
{
    public function add(MessageTemplate $template): void;

    public function remove(MessageTemplate $template): void;

    /** The template of the current organizer (the tenant filter scopes it), or null. */
    public function find(MessageTemplateId $id): ?MessageTemplate;

    public function findByKey(TemplateKey $key, MessageLocale $locale): ?MessageTemplate;
}
