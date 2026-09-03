<?php

namespace App\Actions\Contracts;

use App\Enums\ContractStatus;
use App\Models\Contract;

class SignContract
{
    /**
     * Firmar congela el documento: a partir de aquí el texto ya no se edita,
     * porque el contrato que se firmó es el que vale.
     */
    public function handle(Contract $contract, string $signedBy): Contract
    {
        $contract->update([
            'status' => ContractStatus::Firmado,
            'signed_by' => $signedBy,
            'signed_at' => now(),
        ]);

        return $contract;
    }
}
