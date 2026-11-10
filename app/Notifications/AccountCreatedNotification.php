<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a newly created user (or on request by an administrator) with a link to set their password.
 */
class AccountCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $hours = intdiv((int) config('auth.passwords.users.expire'), 60);

        return (new MailMessage)
            ->subject('Your '.config('app.name').' account')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('An account has been created for you on '.config('app.name').'.')
            ->line('Use the button below to set your password and sign in.')
            ->action('Set your password', $url)
            ->line("This link expires in {$hours} hours. If it expires, ask your administrator to send a new one.");
    }
}
