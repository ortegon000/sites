<?php

namespace App\Notifications;

use App\Models\Domain;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DomainExpiringNotification extends Notification
{
    public function __construct(public Domain $domain) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $client = $this->domain->client;
        $expiresOn = $this->domain->expires_at->format('d/m/Y');

        return (new MailMessage)
            ->subject("Dominio próximo a expirar: {$this->domain->name}")
            ->greeting('Recordatorio de renovación')
            ->line("El dominio \"{$this->domain->name}\" de {$client->name} expira el {$expiresOn}.")
            ->line($this->domain->auto_renew
                ? 'Tiene renovación automática activada: conviene confirmar que el pago con el registrador va a pasar.'
                : 'No tiene renovación automática: hay que renovarlo a mano con el registrador.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'domain_expiring',
            'domain_id' => $this->domain->id,
            'domain_name' => $this->domain->name,
            'client_name' => $this->domain->client->name,
            'auto_renew' => $this->domain->auto_renew,
            'expires_at' => $this->domain->expires_at->toDateString(),
        ];
    }
}
