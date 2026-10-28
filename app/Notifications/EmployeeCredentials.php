<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeeCredentials extends Notification
{
    use Queueable;

    public function __construct(public readonly string $temporaryPassword) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Credenziali di accesso al portale Pineto Calcio')
            ->markdown('emails.employee-credentials', [
                'firstName' => $notifiable->first_name,
                'email' => $notifiable->email,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl' => route('login'),
            ]);
    }
}
