<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Campaign;

use Eventor\Communication\Contract\TemplateKey;

/**
 * Working names of the campaigns the platform proposes or sends by itself.
 */
final class CampaignNames
{
    public static function for(TemplateKey $key): string
    {
        return match ($key) {
            TemplateKey::ActivityChanged => 'Změna aktivity',
            TemplateKey::ProgramChanged => 'Změna programu',
            TemplateKey::EventCancelled => 'Zrušení akce',
            TemplateKey::PostEvent => 'Poděkování po akci',
            TemplateKey::NewsBroadcast => 'Novinky k akci',
            TemplateKey::DocumentChanged => 'Změna dokumentů',
            default => 'Zpráva účastníkům',
        };
    }
}
