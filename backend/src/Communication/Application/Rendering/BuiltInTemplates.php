<?php

declare(strict_types=1);

namespace Eventor\Communication\Application\Rendering;

use Eventor\Communication\Contract\MessageLocale;
use Eventor\Communication\Contract\TemplateKey;

/**
 * The wording the platform ships (COM-01, COM-08). An organizer overrides a (key, language) with its own
 * `MessageTemplate`; everything else falls back to these. Sandboxed Twig over the variables of `TemplateVariables`.
 */
final class BuiltInTemplates
{
    /**
     * @return array{subject: string, body: string}
     */
    public static function get(TemplateKey $key, MessageLocale $locale): array
    {
        $all = self::all();
        $entry = $all[$key->value] ?? throw new \LogicException('No built-in template for '.$key->value);

        return $entry[$locale->value] ?? $entry['cs'];
    }

    /**
     * @return array<string, array<string, array{subject: string, body: string}>>
     */
    private static function all(): array
    {
        $sign = "\n\nS pozdravem\n{{ organizer.name }}";
        $signEn = "\n\nBest regards\n{{ organizer.name }}";
        $people = "{% for p in participants %}\n- {{ p.name }}: {{ p.activity }}{% endfor %}";
        $payment = "Částka k úhradě: {{ payment.amount }}\nČíslo účtu: {{ payment.accountNumber }}\nIBAN: {{ payment.iban }}\nVariabilní symbol: {{ payment.variableSymbol }}\nZpráva pro příjemce: {{ payment.message }}\nSplatnost: {{ payment.dueAt }}";
        $paymentEn = "Amount: {{ payment.amount }}\nAccount: {{ payment.accountNumber }}\nIBAN: {{ payment.iban }}\nVariable symbol: {{ payment.variableSymbol }}\nMessage: {{ payment.message }}\nDue: {{ payment.dueAt }}";

        return [
            'order_received' => [
                'cs' => ['subject' => 'Registrace {{ order.number }} přijata – {{ event.name }}', 'body' => "Dobrý den, {{ recipient.name }},\n\nděkujeme, vaše registrace na akci {{ event.name }} ({{ event.startsAt }}) byla přijata.\n\nObjednávka {{ order.number }}:{$people}\n\nSprávu registrace najdete zde: {{ selfServiceUrl }}{$sign}"],
                'en' => ['subject' => 'Registration {{ order.number }} received – {{ event.name }}', 'body' => "Hello {{ recipient.name }},\n\nthank you, your registration for {{ event.name }} ({{ event.startsAt }}) was received.\n\nOrder {{ order.number }}:{$people}\n\nManage your registration: {{ selfServiceUrl }}{$signEn}"],
            ],
            'payment_instructions' => [
                'cs' => ['subject' => 'Registrace {{ order.number }} přijata – platební údaje', 'body' => "Dobrý den, {{ recipient.name }},\n\nděkujeme, vaše registrace na akci {{ event.name }} ({{ event.startsAt }}) byla přijata.\n\nObjednávka {{ order.number }}:{$people}\n\nPro dokončení prosím uhraďte registraci převodem:\n\n{$payment}\n\nPlatbu usnadní QR kód v příloze této zprávy. Po přijetí platby vám potvrdíme účast.\n\nSprávu registrace najdete zde: {{ selfServiceUrl }}{$sign}"],
                'en' => ['subject' => 'Registration {{ order.number }} received – payment details', 'body' => "Hello {{ recipient.name }},\n\nthank you, your registration for {{ event.name }} ({{ event.startsAt }}) was received.\n\nOrder {{ order.number }}:{$people}\n\nPlease pay by bank transfer:\n\n{$paymentEn}\n\nUse the QR code attached to this message. We confirm your place once the payment arrives.\n\nManage your registration: {{ selfServiceUrl }}{$signEn}"],
            ],
            'payment_reminder' => [
                'cs' => ['subject' => 'Připomínka platby – objednávka {{ order.number }}', 'body' => "Dobrý den, {{ recipient.name }},\n\nobjednávka {{ order.number }} na akci {{ event.name }} zatím není uhrazena. Zbývá zaplatit {{ order.outstanding }}.\n\n{$payment}\n\nPokud jste již zaplatili, zprávu prosím ignorujte.\n\nSprávu registrace najdete zde: {{ selfServiceUrl }}{$sign}"],
                'en' => ['subject' => 'Payment reminder – order {{ order.number }}', 'body' => "Hello {{ recipient.name }},\n\norder {{ order.number }} for {{ event.name }} is not paid yet. Outstanding: {{ order.outstanding }}.\n\n{$paymentEn}\n\nIf you have already paid, please ignore this message.\n\nManage your registration: {{ selfServiceUrl }}{$signEn}"],
            ],
            'payment_partial' => [
                'cs' => ['subject' => 'Přijali jsme část platby – objednávka {{ order.number }}', 'body' => "Dobrý den, {{ recipient.name }},\n\nobdrželi jsme část platby za objednávku {{ order.number }}. Zbývá doplatit {{ order.outstanding }}.\n\n{$payment}\n\nSprávu registrace najdete zde: {{ selfServiceUrl }}{$sign}"],
                'en' => ['subject' => 'Partial payment received – order {{ order.number }}', 'body' => "Hello {{ recipient.name }},\n\nwe received a part of the payment for order {{ order.number }}. Still to pay: {{ order.outstanding }}.\n\n{$paymentEn}\n\nManage your registration: {{ selfServiceUrl }}{$signEn}"],
            ],
            'participation_confirmed' => [
                'cs' => ['subject' => 'Účast potvrzena – {{ event.name }}', 'body' => "Dobrý den, {{ recipient.name }},\n\nvaše účast na akci {{ event.name }} ({{ event.startsAt }}) je potvrzena.{$people}\n\nSprávu registrace najdete zde: {{ selfServiceUrl }}{$sign}"],
                'en' => ['subject' => 'Participation confirmed – {{ event.name }}', 'body' => "Hello {{ recipient.name }},\n\nyour participation in {{ event.name }} ({{ event.startsAt }}) is confirmed.{$people}\n\nManage your registration: {{ selfServiceUrl }}{$signEn}"],
            ],
            'reservation_expired' => [
                'cs' => ['subject' => 'Rezervace vypršela – {{ event.name }}', 'body' => "Dobrý den, {{ recipient.name }},\n\nrezervace míst z objednávky {{ order.number }} na akci {{ event.name }} vypršela, protože jsme nedostali platbu včas. Místa jsme uvolnili dalším zájemcům.\n\nPokud jste již zaplatili, ozvěte se nám na {{ organizer.contactEmail }}.{$sign}"],
                'en' => ['subject' => 'Reservation expired – {{ event.name }}', 'body' => "Hello {{ recipient.name }},\n\nthe reservation from order {{ order.number }} for {{ event.name }} expired because we did not receive the payment in time. The places were released.\n\nIf you have already paid, contact us at {{ organizer.contactEmail }}.{$signEn}"],
            ],
            'participation_cancelled' => [
                'cs' => ['subject' => 'Účast zrušena – {{ event.name }}', 'body' => "Dobrý den, {{ recipient.name }},\n\núčast {{ participant.name }} ({{ participant.activity }}) na akci {{ event.name }} byla zrušena.\n\nOtázky nám pošlete na {{ organizer.contactEmail }}.{$sign}"],
                'en' => ['subject' => 'Participation cancelled – {{ event.name }}', 'body' => "Hello {{ recipient.name }},\n\nthe participation of {{ participant.name }} ({{ participant.activity }}) in {{ event.name }} was cancelled.\n\nQuestions: {{ organizer.contactEmail }}.{$signEn}"],
            ],
            'participation_changed' => [
                'cs' => ['subject' => 'Změna vaší registrace – {{ event.name }}', 'body' => "Dobrý den, {{ recipient.name }},\n\nregistrace {{ participant.name }} na akci {{ event.name }} byla změněna, nově: {{ participant.activity }}.\n\nSprávu registrace najdete zde: {{ selfServiceUrl }}{$sign}"],
                'en' => ['subject' => 'Your registration changed – {{ event.name }}', 'body' => "Hello {{ recipient.name }},\n\nthe registration of {{ participant.name }} for {{ event.name }} was changed, now: {{ participant.activity }}.\n\nManage your registration: {{ selfServiceUrl }}{$signEn}"],
            ],
            'participation_transferred' => [
                'cs' => ['subject' => 'Účast byla přepsána – {{ event.name }}', 'body' => "Dobrý den, {{ recipient.name }},\n\núčast v objednávce {{ order.number }} na akci {{ event.name }} byla přepsána na jinou osobu.\n\nSprávu registrace najdete zde: {{ selfServiceUrl }}{$sign}"],
                'en' => ['subject' => 'Participation transferred – {{ event.name }}', 'body' => "Hello {{ recipient.name }},\n\na participation in order {{ order.number }} for {{ event.name }} was transferred to another person.\n\nManage your registration: {{ selfServiceUrl }}{$signEn}"],
            ],
            'pre_event_reminder' => [
                'cs' => ['subject' => 'Brzy začínáme – {{ activity.name }}', 'body' => "Dobrý den, {{ recipient.name }},\n\npřipomínáme, že {{ activity.startsAt }} začíná {{ activity.name }} (akce {{ event.name }}, místo: {{ event.venue }}, {{ event.address }}).{$people}\n\nTěšíme se na vás.\nVšechny údaje najdete zde: {{ selfServiceUrl }}{$sign}"],
                'en' => ['subject' => 'Starting soon – {{ activity.name }}', 'body' => "Hello {{ recipient.name }},\n\na reminder: {{ activity.name }} starts {{ activity.startsAt }} ({{ event.name }}, venue: {{ event.venue }}, {{ event.address }}).{$people}\n\nSee you there.\nAll details: {{ selfServiceUrl }}{$signEn}"],
            ],
            'activity_changed' => [
                'cs' => ['subject' => 'Změna termínu – {{ activity.name }}', 'body' => "Dobrý den, {{ recipient.name }},\n\nu aktivity {{ activity.name }} (akce {{ event.name }}) došlo ke změně. Nový začátek: {{ activity.startsAt }}.\n\nPokud vám nový termín nevyhovuje, napište nám na {{ organizer.contactEmail }}.{$sign}"],
                'en' => ['subject' => 'Schedule change – {{ activity.name }}', 'body' => "Hello {{ recipient.name }},\n\n{{ activity.name }} ({{ event.name }}) has changed. New start: {{ activity.startsAt }}.\n\nIf the new time does not suit you, write to {{ organizer.contactEmail }}.{$signEn}"],
            ],
            'program_changed' => [
                'cs' => ['subject' => 'Změna programu – {{ event.name }}', 'body' => "Dobrý den, {{ recipient.name }},\n\nprogram akce {{ event.name }} se změnil:\n{% for item in program %}\n- {{ item.startsAt }}: {{ item.title }} ({{ item.location }}){% endfor %}\n\nAktuální program najdete na stránce akce.{$sign}"],
                'en' => ['subject' => 'Program change – {{ event.name }}', 'body' => "Hello {{ recipient.name }},\n\nthe program of {{ event.name }} changed:\n{% for item in program %}\n- {{ item.startsAt }}: {{ item.title }} ({{ item.location }}){% endfor %}\n\nSee the event page for the current program.{$signEn}"],
            ],
            'event_cancelled' => [
                'cs' => ['subject' => 'Akce zrušena – {{ event.name }}', 'body' => "Dobrý den, {{ recipient.name }},\n\nbohužel jsme museli zrušit akci {{ event.name }}. Omlouváme se za komplikace. Případné vrácení plateb vyřídíme individuálně, ozveme se vám.\n\nOtázky nám pošlete na {{ organizer.contactEmail }}.{$sign}"],
                'en' => ['subject' => 'Event cancelled – {{ event.name }}', 'body' => "Hello {{ recipient.name }},\n\nunfortunately {{ event.name }} had to be cancelled. We are sorry. Refunds, if any, are handled individually and we will contact you.\n\nQuestions: {{ organizer.contactEmail }}.{$signEn}"],
            ],
            'post_event' => [
                'cs' => ['subject' => 'Děkujeme za účast – {{ event.name }}', 'body' => "Dobrý den, {{ recipient.name }},\n\nděkujeme, že jste byli součástí akce {{ event.name }}. Další materiály a informace najdete na stránce akce.{$sign}"],
                'en' => ['subject' => 'Thank you for taking part – {{ event.name }}', 'body' => "Hello {{ recipient.name }},\n\nthank you for being part of {{ event.name }}. More materials are available on the event page.{$signEn}"],
            ],
            'answers_incomplete' => [
                'cs' => ['subject' => 'Doplňte prosím údaje k registraci {{ order.number }}', 'body' => "Dobrý den, {{ recipient.name }},\n\nk registraci na akci {{ event.name }} nám ještě chybí některé údaje. Doplníte je zde: {{ selfServiceUrl }}{$sign}"],
                'en' => ['subject' => 'Please complete registration {{ order.number }}', 'body' => "Hello {{ recipient.name }},\n\nwe still miss some details for your registration for {{ event.name }}. Complete them here: {{ selfServiceUrl }}{$signEn}"],
            ],
            'reconfirmation_request' => [
                'cs' => ['subject' => 'Potvrďte prosím aktualizované podmínky – {{ event.name }}', 'body' => "Dobrý den, {{ recipient.name }},\n\npodmínky akce {{ event.name }} se změnily a potřebujeme vaše nové potvrzení. Potvrdíte je zde: {{ selfServiceUrl }}{$sign}"],
                'en' => ['subject' => 'Please confirm the updated terms – {{ event.name }}', 'body' => "Hello {{ recipient.name }},\n\nthe terms of {{ event.name }} changed and we need your new confirmation. Confirm here: {{ selfServiceUrl }}{$signEn}"],
            ],
            'consent_withdrawn' => [
                'cs' => ['subject' => 'Potvrzení odvolání souhlasu', 'body' => "Dobrý den, {{ recipient.name }},\n\npotvrzujeme, že jsme zaznamenali odvolání vašeho souhlasu. Vaše registrace na akci {{ event.name }} zůstává v platnosti.{$sign}"],
                'en' => ['subject' => 'Consent withdrawal confirmed', 'body' => "Hello {{ recipient.name }},\n\nwe confirm that your consent was withdrawn. Your registration for {{ event.name }} stays valid.{$signEn}"],
            ],
            'refund_settled' => [
                'cs' => ['subject' => 'Vrácení platby – objednávka {{ order.number }}', 'body' => "Dobrý den, {{ recipient.name }},\n\nvrátili jsme vám {{ refund.amount }} z objednávky {{ order.number }} na akci {{ event.name }}. Peníze by měly dorazit do několika pracovních dnů.{$sign}"],
                'en' => ['subject' => 'Refund – order {{ order.number }}', 'body' => "Hello {{ recipient.name }},\n\nwe refunded {{ refund.amount }} from order {{ order.number }} for {{ event.name }}. The money should arrive within a few working days.{$signEn}"],
            ],
            'self_service_link' => [
                'cs' => ['subject' => 'Odkaz na správu registrace {{ order.number }}', 'body' => "Dobrý den, {{ recipient.name }},\n\nsprávu své registrace na akci {{ event.name }} najdete zde: {{ selfServiceUrl }}\n\nOdkaz nikomu nepřeposílejte.{$sign}"],
                'en' => ['subject' => 'Link to manage registration {{ order.number }}', 'body' => "Hello {{ recipient.name }},\n\nmanage your registration for {{ event.name }} here: {{ selfServiceUrl }}\n\nDo not forward this link.{$signEn}"],
            ],
            'member_invitation' => [
                'cs' => ['subject' => 'Pozvánka do týmu {{ invitation.organizerName }}', 'body' => "Dobrý den,\n\nbyli jste pozváni do týmu {{ invitation.organizerName }} v roli {{ invitation.role }}. Pozvánku přijmete zde (platí do {{ invitation.expiresAt }}):\n\n{{ invitation.acceptUrl }}\n\nTým Eventor"],
                'en' => ['subject' => 'Invitation to the team of {{ invitation.organizerName }}', 'body' => "Hello,\n\nyou were invited to the team of {{ invitation.organizerName }} as {{ invitation.role }}. Accept the invitation here (valid until {{ invitation.expiresAt }}):\n\n{{ invitation.acceptUrl }}\n\nThe Eventor team"],
            ],
            'news_broadcast' => [
                'cs' => ['subject' => '{{ news.title }}', 'body' => "Dobrý den, {{ recipient.name }},\n\n{{ news.body }}\n\nAkce: {{ event.name }}{$sign}"],
                'en' => ['subject' => '{{ news.title }}', 'body' => "Hello {{ recipient.name }},\n\n{{ news.body }}\n\nEvent: {{ event.name }}{$signEn}"],
            ],
            'document_changed' => [
                'cs' => ['subject' => 'Změna dokumentů akce {{ event.name }}', 'body' => "Dobrý den, {{ recipient.name }},\n\nu akce {{ event.name }} byl aktualizován některý z dokumentů (podmínky, pravidla). Nové znění najdete na stránce akce.{$sign}"],
                'en' => ['subject' => 'Documents of {{ event.name }} changed', 'body' => "Hello {{ recipient.name }},\n\na document of {{ event.name }} (terms, rules) was updated. The new wording is on the event page.{$signEn}"],
            ],
            'custom' => [
                'cs' => ['subject' => 'Zpráva k akci {{ event.name }}', 'body' => "Dobrý den, {{ recipient.name }},\n\n{$sign}"],
                'en' => ['subject' => 'A message about {{ event.name }}', 'body' => "Hello {{ recipient.name }},\n\n{$signEn}"],
            ],
        ];
    }
}
