<?php

declare(strict_types=1);

namespace Eventor\Payments\UserInterface\Console;

use Eventor\Identity\Contract\OrganizerLookup;
use Eventor\Identity\Contract\OrganizerSettings;
use Eventor\Payments\Contract\Exception\BankAccountUnavailable;
use Eventor\Payments\Contract\Exception\InvalidBankAccount;
use Eventor\Payments\Contract\Exception\PairrotUnavailable;
use Eventor\Payments\Contract\PairrotOnboarding;
use Eventor\Shared\Domain\Id\OrganizerId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `eventor:pairrot:provision <organizer>`: the ORG-02 wizard from the console (demo seed, black-box suite, support).
 * Creates the Pairrot subtenant of an organizer and, with `--account`, connects its bank account and the inbound
 * address. Idempotent: running it again changes nothing.
 */
#[AsCommand(name: 'eventor:pairrot:provision', description: 'Create the Pairrot subtenant of an organizer and (optionally) connect its bank account')]
final class ProvisionPairrotCommand extends Command
{
    public function __construct(
        private readonly PairrotOnboarding $onboarding,
        private readonly ?OrganizerLookup $organizers = null,
        private readonly ?OrganizerSettings $settings = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('organizer', InputArgument::REQUIRED, 'Organizer slug or id')
            ->addOption('account', null, InputOption::VALUE_REQUIRED, 'Bank account number (prefix-number/bank) or IBAN to connect')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Label of the bank connection in Pairrot', 'Eventor')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Organizer name when it cannot be looked up')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Contact e-mail when it cannot be looked up');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $reference = (string) $input->getArgument('organizer');

        $id = null;
        $name = $this->option($input, 'name');
        $email = $this->option($input, 'email');
        if (OrganizerId::isValid($reference)) {
            $id = OrganizerId::fromString($reference);
        } elseif (null !== $this->organizers) {
            $view = $this->organizers->findBySlug($reference);
            if (null === $view) {
                $io->error(\sprintf('Organizer "%s" does not exist.', $reference));

                return Command::FAILURE;
            }
            $id = $view->id;
            $name ??= $view->name;
        }
        if (null === $id) {
            $io->error('Pass the organizer id (the slug cannot be resolved until the Identity module publishes OrganizerLookup).');

            return Command::FAILURE;
        }

        $email ??= $this->settings?->contact($id)->contactEmail;
        if (null === $name || null === $email) {
            $io->error('The organizer name and contact e-mail are needed: pass --name and --email.');

            return Command::FAILURE;
        }

        try {
            $subtenant = $this->onboarding->activateSubtenant($id, $name, $email);
            $io->writeln('Subtenant: '.$subtenant);

            $account = $this->option($input, 'account');
            if (null !== $account) {
                $connection = $this->onboarding->connectBankAccount($id, $subtenant, $account, (string) $input->getOption('label'));
                $io->writeln(\sprintf('Bank connection: %s (%s)', $connection->bankConnectionId, $connection->maskedIban));
                $io->writeln('Inbound address: '.($connection->inboundAddress ?? '-'));
                $io->writeln('Reception: '.$this->onboarding->receptionState($id, $subtenant, $connection->bankConnectionId)->value);
            }
        } catch (BankAccountUnavailable|InvalidBankAccount|PairrotUnavailable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Pairrot is provisioned for organizer '.$id->toString().'.');

        return Command::SUCCESS;
    }

    private function option(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
