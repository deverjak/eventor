<?php

declare(strict_types=1);

namespace Eventor\Shared\Infrastructure\Doctrine;

/**
 * The DBAL types of every typed id that crosses a module boundary (`Shared\Domain\Id\*`). Registered once by the
 * Shared module, so any module can map `#[ORM\Column(type: OrderIdType::NAME)]` without registering anything.
 * IdTypesTest checks that this list covers every id class. Module-internal ids stay in their module.
 */
final class SharedIdTypes
{
    /**
     * @return array<string, class-string<AbstractIdType>> DBAL type name => type class
     */
    public static function all(): array
    {
        return [
            ActivityIdType::NAME => ActivityIdType::class,
            AddOnIdType::NAME => AddOnIdType::class,
            AttentionItemIdType::NAME => AttentionItemIdType::class,
            BankTransactionIdType::NAME => BankTransactionIdType::class,
            CampaignIdType::NAME => CampaignIdType::class,
            CapacityBucketIdType::NAME => CapacityBucketIdType::class,
            CheckInIdType::NAME => CheckInIdType::class,
            CheckInListIdType::NAME => CheckInListIdType::class,
            CheckInStationIdType::NAME => CheckInStationIdType::class,
            CollectionIdType::NAME => CollectionIdType::class,
            ConfirmationIdType::NAME => ConfirmationIdType::class,
            DataSubjectRequestIdType::NAME => DataSubjectRequestIdType::class,
            DeliveryIdType::NAME => DeliveryIdType::class,
            DocumentVersionIdType::NAME => DocumentVersionIdType::class,
            EventIdType::NAME => EventIdType::class,
            FormVersionIdType::NAME => FormVersionIdType::class,
            InvitationIdType::NAME => InvitationIdType::class,
            LedgerEntryIdType::NAME => LedgerEntryIdType::class,
            LegalDocumentIdType::NAME => LegalDocumentIdType::class,
            LegalRequirementIdType::NAME => LegalRequirementIdType::class,
            MembershipIdType::NAME => MembershipIdType::class,
            NewsPostIdType::NAME => NewsPostIdType::class,
            OrderIdType::NAME => OrderIdType::class,
            OrganizerIdType::NAME => OrganizerIdType::class,
            ParticipationIdType::NAME => ParticipationIdType::class,
            PaymentRequestIdType::NAME => PaymentRequestIdType::class,
            PersonIdType::NAME => PersonIdType::class,
            PhotoIdType::NAME => PhotoIdType::class,
            ProgramItemIdType::NAME => ProgramItemIdType::class,
            RefundTaskIdType::NAME => RefundTaskIdType::class,
            SelfServiceTokenIdType::NAME => SelfServiceTokenIdType::class,
            UserAccountIdType::NAME => UserAccountIdType::class,
            VariantIdType::NAME => VariantIdType::class,
            WaitlistEntryIdType::NAME => WaitlistEntryIdType::class,
            WaitlistOfferIdType::NAME => WaitlistOfferIdType::class,
            WaveIdType::NAME => WaveIdType::class,
        ];
    }
}
