<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipBankActivated extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 5;

    public function __construct(public string $reference)
    {
        // The existing database queue commits together with the activation.
        $this->onConnection('database')->onQueue('membership-notifications')->beforeCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function backoff(): array
    {
        return [60, 300, 1800, 3600];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Tu membresía JAKAWI está activa')
            ->greeting('Hola, '.$notifiable->name)->line('Conciliamos tu pago y activamos tu membresía JAKAWI.')
            ->line('Referencia de compra: '.$this->reference)->action('Ir a Mi JAKAWI', url('/mi-jakawi'));
    }
}
