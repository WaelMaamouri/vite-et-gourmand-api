<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Commande Symfony permettant de définir ou réinitialiser
 * le mot de passe d’un utilisateur depuis la console.
 */
#[AsCommand(
    name: 'app:user:set-password',
    description: 'Set/reset a user password (hashed properly).'
)]
class UserSetPasswordCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    /**
     * Configuration des arguments de la commande :
     */
    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addArgument('password', InputArgument::REQUIRED, 'New plain password');
    }

    /**
     * Méthode exécutée quand la commande est lancée.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $plain = (string) $input->getArgument('password');

        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $output->writeln('<error>User not found for email: '.$email.'</error>');
            return Command::FAILURE;
        }

        $user->setPassword($this->hasher->hashPassword($user, $plain));
        $this->em->flush();

        $output->writeln('<info>Password updated for '.$email.'</info>');
        return Command::SUCCESS;
    }
}
