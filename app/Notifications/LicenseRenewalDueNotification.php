<?php

namespace App\Notifications;

use App\Models\License;
use App\Notifications\Messages\DetailedMailMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LicenseRenewalDueNotification extends Notification
{
    public function __construct(public License $license) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $client = $this->license->client;

        return (new DetailedMailMessage)
            ->subject("Cobro mensual próximo: {$this->license->name}")
            ->greeting('Recordatorio interno')
            ->line("Se acerca el cobro mensual de una licencia de {$client->name}.")
            ->details([
                'Cliente' => $client->name,
                'Licencia' => $this->license->name,
                'Cobra' => $this->license->renewal_date->format('d/m/Y'),
                'Renovación' => $this->license->auto_renew ? 'Automática' : 'Manual',
            ])
            ->paragraph($this->license->auto_renew
                ? 'Tiene renovación automática activada: conviene confirmar que el cobro va a pasar.'
                : 'No tiene renovación automática: hay que gestionarla a mano con el proveedor.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'license_renewal_due',
            'license_id' => $this->license->id,
            'license_name' => $this->license->name,
            'client_name' => $this->license->client->name,
            'auto_renew' => $this->license->auto_renew,
            'renewal_date' => $this->license->renewal_date->toDateString(),
        ];
    }
}
