<?php

namespace App\Notifications\Messages;

use Illuminate\Notifications\Messages\MailMessage;

class DetailedMailMessage extends MailMessage
{
    /**
     * Los datos clave del aviso (cliente, fecha, monto...) como un bloque
     * aparte, para que se lean de un vistazo y no enterrados en una frase. Los
     * pinta la plantilla de resources/views/vendor/notifications/email.blade.php.
     *
     * @param  array<string, string>  $rows
     */
    public function details(array $rows): static
    {
        $this->viewData['details'] = $rows;

        return $this;
    }

    /**
     * Una nota que va justo debajo de los datos clave, para lo que el equipo
     * debe hacer con ellos.
     */
    public function footnote(string $text): static
    {
        $this->viewData['footnote'] = $text;

        return $this;
    }
}
