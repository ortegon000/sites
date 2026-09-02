<?php

namespace App\Actions\Clients;

use App\Models\Client;
use App\Models\Contact;

class LinkContactToClient
{
    /**
     * Toma los datos de contacto capturados en el formulario de cliente y los
     * resuelve contra `contacts`: si esa persona ya existe —se busca por correo,
     * y si no hay correo por nombre— se reutiliza en vez de duplicarla, que es
     * justamente lo que pasaba cuando el contacto vivía dentro de `clients`.
     *
     * @param  array{name: ?string, email: ?string, phone: ?string, role?: ?string}  $attributes
     */
    public function handle(Client $client, array $attributes, bool $isPrimary = true): ?Contact
    {
        $name = trim((string) ($attributes['name'] ?? ''));
        $email = trim((string) ($attributes['email'] ?? ''));
        $phone = trim((string) ($attributes['phone'] ?? ''));

        if ($name === '' && $email === '') {
            return null;
        }

        $contact = $email !== ''
            ? Contact::firstWhere('email', $email)
            : Contact::whereNull('email')->firstWhere('name', $name);

        if ($contact === null) {
            $contact = Contact::create([
                'name' => $name !== '' ? $name : $email,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
            ]);
        } else {
            /**
             * Un valor capturado siempre gana, porque editar el contacto desde
             * la ficha del cliente tiene que guardar. Uno vacío no pisa nada:
             * ligar a una persona existente sin repetir su teléfono no debe
             * borrárselo.
             */
            $contact->fill([
                'name' => $name !== '' ? $name : $contact->name,
                'phone' => $phone !== '' ? $phone : $contact->phone,
            ])->save();
        }

        if ($isPrimary) {
            $client->contacts()->newPivotQuery()->update(['is_primary' => false]);
        }

        $client->contacts()->syncWithoutDetaching([$contact->id => [
            'is_primary' => $isPrimary,
            'role' => $attributes['role'] ?? null,
        ]]);

        $client->unsetRelation('contacts');

        return $contact;
    }
}
