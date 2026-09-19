<?php

namespace App\Notifications;

use App\Concerns\FormatsMoney;
use App\Models\Renewal;
use App\Notifications\Messages\DetailedMailMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

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

        $message = (new DetailedMailMessage)
            ->subject("Tu {$kind} {$subject} se renueva pronto")
            ->greeting($this->greeting())
            ->line("Queremos informarte que: tu {$kind} **{$subject}** se renueva el {$dueDate}.")
            ->line('Te dejamos los datos aquí abajo para que los tengas a la mano.')
            ->details($details);

        if ($this->renewal->amount !== null) {
            $amount = $this->formatMoney($this->renewal->amount, $this->renewal->currency);

            $message
                ->paragraph("**Si quieres renovarlo**, es muy sencillo: deposita **{$amount}** a esta cuenta antes de esa fecha.")
                ->details($this->bankDetails($subject))
                ->paragraph('En cuanto lo hagas, mándanos tu comprobante por WhatsApp o por correo (abajo te dejamos los datos) y nosotros nos encargamos del resto.');
        }

        return $message
            ->paragraph('**Y si prefieres no renovarlo**, no pasa nada: solo avísanos antes de esa fecha para dejarlo listo de nuestro lado.')
            // Pendiente: el portal del cliente aún no está listo. Cuando lo esté, descomentar el botón.
            // ->action(__('Ver mis renovaciones'), route('portal.renewals.index'))
            ->paragraph("**¿Dudas? Escríbenos por donde te quede más cómodo:**\n\n{$this->contactLines()}")
            ->salutation("¡Un saludo!  \n{$this->companyName()}");
    }

    /**
     * La cuenta donde depositar. Como concepto va lo que se renueva, para que
     * el depósito se identifique sin tener que preguntar de quién es.
     *
     * @return array<string, string>
     */
    private function bankDetails(string $subject): array
    {
        $bank = config('company.bank');

        return [
            'Banco' => $bank['bank'],
            'Titular' => $bank['holder'],
            'CLABE' => $this->formatClabe($bank['clabe']),
            'Cuenta' => $bank['account'],
            'Concepto' => $subject,
        ];
    }

    /**
     * "012180001234567891" como "012 180 00123456789 1": banco, plaza, cuenta
     * y dígito verificador, para poder leerla y copiarla sin equivocarse.
     */
    private function formatClabe(string $clabe): string
    {
        return preg_replace('/^(\d{3})(\d{3})(\d{11})(\d)$/', '$1 $2 $3 $4', $clabe) ?? $clabe;
    }

    /**
     * Saluda por su nombre de pila al contacto principal de la empresa. El
     * correo también llega a los demás contactos con correo, pero es a esa
     * persona a quien va dirigido; sin contactos, el saludo queda genérico.
     */
    private function greeting(): string
    {
        $name = $this->renewal->client->primaryContact()?->name;

        return $name ? '¡Hola, '.Str::of($name)->trim()->before(' ').'!' : '¡Hola!';
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
