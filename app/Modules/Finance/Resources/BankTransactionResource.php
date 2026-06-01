<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BankTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'value_date'       => $this->value_date?->toDateString(),
            'description'      => $this->description,
            'debit_amount'     => (float) $this->debit_amount,
            'credit_amount'    => (float) $this->credit_amount,
            'balance'          => $this->balance !== null ? (float) $this->balance : null,
            'reference'        => $this->reference,
            'bank_name'        => $this->bank_name,
            'is_amazon_credit' => $this->credit_amount > 0
                && str_contains(strtolower($this->description ?? ''), 'amazon'),
        ];
    }
}
