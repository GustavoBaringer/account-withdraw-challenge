<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Notification\AbstractNotification;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SymfonyMailer implements MailerInterface
{
    private Mailer $mailer;

    public function __construct()
    {
        $host = env('MAIL_HOST', 'mailhog');
        $port = env('MAIL_PORT', 1025);
        $dsn = "smtp://{$host}:{$port}";

        $transport = Transport::fromDsn($dsn);
        $this->mailer = new Mailer($transport);
    }

    public function send(AbstractNotification $notification): void
    {
        $fromAddress = env('MAIL_FROM_ADDRESS', 'noreply@tecnofit.com');
        $fromName = env('MAIL_FROM_NAME', 'Tecnofit');

        $email = (new Email())
            ->from(new Address($fromAddress, $fromName))
            ->to($notification->getTo())
            ->subject($notification->getSubject())
            ->html($notification->getHtmlBody());

        if ($textBody = $notification->getTextBody()) {
            $email->text($textBody);
        }

        $this->mailer->send($email);
    }
}
