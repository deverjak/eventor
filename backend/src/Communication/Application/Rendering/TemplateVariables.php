<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Rendering;

/**
 * The variables a template may use (COM-08): the whitelist of the sandbox and the sample data of the preview. Everything
 * comes from read models and is formatted for people; no entity or service is reachable from a template.
 */
final class TemplateVariables
{
    /**
     * @return array<string, string> variable => meaning
     */
    public static function documentation(): array
    {
        return [
            'organizer.name' => 'Name of the organizer',
            'organizer.contactEmail' => 'Contact e-mail of the organizer (Reply-To)',
            'event.name' => 'Name of the event',
            'event.startsAt' => 'Start of the event',
            'event.endsAt' => 'End of the event',
            'event.venue' => 'Venue name',
            'event.address' => 'Venue address',
            'recipient.name' => 'Name of the person who ordered',
            'participant.name' => 'The participant the message is about',
            'participant.activity' => 'Activity of that participant',
            'participants' => 'List of {name, activity, state} of the order (or of the activity in a campaign)',
            'order.number' => 'Order number',
            'order.amountDue' => 'Amount due of the order',
            'order.outstanding' => 'Amount still to pay',
            'order.holdExpiresAt' => 'Until when the places are held',
            'activity.name' => 'Name of the activity',
            'activity.startsAt' => 'Start of the activity',
            'payment.amount' => 'Amount of the payment request',
            'payment.accountNumber' => 'Account number to pay to',
            'payment.iban' => 'IBAN to pay to',
            'payment.variableSymbol' => 'Variable symbol',
            'payment.message' => 'Message for the recipient of the payment',
            'payment.dueAt' => 'Due date of the payment',
            'refund.amount' => 'Refunded amount',
            'selfServiceUrl' => 'Link to the self-service page of the order',
            'invitation.organizerName' => 'Team the person is invited to',
            'invitation.role' => 'Role offered',
            'invitation.acceptUrl' => 'Link that accepts the invitation',
            'invitation.expiresAt' => 'Until when the invitation is valid',
            'news.title' => 'Title of the news post',
            'news.body' => 'Text of the news post',
            'program' => 'List of {title, startsAt, location} of the changed program items',
        ];
    }

    /**
     * The empty frame of every variable, so that a template referring to a part that does not apply to a message
     * renders empty instead of failing in strict mode.
     *
     * @return array<string, mixed>
     */
    public static function blank(): array
    {
        return [
            'organizer' => ['name' => '', 'contactEmail' => ''],
            'event' => ['name' => '', 'startsAt' => '', 'endsAt' => '', 'venue' => '', 'address' => ''],
            'recipient' => ['name' => ''],
            'participant' => ['name' => '', 'activity' => ''],
            'participants' => [],
            'order' => ['number' => '', 'amountDue' => '', 'outstanding' => '', 'holdExpiresAt' => ''],
            'activity' => ['name' => '', 'startsAt' => ''],
            'payment' => ['amount' => '', 'accountNumber' => '', 'iban' => '', 'variableSymbol' => '', 'message' => '', 'dueAt' => ''],
            'refund' => ['amount' => ''],
            'selfServiceUrl' => '',
            'invitation' => ['organizerName' => '', 'role' => '', 'acceptUrl' => '', 'expiresAt' => ''],
            'news' => ['title' => '', 'body' => ''],
            'program' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sample(string $organizerName = 'Klub Plzeň', string $contactEmail = 'info@example.test'): array
    {
        return [
            'organizer' => ['name' => $organizerName, 'contactEmail' => $contactEmail],
            'event' => ['name' => 'Borský podzimní víkend 2026', 'startsAt' => 'sobota 21. listopadu 2026 8:00', 'endsAt' => 'neděle 22. listopadu 2026 16:00', 'venue' => 'Borský park', 'address' => 'Plzeň'],
            'recipient' => ['name' => 'Jana Nováková'],
            'participant' => ['name' => 'Anna Nováková', 'activity' => 'Dětský závod'],
            'participants' => [
                ['name' => 'Anna Nováková', 'activity' => 'Dětský závod', 'state' => 'confirmed'],
                ['name' => 'Jana Nováková', 'activity' => 'Trail 5 km', 'state' => 'confirmed'],
            ],
            'order' => ['number' => 'EV-2026-0001', 'amountDue' => '400 Kč', 'outstanding' => '400 Kč', 'holdExpiresAt' => 'pondělí 23. listopadu 2026 12:00'],
            'activity' => ['name' => 'Dětský závod', 'startsAt' => 'neděle 22. listopadu 2026 10:00'],
            'payment' => ['amount' => '400 Kč', 'accountNumber' => '123456789/0100', 'iban' => 'CZ6501000000000123456789', 'variableSymbol' => '2026000001', 'message' => 'EV-2026-0001', 'dueAt' => 'pátek 27. listopadu 2026'],
            'refund' => ['amount' => '100 Kč'],
            'selfServiceUrl' => 'https://example.test/samoobsluha/ukazka',
            'invitation' => ['organizerName' => $organizerName, 'role' => 'event_manager', 'acceptUrl' => 'https://example.test/pozvanka/ukazka', 'expiresAt' => '30. listopadu 2026'],
            'news' => ['title' => 'Nové informace', 'body' => 'Prezence začíná už v 7:30.'],
            'program' => [
                ['title' => 'Start dětského závodu', 'startsAt' => 'neděle 22. listopadu 2026 10:00', 'location' => 'Louka u rybníka'],
            ],
        ];
    }
}
