<?php

declare(strict_types=1);

namespace App\Mail;

use App\Mail\Notification\AbstractNotification;

interface MailerInterface
{
    public function send(AbstractNotification $notification): void;
}
