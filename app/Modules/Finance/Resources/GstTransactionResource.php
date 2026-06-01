<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GstTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'transaction_type' => $this->transaction_type,
            'invoice_date'     => $this->invoice_date?->toDateString(),
            'invoice_number'   => $this->invoice_number,
            'order_id'         => $this->order_id,
            'asin'             => $this->asin,
            'sku'              => $this->sku,
            'product_name'     => $this->product_name,
            'quantity'         => $this->quantity,
            'ship_from_state'  => $this->ship_from_state,
            'ship_to_state'    => $this->ship_to_state,
            'taxable_value'    => $this->taxable_value !== null ? (float) $this->taxable_value : null,
            'igst_rate'        => $this->igst_rate !== null ? (float) $this->igst_rate : null,
            'igst_amount'      => $this->igst_amount !== null ? (float) $this->igst_amount : null,
            'cgst_rate'        => $this->cgst_rate !== null ? (float) $this->cgst_rate : null,
            'cgst_amount'      => $this->cgst_amount !== null ? (float) $this->cgst_amount : null,
            'sgst_rate'        => $this->sgst_rate !== null ? (float) $this->sgst_rate : null,
            'sgst_amount'      => $this->sgst_amount !== null ? (float) $this->sgst_amount : null,
            'invoice_amount'   => $this->invoice_amount !== null ? (float) $this->invoice_amount : null,
            'total_tax'        => round(
                (float) $this->igst_amount
                    + (float) $this->cgst_amount
                    + (float) $this->sgst_amount,
                2
            ),
            'irn'              => $this->irn,
            'hsn_sac'          => $this->hsn_sac,
        ];
    }
}
