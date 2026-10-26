<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Rendering;

use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Template\MessageTemplateRepository;

/**
 * Which wording applies to (key, language): the organizer's own template of the current tenant, otherwise the built-in
 * one (which falls back to Czech for a language it lacks).
 */
final readonly class TemplateCatalog
{
    public function __construct(private MessageTemplateRepository $templates) {}

    public function resolve(TemplateKey $key, MessageLocale $locale): ResolvedTemplate
    {
        $own = $this->templates->findByKey($key, $locale);
        if (null !== $own) {
            return new ResolvedTemplate($own->subject(), $own->body(), true);
        }
        $builtIn = BuiltInTemplates::get($key, $locale);

        return new ResolvedTemplate($builtIn['subject'], $builtIn['body'], false);
    }
}
