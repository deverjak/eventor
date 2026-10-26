<?php

declare(strict_types=1);

namespace Eventor\Communication\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Template\MessageTemplate;
use Eventor\Communication\Domain\Template\MessageTemplateId;
use Eventor\Communication\Domain\Template\MessageTemplateRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(MessageTemplateRepository::class)]
final readonly class DoctrineMessageTemplateRepository implements MessageTemplateRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function add(MessageTemplate $template): void
    {
        $this->em->persist($template);
    }

    public function remove(MessageTemplate $template): void
    {
        $this->em->remove($template);
    }

    public function find(MessageTemplateId $id): ?MessageTemplate
    {
        return $this->em->getRepository(MessageTemplate::class)->findOneBy(['id' => $id]);
    }

    public function findByKey(TemplateKey $key, MessageLocale $locale): ?MessageTemplate
    {
        return $this->em->getRepository(MessageTemplate::class)->findOneBy(['key' => $key, 'locale' => $locale]);
    }
}
