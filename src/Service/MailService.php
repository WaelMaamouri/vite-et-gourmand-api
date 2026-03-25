<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\HttpClientInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;

class MailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%app.mailer_from_email%')]
        private readonly string $fromEmail,
        #[Autowire('%env(BREVO_API_KEY)%')]
        private readonly string $brevoApiKey = '',
        private readonly ?HttpClientInterface $httpClient = null,
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

        try {
            $this->mailer->send($email);
            return;
        } catch (TransportExceptionInterface) {
            // Fallback to Brevo HTTPS API when SMTP is unreachable on hosting.
            if ($this->brevoApiKey === '') {
                throw new TransportException('SMTP unavailable and BREVO_API_KEY missing.');
            }
        }

        $client = $this->httpClient ?? HttpClient::create();
        $payload = [
            'sender' => ['email' => $this->fromEmail],
            'to' => [['email' => $to]],
            'subject' => $subject,
            'textContent' => $textBody,
        ];

        if ($htmlBody !== null && $htmlBody !== '') {
            $payload['htmlContent'] = $htmlBody;
        }

        $response = $client->request('POST', 'https://api.brevo.com/v3/smtp/email', [
            'headers' => [
                'accept' => 'application/json',
                'content-type' => 'application/json',
                'api-key' => $this->brevoApiKey,
            ],
            'json' => $payload,
            'timeout' => 10,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new TransportException('Brevo API send failed with HTTP ' . $response->getStatusCode());
        }
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
