<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class SecureAccountAccess extends ResetPassword implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Asegura tu acceso a JAKAWI')
            ->greeting('Hola, '.explode(' ', trim($notifiable->name))[0])
            ->line('Tu cuenta está lista. Elige tu contraseña para volver a JAKAWI cuando quieras.')
            ->action('ASEGURA TU ACCESO A JAKAWI', $this->resetUrl($notifiable))
            ->line('Tu sesión actual continúa. Este enlace seguro vence en '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutos.')
            ->line('Si el enlace vence, puedes solicitar otro desde Recupera tu acceso.');
    }
}
