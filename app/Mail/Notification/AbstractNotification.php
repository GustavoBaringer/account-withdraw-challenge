<?php

declare(strict_types=1);

namespace App\Mail\Notification;

abstract class AbstractNotification
{
    abstract public function getTo(): string;

    abstract public function getSubject(): string;

    abstract public function getHtmlBody(): string;

    public function getTextBody(): ?string
    {
        return null;
    }
}
