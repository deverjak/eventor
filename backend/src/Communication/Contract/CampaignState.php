<?php

declare(strict_types=1);

namespace Eventor\Communication\Contract;

enum CampaignState: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Sent = 'sent';
    case Cancelled = 'cancelled';
}
