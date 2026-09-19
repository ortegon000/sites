<?php

namespace App\Notifications\Messages;

use Illuminate\Notifications\Messages\MailMessage;

class DetailedMailMessage extends MailMessage
{
    /**
     * Un bloque de datos clave (cliente, fecha, monto, cuenta...) para que se
     * lean de un vistazo y no enterrados en una frase. Las secciones salen en
     * el orden en que se agregan, después de las líneas de introducción, y las
     * pinta resources/views/vendor/notifications/email.blade.php.
     *
     * @param  array<string, string>  $rows
     */
    public function details(array $rows): static
    {
        $this->viewData['sections'][] = ['type' => 'details', 'rows' => $rows];

        return $this;
    }

    /**
     * Un párrafo (admite Markdown) que va en su lugar entre los bloques de
     * datos, para explicar lo que sigue o lo que hay que hacer con ellos.
     */
    public function paragraph(string $body): static
    {
        $this->viewData['sections'][] = ['type' => 'text', 'body' => $body];

        return $this;
    }
}
