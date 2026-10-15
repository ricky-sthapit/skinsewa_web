<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $otp,
        private readonly int $expiresInMinutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your SkinSewa registration code')
            ->greeting('Hello,')
            ->line('An administrator invited you to join SkinSewa.')
            ->line('Your one-time registration code is: '.$this->otp)
            ->line('This code expires in '.$this->expiresInMinutes.' minutes and can be used once.')
            ->line('Enter this code with the same email address that received this invitation.');
    }
}
