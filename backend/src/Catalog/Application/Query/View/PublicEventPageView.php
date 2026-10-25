<?php

declare(strict_types=1);

namespace Eventor\Catalog\Application\Query\View;

use Eventor\Catalog\Contract\VenueView;

/**
 * The public page of a published event: description, activities with prices and availability, program, news, form.
 */
final readonly class PublicEventPageView
{
    /**
     * @param list<VenueView>             $venues
     * @param list<string>                $paymentMethods
     * @param list<PublicActivityView>    $activities
     * @param list<PublicProgramItemView> $program
     * @param list<PublicNewsView>        $news
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public ?string $shortDescription,
        public ?string $description,
        public ?string $coverImageUrl,
        public ContactView $contact,
        public string $timezone,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
        public string $publicationState,
        public string $registrationState,
        public ?\DateTimeImmutable $registrationOpensAt,
        public ?\DateTimeImmutable $registrationClosesAt,
        public array $venues,
        public array $paymentMethods,
        public ?AvailabilityStatusView $availability,
        public bool $availabilityShown,
        public array $activities,
        public array $program,
        public array $news,
        public PublicFormView $form,
    ) {}
}
