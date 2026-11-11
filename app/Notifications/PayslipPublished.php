<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayslipPublished extends Notification
{
    public function __construct(
        public readonly ?int $month,
        public readonly int $year,
        public readonly string $documentType = 'payslip',
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $message] = match ($this->documentType) {
            'cud' => [
                'La tua Certificazione Unica è disponibile - Pineto Calcio',
                'La tua Certificazione Unica per i redditi '.$this->year.' è disponibile.',
            ],
            'thirteenth' => [
                'La tua tredicesima è disponibile - Pineto Calcio',
                'La tua busta della tredicesima '.$this->year.' è disponibile.',
            ],
            'payslip' => [
                'La tua busta paga è disponibile - Pineto Calcio',
                'La tua busta paga per il periodo '.str_pad((string) $this->month, 2, '0', STR_PAD_LEFT).'/'.$this->year.' è disponibile.',
            ],
        };

        return (new MailMessage)
            ->subject($subject)
            ->line($message)
            ->line('Accedi al portale per consultare i documenti disponibili.')
            ->action('Vai ai documenti', route('documents.index'));
    }
}
