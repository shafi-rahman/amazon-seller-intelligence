<?php

namespace App\Modules\Finance\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                        => $this->id,
            'amazon_order_id'           => $this->amazon_order_id,
            'purchase_date'             => $this->purchase_date?->toDateTimeString(),
            'order_status'              => $this->order_status,
            'fulfillment_channel'       => $this->fulfillment_channel,
            'sales_channel'             => $this->sales_channel,
            'sku'                       => $this->sku,
            'asin'                      => $this->asin,
            'product_name'              => $this->product_name,
            'quantity'                  => $this->quantity,
            'currency'                  => $this->currency,
            'item_price'                => (float) $this->item_price,
            'item_tax'                  => (float) $this->item_tax,
            'shipping_price'            => (float) $this->shipping_price,
            'shipping_tax'              => (float) $this->shipping_tax,
            'item_promotion_discount'   => (float) $this->item_promotion_discount,
            'ship_promotion_discount'   => (float) $this->ship_promotion_discount,
            'net_amount'                => round(
                (float) $this->item_price
                    + (float) $this->item_tax
                    + (float) $this->shipping_price
                    - (float) $this->item_promotion_discount
                    - (float) $this->ship_promotion_discount,
                2
            ),
            'ship_city'                 => $this->ship_city,
            'ship_state'                => $this->ship_state,
            'ship_country'              => $this->ship_country,
            'is_business_order'         => $this->is_business_order,
            'has_gst_record'            => $this->whenLoaded('gstTransactions', fn() =>
                $this->gstTransactions->isNotEmpty()
            ),
        ];
    }
}
