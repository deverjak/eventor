<?php

declare(strict_types=1);

/**
 * Which foreign `Contract` layers a module may depend on (chapter 02, "Hranice jako kód"). `bin/deptrac-layers` turns it into
 * the ruleset of `deptrac.yaml` for Application, Infrastructure and UserInterface; Domain depends on Shared and on the
 * module's own Contract only; a Contract depends on Shared only, so Contracts can never form a cycle.
 *
 * An edge exists because the module calls a facade of the other module or subscribes to its integration events (the
 * subscriber imports the event class). The reason is documentation: when a module needs a new edge, add it here with
 * the reason and mention it in the report (the matrix is reviewed like an ADR). `ModuleContractMatrixTest` checks that
 * every planned subscriber of an event may import it.
 *
 * Registration <-> Payments is two-way on purpose and stays asynchronous in spirit: Registration calls Payments'
 * facades during `PlaceOrder`, Payments only subscribes to Registration's events.
 */
return [
    'Identity' => [
        'Payments' => 'PairrotOnboarding (the onboarding wizard calls Pairrot through Payments)',
    ],
    'Catalog' => [
        'Identity' => 'Permission attributes, OrganizerSettings (payment publish rules), identity.pairrot_connected',
        'Registration' => 'CapacityProvisioner / CapacityQuery, registration.capacity_released',
    ],
    'Legal' => [
        'Identity' => 'Permission attributes, organizer policy',
        'Registration' => 'ParticipationQuery (activity/variant of a participation), registration.participation_transferred',
    ],
    'Registration' => [
        'Identity' => 'OrganizerLookup (public zone), OrganizerSettings, Permission attributes',
        'Catalog' => 'EventCatalog, FormValidator, FormSchemaProvider, catalog events',
        'Legal' => 'RequirementResolver, ConfirmationRecorder, ConsentQuery, legal events',
        'Payments' => 'OrderFinanceInitializer, FinanceQuery, payments.order_payment_changed / payment_request_issued',
    ],
    'Payments' => [
        'Identity' => 'OrganizerSettings (Pairrot ids, policy), Permission attributes',
        'Registration' => 'registration.order_placed / order_amount_changed',
    ],
    'CheckIn' => [
        'Identity' => 'Permission attributes, OrganizerSettings (cash policy), device authentication',
        'Catalog' => 'EventCatalog (lists), catalog.event_published / event_cancelled / activity_rescheduled',
        'Registration' => 'ParticipationQuery, ParticipationQrTokens, registration events',
        'Payments' => 'CashDesk (RecordCashAndCheckIn), FinanceQuery',
        'Legal' => 'ConfirmationRecorder (confirmation on site, channel staff)',
    ],
    'Communication' => [
        'Identity' => 'OrganizerSettings (sender), InvitationQuery, identity.member_invited',
        'Catalog' => 'EventCatalog, ProgramReader, NewsReader, catalog events',
        'Legal' => 'ConsentQuery, legal events',
        'Registration' => 'RecipientQuery, SelfServiceLinks, ParticipationQrTokens, registration events',
        'Payments' => 'FinanceQuery (instructions), payments events',
        'CheckIn' => 'CheckInQuery (segments)',
    ],
    'Content' => [
        'Identity' => 'Permission attributes',
        'Catalog' => 'EventCatalog (collection of an event)',
        'Legal' => 'ConsentQuery (likeness consents of a gallery)',
    ],
    'Privacy' => [
        'Identity' => 'Permission attributes',
        'Catalog' => 'catalog.event_completed (retention clock)',
        'Registration' => 'PersonDirectory',
    ],
    'Audit' => [
        'Identity' => 'Permission attributes (audit.view.*)',
    ],
    'Attention' => [
        'Identity' => 'Permission attributes',
        'Registration' => 'registration.participation_requires_attention / late_payment_received',
        'Payments' => 'payments events (revoked, failed, overpayment, refund task, unmatched transaction...)',
        'Legal' => 'legal.consent_withdrawn',
        'Communication' => 'communication.delivery_failed',
        'CheckIn' => 'checkin.escalation_raised',
        'Content' => 'content.collection_published',
        'Privacy' => 'privacy.data_subject_request_opened',
    ],
    'Reporting' => [
        'Identity' => 'Permission attributes',
        'Catalog' => 'catalog.event_published',
        'Registration' => 'registration.order_placed / participation_expired',
        'Payments' => 'payments.order_payment_changed / cash_recorded / ledger_entry_corrected',
        'CheckIn' => 'checkin events',
        'Communication' => 'communication.campaign_sent',
        'Privacy' => 'privacy.person_anonymized',
    ],
];
