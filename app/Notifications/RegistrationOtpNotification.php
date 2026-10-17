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
        private readonly string $registrationUrl,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your SkinSewa invitation')
            ->greeting('Hello,')
            ->line('An administrator invited you to join SkinSewa.')
            ->action('Complete registration', $this->registrationUrl)
            ->line('On the registration page, confirm your details and enter this one-time code:')
            ->line('Your one-time registration code is: '.$this->otp)
            ->line('The link and code expire in '.$this->expiresInMinutes.' minutes. The code can be used once.')
            ->line('Your email address and user type are already set by the invitation.');
    }
}
