<?php

declare(strict_types=1);

namespace Eventor\Identity\Contract;

/**
 * Every protected action of the platform (chapter 08, "Od matice k voterům"). The role x permission matrix of the
 * specification (section 3.2) lives in `Identity\Application\Access\PermissionMatrix`; controllers of all modules
 * declare what they need with `#[RequiresPermission(Permission::…)]`.
 */
enum Permission: string
{
    /** Every team member may see the organizer they belong to. Not a row of the specification matrix. */
    case OrganizerView = 'organizer.view';
    case OrganizerSettingsManage = 'organizer.settings.manage';
    case TeamManage = 'team.manage';
    case OrganizerLegalPublish = 'legal.organizer.publish';
    case EventEdit = 'event.edit';
    case EventLegalManage = 'legal.event.manage';
    case EventConfigure = 'event.configure';
    case ParticipantsViewFull = 'participants.view.full';
    case ParticipantsViewCheckIn = 'participants.view.checkin';
    case PersonalDataExport = 'personal_data.export';
    case RegistrationEdit = 'registration.edit';
    case PaymentExceptionAssign = 'payment.exception.assign';
    case PaymentExceptionResolve = 'payment.exception.resolve';
    case CashRecord = 'cash.record';
    case CashCorrect = 'cash.correct';
    case RefundCreate = 'refund.create';
    case RefundClose = 'refund.close';
    case CheckInPerform = 'checkin.perform';
    case CheckInCorrect = 'checkin.correct';
    case MessageSend = 'message.send';
    case ProgramManage = 'program.manage';
    case ContentManage = 'content.manage';
    case DataSubjectRequestManage = 'dsr.manage';
    case AuditViewAll = 'audit.view.all';
    case AuditViewOwnEvents = 'audit.view.own_events';
    case AuditViewFinance = 'audit.view.finance';
    case EventCancel = 'event.cancel';
}
