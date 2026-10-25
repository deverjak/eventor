<?php

declare(strict_types=1);

namespace Eventor\Tests\Unit\Shared;

use Eventor\Shared\Domain\Id\AbstractId;
use Eventor\Shared\Domain\Id\OrganizerId;
use Eventor\Shared\Domain\Messaging\IntegrationEvent;
use Eventor\Shared\Domain\Messaging\PendingSubscribers;
use Eventor\Shared\Domain\Money;
use Eventor\Tests\Integration\Architecture\ModuleCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Properties of ALL integration events by shape (chapter 06, 10): what an event may carry. This is the
 * `NoPersonalDataInEventRule` of the book as a test: only ids, money, enums, flags, counts, times and a short
 * allow-list of neutral strings. A new string field has to be added to the list on purpose, which is the review moment.
 */
final class ContractEventsShapeTest extends TestCase
{
    /** Neutral strings (codes, slugs, numbers, external ids, free text written by the organizer about the event). */
    private const array ALLOWED_STRINGS = [
        'slug', 'orderNumber', 'reason', 'variableSymbol', 'lastError', 'integration', 'trigger', 'error', 'storageKey',
        'subtenantId', 'bankConnectionId', 'reviewCaseId', 'role', 'previousRole', 'newRole',
    ];

    /** Lists of strings (field keys, property names), never values. */
    private const array ALLOWED_STRING_LISTS = ['changedFields', 'addedRequiredFieldIds'];

    public function test_every_event_is_a_final_readonly_class_with_the_envelope(): void
    {
        $events = ModuleCatalog::contractEvents();
        self::assertGreaterThanOrEqual(50, \count($events));

        foreach ($events as ['class' => $class]) {
            $reflection = new \ReflectionClass($class);
            self::assertTrue($reflection->isFinal() && $reflection->isReadOnly(), "$class must be final readonly");
            self::assertNotSame([], $reflection->getAttributes(IntegrationEvent::class), "$class needs #[IntegrationEvent]");

            $parameters = $reflection->getConstructor()?->getParameters() ?? [];
            self::assertSame('organizerId', $parameters[0]->getName());
            $first = $parameters[0]->getType();
            self::assertTrue($first instanceof \ReflectionNamedType && OrganizerId::class === $first->getName(), "$class: organizerId is a typed OrganizerId");
            self::assertSame('occurredAt', $parameters[\count($parameters) - 1]->getName());
        }
    }

    public function test_events_carry_ids_money_enums_times_and_allow_listed_strings_only(): void
    {
        foreach (ModuleCatalog::contractEvents() as ['class' => $class]) {
            $reflection = new \ReflectionClass($class);
            $doc = (string) $reflection->getConstructor()?->getDocComment();

            foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
                $name = $parameter->getName();
                $type = $parameter->getType();
                self::assertInstanceOf(\ReflectionNamedType::class, $type, "$class::$name needs a plain type");
                $typeName = $type->getName();
                $where = "$class::$name";

                if ('string' === $typeName) {
                    self::assertContains($name, self::ALLOWED_STRINGS, "$where: new string field, check it for personal data and add it to the allow-list");
                } elseif ('array' === $typeName) {
                    self::assertSame(1, preg_match('/@param\s+list<([A-Za-z]+)>\s+\$'.$name.'\b/', $doc, $m), "$where needs a `@param list<...>` docblock");
                    if ('string' === $m[1]) {
                        self::assertContains($name, self::ALLOWED_STRING_LISTS, "$where: list of strings must be field keys");
                    } else {
                        self::assertStringEndsWith('Id', $m[1], "$where: lists carry ids");
                    }
                } elseif (\in_array($typeName, ['int', 'bool'], true) || \DateTimeImmutable::class === $typeName || Money::class === $typeName || Uuid::class === $typeName) {
                    continue;
                } else {
                    self::assertTrue(is_subclass_of($typeName, AbstractId::class) || is_subclass_of($typeName, \BackedEnum::class), "$where has the type $typeName: only ids, enums, money and times travel");
                }
            }
        }
    }

    public function test_no_event_property_is_named_like_personal_data(): void
    {
        $forbidden = ['email', 'phone', 'firstname', 'lastname', 'birthdate', 'name', 'displayname', 'address', 'iban', 'accountnumber', 'note', 'answers', 'contact', 'token', 'secret', 'password'];
        foreach (ModuleCatalog::contractEvents() as ['class' => $class]) {
            foreach ((new \ReflectionClass($class))->getProperties() as $property) {
                self::assertNotContains(strtolower($property->getName()), $forbidden, "$class carries personal data or a secret");
            }
        }
    }

    public function test_every_event_without_a_plan_would_have_to_declare_no_subscribers(): void
    {
        // The pass (IntegrationEventRoutingPass) proves "plan or handler or NoSubscribers" in the compiled container;
        // here only the attribute pair is checked to be consistent with the docblock promise "Planned subscribers".
        foreach (ModuleCatalog::contractEvents() as ['class' => $class]) {
            $reflection = new \ReflectionClass($class);
            $pending = $reflection->getAttributes(PendingSubscribers::class);
            if ([] !== $pending) {
                self::assertStringContainsString('Planned subscribers:', (string) $reflection->getDocComment(), $class);
            }
        }
    }
}
