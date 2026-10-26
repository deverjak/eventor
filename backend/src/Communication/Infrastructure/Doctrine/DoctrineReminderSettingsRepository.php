<?php

declare(strict_types=1);

namespace Eventor\Communication\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Communication\Domain\Reminder\ReminderSettings;
use Eventor\Communication\Domain\Reminder\ReminderSettingsRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ReminderSettingsRepository::class)]
final readonly class DoctrineReminderSettingsRepository implements ReminderSettingsRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(ReminderSettings $settings): void
    {
        $this->em->persist($settings);
    }

    public function current(): ?ReminderSettings
    {
        return $this->em->getRepository(ReminderSettings::class)->findOneBy([]);
    }
}
