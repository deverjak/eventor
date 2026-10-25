<?php

declare(strict_types=1);

namespace Eventor\Identity\UserInterface\Console;

use Eventor\Identity\Application\CreateOwner\CreateOwner;
use Eventor\Shared\Application\Messaging\CommandBus;
use Eventor\Shared\Domain\Exception\DomainException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\ValidationFailedException;

/**
 * Bootstraps an organizer with its owner for the demo, the black-box API tests and local development.
 * Goes through the command bus like every write (transaction, audit, outbox).
 *
 *     bin/console eventor:identity:create-owner --email=jana@example.test --password=… --organizer-name="Klub" --slug=klub
 */
#[AsCommand(name: 'eventor:identity:create-owner', description: 'Create an organizer with its first owner (and the account, unless it exists)')]
final readonly class CreateOwnerCommand
{
    public function __construct(private CommandBus $commands) {}

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Login e-mail of the owner (required)')]
        ?string $email = null,
        #[Option(description: 'Password, 10+ characters (required); ignored when the account already exists')]
        ?string $password = null,
        #[Option(description: 'Organizer name (required)')]
        ?string $organizerName = null,
        #[Option(description: 'Organizer URL slug: lower-case letters, digits, hyphens (required)')]
        ?string $slug = null,
        #[Option(description: 'Display name of the owner; defaults to the part of the e-mail before @')]
        ?string $displayName = null,
    ): int {
        foreach (['email' => $email, 'password' => $password, 'organizer-name' => $organizerName, 'slug' => $slug] as $option => $value) {
            if (null === $value || '' === $value) {
                $io->error(\sprintf('The --%s option is required.', $option));

                return Command::INVALID;
            }
        }
        \assert(null !== $email && null !== $password && null !== $organizerName && null !== $slug);

        try {
            /** @var array{organizerId: string, userAccountId: string, membershipId: string} $result */
            $result = $this->commands->dispatch(new CreateOwner(
                $email,
                $password,
                $displayName ?? explode('@', $email)[0],
                $organizerName,
                $slug,
            ));
        } catch (ValidationFailedException $e) {
            foreach ($e->getViolations() as $violation) {
                $io->error(\sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage()));
            }

            return Command::INVALID;
        } catch (DomainException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('Organizer "%s" (%s) created, owner %s.', $organizerName, $result['organizerId'], $email));

        return Command::SUCCESS;
    }
}
