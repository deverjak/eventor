<?php

declare(strict_types=1);

namespace Eventor\Communication\Contract;

/**
 * The messages the platform sends (COM-01) and their template keys. The key is also the stable trigger of a
 * transactional delivery. `Custom` is the free-form base of an organizer's own campaign (COM-03).
 */
enum TemplateKey: string
{
    case OrderReceived = 'order_received';
    case PaymentInstructions = 'payment_instructions';
    case PaymentReminder = 'payment_reminder';
    case PaymentPartial = 'payment_partial';
    case ParticipationConfirmed = 'participation_confirmed';
    case ReservationExpired = 'reservation_expired';
    case ParticipationCancelled = 'participation_cancelled';
    case ParticipationChanged = 'participation_changed';
    case ParticipationTransferred = 'participation_transferred';
    case PreEventReminder = 'pre_event_reminder';
    case ActivityChanged = 'activity_changed';
    case ProgramChanged = 'program_changed';
    case EventCancelled = 'event_cancelled';
    case PostEvent = 'post_event';
    case AnswersIncomplete = 'answers_incomplete';
    case ReconfirmationRequest = 'reconfirmation_request';
    case ConsentWithdrawn = 'consent_withdrawn';
    case RefundSettled = 'refund_settled';
    case SelfServiceLink = 'self_service_link';
    case MemberInvitation = 'member_invitation';
    case NewsBroadcast = 'news_broadcast';
    case DocumentChanged = 'document_changed';
    case Custom = 'custom';

    /** Sent to many people at once by an organizer's decision (a campaign) rather than by a single event. */
    public function isCampaign(): bool
    {
        return match ($this) {
            self::ActivityChanged, self::ProgramChanged, self::EventCancelled, self::PostEvent, self::NewsBroadcast, self::DocumentChanged, self::Custom => true,
            default => false,
        };
    }
}
