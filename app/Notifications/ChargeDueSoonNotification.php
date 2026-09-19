<?php

namespace App\Notifications;

use App\Concerns\FormatsMoney;
use App\Models\Charge;
use App\Notifications\Messages\DetailedMailMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChargeDueSoonNotification extends Notification
{
    use FormatsMoney;

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

        return (new DetailedMailMessage)
            ->subject("Cobro próximo a vencer: {$this->charge->conceptLabel()}")
            ->greeting('Recordatorio de cobro')
            ->line("Se acerca la fecha de un cobro de {$where}.")
            ->details([
                'Cliente' => $where,
                'Concepto' => $this->charge->conceptLabel(),
                'Vence' => $this->charge->due_date->format('d/m/Y'),
                'Monto' => $this->formatMoney($this->charge->amount, $this->charge->currency),
            ]);
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
