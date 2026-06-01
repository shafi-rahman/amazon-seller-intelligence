<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'settlement_id'         => $this->settlement_id,
            'settlement_start_date' => $this->settlement_start_date?->toDateString(),
            'settlement_end_date'   => $this->settlement_end_date?->toDateString(),
            'deposit_date'          => $this->deposit_date?->toDateString(),
            'deposited_amount'      => (float) $this->deposited_amount,
            'currency'              => $this->currency,
            'transaction_type'      => $this->transaction_type,
            'order_id'              => $this->order_id,
            'amount_type'           => $this->amount_type,
            'amount_description'    => $this->amount_description,
            'amount'                => (float) $this->amount,
            'marketplace_name'      => $this->marketplace_name,
            'sku'                   => $this->sku,
            'posted_date'           => $this->posted_date?->toDateString(),
        ];
    }
}
