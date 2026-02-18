<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Service qui gère la vérification d'email des utilisateurs.
 */
class EmailVerifier
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Envoie l'email de confirmation avec lien sécurisé.
     */
    public function sendEmailConfirmation(string $verifyRouteName, User $user): void
    {
        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            $verifyRouteName,
            (string) $user->getId(),
            (string) $user->getEmail()
        );

        $signedUrl = $signatureComponents->getSignedUrl();
        $prenom = $user->getPrenom() ?: 'client';

        $email = (new Email())
            ->from(new Address('vitegourmand00@gmail.com', 'Vite & Gourmand'))
            ->to((string) $user->getEmail())
            ->subject('Confirmez votre email – Vite & Gourmand')
            ->text(
                "Bonjour {$prenom},\n\n" .
                "Cliquez sur ce lien pour confirmer votre email :\n{$signedUrl}\n\n" .
                "Si vous n’êtes pas à l’origine de cette inscription, ignorez ce message.\n\n" .
                "Vite & Gourmand"
            )
            ->html(
                "<h2>Bonjour {$prenom} 👋</h2>
                 <p>Merci pour votre inscription.</p>
                 <p><a href=\"{$signedUrl}\">✅ Confirmer mon email</a></p>
                 <p><small>Si vous n’êtes pas à l’origine de cette inscription, ignorez ce message.</small></p>
                 <p><strong>Vite & Gourmand</strong></p>"
            );

        $this->mailer->send($email);
    }

    /**
     * Valide le lien de confirmation cliqué par l'utilisateur.
     */
    public function handleEmailConfirmation(Request $request, User $user): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
            $request->getUri(),
            (string) $user->getId(),
            (string) $user->getEmail()
        );

        $user->setIsVerified(true);

        $this->entityManager->flush();
    }
}
