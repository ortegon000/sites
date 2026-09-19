<?php

namespace App\Notifications;

use App\Concerns\FormatsMoney;
use App\Models\Renewal;
use App\Notifications\Messages\DetailedMailMessage;
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
    use FormatsMoney;

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
        $subject = $this->renewal->subject();
        $kind = mb_strtolower($this->renewal->kindLabel());
        $dueDate = $this->renewal->due_date->format('d/m/Y');

        $details = [
            ucfirst($kind) => $subject,
            'Se renueva el' => $dueDate,
        ];

        if ($this->renewal->amount !== null) {
            $details['Costo de la renovación'] = $this->formatMoney($this->renewal->amount, $this->renewal->currency);
        }

        return (new DetailedMailMessage)
            ->subject("Tu {$kind} {$subject} se renueva pronto")
            ->greeting('¡Hola!')
            ->line("Solo queríamos avisarte con tiempo: tu {$kind} **{$subject}** se renueva el {$dueDate}.")
            ->line('Te dejamos los datos aquí abajo para que los tengas a la mano.')
            ->details($details)
            ->line('**¿Necesitas hacer algo?** Si todo sigue igual, no tienes que hacer nada: nosotros nos encargamos de la renovación. Y si prefieres no renovarlo o quieres cambiar algo, cuéntanos antes de esa fecha y lo vemos juntos.')
            // Pendiente: el portal del cliente aún no está listo. Cuando lo esté, descomentar el botón.
            // ->action(__('Ver mis renovaciones'), route('portal.renewals.index'))
            ->line("**¿Dudas? Escríbenos por donde te quede más cómodo:**\n\n{$this->contactLines()}")
            ->salutation("¡Un saludo!  \n{$this->companyName()}");
    }

    /**
     * Los medios de contacto en una lista con enlaces: tocar uno abre el
     * WhatsApp, el correo o la llamada directamente.
     */
    private function contactLines(): string
    {
        $contact = config('company.contact');

        return implode("\n", [
            "- **WhatsApp:** [{$this->formatPhone($contact['whatsapp'])}](https://wa.me/52{$contact['whatsapp']})",
            "- **Correo:** [{$contact['email']}](mailto:{$contact['email']})",
            "- **Teléfono:** [{$this->formatPhone($contact['phone'])}](tel:+52{$contact['phone']})",
        ]);
    }

    /**
     * "5512345678" como "55 1234 5678", para que se lea fácil.
     */
    private function formatPhone(string $digits): string
    {
        return preg_replace('/^(\d{2})(\d{4})(\d{4})$/', '$1 $2 $3', $digits) ?? $digits;
    }

    private function companyName(): string
    {
        return (string) config('app.name');
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
