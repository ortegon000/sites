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
        $project = $service->project;
        $client = $project->client;

        return (new MailMessage)
            ->subject("Cobro próximo a vencer: {$service->name}")
            ->greeting('Recordatorio de cobro')
            ->line("El cobro de \"{$service->name}\" para {$client->name} ({$project->name}) vence el {$this->charge->due_date->format('d/m/Y')}.")
            ->line("Monto: {$this->charge->amount} {$this->charge->currency}");
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $service = $this->charge->service;
        $project = $service->project;

        return [
            'type' => 'charge_due_soon',
            'charge_id' => $this->charge->id,
            'service_name' => $service->name,
            'project_id' => $project->id,
            'project_name' => $project->name,
            'amount' => $this->charge->amount,
            'currency' => $this->charge->currency,
            'due_date' => $this->charge->due_date->toDateString(),
        ];
    }
}
