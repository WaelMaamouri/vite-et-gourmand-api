<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailService
{
    public function __construct(private MailerInterface $mailer)
    {
    }

    public function send(string $to, string $subject, string $content): void
    {
        $email = (new Email())
            ->from('vitegourmand00@gmail.com')
            ->to($to)
            ->subject($subject)
            ->text($content);

            file_put_contents(
        __DIR__.'/../../var/log/mails.log',
            $subject." -> ".$to."\n",
        FILE_APPEND
);
    }
}
