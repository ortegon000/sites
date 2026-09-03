<?php

namespace App\Notifications;

use App\Models\Charge;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChargeDueSoonNotification extends Notification
{
    public function __construct(public Charge $charge) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $service = $this->charge->service;
        $client = $service->client;
        /** El proyecto es opcional: una línea suelta se cobra igual sin él. */
        $where = $service->project ? "{$client->name} ({$service->project->name})" : $client->name;

        return (new MailMessage)
            ->subject("Cobro próximo a vencer: {$this->charge->conceptLabel()}")
            ->greeting('Recordatorio de cobro')
            ->line("El cobro de \"{$this->charge->conceptLabel()}\" para {$where} vence el {$this->charge->due_date->format('d/m/Y')}.")
            ->line("Monto: {$this->charge->amount} {$this->charge->currency}");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $service = $this->charge->service;

        return [
            'type' => 'charge_due_soon',
            'charge_id' => $this->charge->id,
            'service_name' => $this->charge->conceptLabel(),
            'client_name' => $service->client->name,
            'project_id' => $service->project?->id,
            'project_name' => $service->project?->name,
            'amount' => $this->charge->amount,
            'currency' => $this->charge->currency,
            'due_date' => $this->charge->due_date->toDateString(),
        ];
    }
}
