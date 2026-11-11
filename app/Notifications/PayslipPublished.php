<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayslipPublished extends Notification
{
    public function __construct(
        public readonly int $month,
        public readonly int $year,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('La tua busta paga è disponibile - Pineto Calcio')
            ->line('La tua busta paga per il periodo '.str_pad((string) $this->month, 2, '0', STR_PAD_LEFT).'/'.$this->year.' è disponibile.')
            ->line('Accedi al portale per consultare i documenti disponibili.')
            ->action('Vai ai documenti', route('documents.index'));
    }
}
