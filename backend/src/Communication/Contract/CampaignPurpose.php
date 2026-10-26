<?php

declare(strict_types=1);

namespace Eventor\Communication\Contract;

/**
 * PRV-07: organisational messages rest on the contract or a legitimate interest, marketing on consent.
 */
enum CampaignPurpose: string
{
    case Organizational = 'organizational';
    case Marketing = 'marketing';
}
