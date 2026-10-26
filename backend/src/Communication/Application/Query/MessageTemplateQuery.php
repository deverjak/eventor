<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Query;

use Doctrine\DBAL\Connection;
use Eventor\Communication\Application\Query\View\TemplateView;
use Eventor\Communication\Application\Rendering\BuiltInTemplates;
use Eventor\Communication\Application\Rendering\TemplateVariables;
use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;
use Eventor\Communication\Domain\Exception\MessageTemplateNotFound;
use Eventor\Shared\Domain\Id\OrganizerId;

/**
 * Read model of the templates screen (COM-08): every (key, language) with the text that applies, the organizer's own or
 * the built-in one.
 */
final readonly class MessageTemplateQuery
{
    public function __construct(private Connection $connection) {}

    /**
     * @return list<TemplateView>
     */
    public function list(OrganizerId $organizer, ?TemplateKey $key = null, ?MessageLocale $locale = null): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, template_key, locale, subject, body, updated_at FROM communication.message_template WHERE organizer_id = :organizer',
            ['organizer' => $organizer->toString()],
        );
        $own = [];
        foreach ($rows as $row) {
            $own[Row::string($row, 'template_key').'|'.Row::string($row, 'locale')] = $row;
        }

        $views = [];
        foreach (null === $key ? TemplateKey::cases() : [$key] as $templateKey) {
            foreach (null === $locale ? MessageLocale::cases() : [$locale] as $templateLocale) {
                $row = $own[$templateKey->value.'|'.$templateLocale->value] ?? null;
                if (null !== $row) {
                    $views[] = self::view($row);

                    continue;
                }
                $builtIn = BuiltInTemplates::get($templateKey, $templateLocale);
                $views[] = new TemplateView(null, $templateKey->value, $templateLocale->value, $builtIn['subject'], $builtIn['body'], 'built_in', null);
            }
        }

        return $views;
    }

    /**
     * @throws MessageTemplateNotFound
     */
    public function get(OrganizerId $organizer, string $id): TemplateView
    {
        /** @var array<string, mixed>|false $row */
        $row = \Eventor\Shared\Domain\Id\AbstractId::isValid($id)
            ? $this->connection->fetchAssociative(
                'SELECT id, template_key, locale, subject, body, updated_at FROM communication.message_template WHERE organizer_id = :organizer AND id = :id',
                ['organizer' => $organizer->toString(), 'id' => $id],
            )
            : false;

        return false === $row ? throw MessageTemplateNotFound::forId($id) : self::view($row);
    }

    /**
     * @return array<string, string> variable => meaning
     */
    public function variables(): array
    {
        return TemplateVariables::documentation();
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function view(array $row): TemplateView
    {
        return new TemplateView(
            Row::string($row, 'id'),
            Row::string($row, 'template_key'),
            Row::string($row, 'locale'),
            Row::string($row, 'subject'),
            Row::string($row, 'body'),
            'custom',
            Row::nullableDate($row, 'updated_at'),
        );
    }
}
