<?php

namespace App\Notifications;

use App\Models\Renewal;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El aviso de renovación que va al cliente, no al equipo.
 *
 * Lleva enlace al portal y nunca credenciales en el cuerpo: un correo se queda
 * para siempre en la bandeja, se reenvía y se filtra. La pantalla donde el
 * cliente ve sus datos —y revela su contraseña con un clic— ya existe.
 */
class RenewalNoticeNotification extends Notification
{
    public function __construct(public Renewal $renewal) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dueDate = $this->renewal->due_date->format('d/m/Y');
        $subject = $this->renewal->subject();

        $message = (new MailMessage)
            ->subject("Renovación próxima: {$subject}")
            ->greeting('Aviso de renovación')
            ->line("Tu {$this->renewal->kindLabel()} \"{$subject}\" se renueva el {$dueDate}.");

        if ($this->renewal->amount !== null) {
            $message->line("Costo de la renovación: {$this->renewal->amount} {$this->renewal->currency}.");
        }

        return $message
            ->action(__('Ver mis renovaciones'), route('portal.renewals.index'))
            ->line('Si prefieres no renovarlo, respóndenos a este correo antes de esa fecha.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'renewal_notice',
            'renewal_id' => $this->renewal->id,
            'subject' => $this->renewal->subject(),
            'due_date' => $this->renewal->due_date->toDateString(),
        ];
    }
}
