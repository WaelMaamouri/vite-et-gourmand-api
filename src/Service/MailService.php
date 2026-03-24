<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%app.mailer_from_email%')]
        private readonly string $fromEmail,
    ) {
    }

    public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): void
    {
        $email = (new Email())
            ->from($this->fromEmail)
            ->to($to)
            ->subject($subject)
            ->text($textBody);

        if ($htmlBody !== null && $htmlBody !== '') {
            $email->html($htmlBody);
        }

        $this->mailer->send($email);
    }

    /** Email envoyé après inscription réussie. */
    public function sendRegistrationConfirmation(string $to, string $prenom): void
    {
        $greeting = trim($prenom) !== '' ? $prenom : 'cher client';
        $subject = 'Bienvenue — votre compte Vite & Gourmand';
        $text = <<<TXT
Bonjour {$greeting},

Nous avons bien enregistré votre inscription sur Vite & Gourmand.

Vous pouvez dès maintenant vous connecter avec l’adresse email utilisée à l’inscription.

Cordialement,
L’équipe Vite & Gourmand
TXT;

        $this->send($to, $subject, $text);
    }
}
