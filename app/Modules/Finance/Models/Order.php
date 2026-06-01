<?php

namespace App\Modules\Finance\Models;

use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workspace_id', 'import_batch_id', 'amazon_order_id', 'merchant_order_id',
        'purchase_date', 'last_updated_date', 'order_status', 'fulfillment_channel',
        'sales_channel', 'ship_service_level', 'sku', 'asin', 'product_name',
        'item_status', 'quantity', 'currency', 'item_price', 'item_tax',
        'shipping_price', 'shipping_tax', 'gift_wrap_price', 'gift_wrap_tax',
        'item_promotion_discount', 'ship_promotion_discount', 'ship_city',
        'ship_state', 'ship_postal_code', 'ship_country', 'is_business_order', 'raw_row',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date'       => 'datetime',
            'last_updated_date'   => 'datetime',
            'item_price'          => 'decimal:2',
            'item_tax'            => 'decimal:2',
            'shipping_price'      => 'decimal:2',
            'shipping_tax'        => 'decimal:2',
            'gift_wrap_price'     => 'decimal:2',
            'gift_wrap_tax'       => 'decimal:2',
            'item_promotion_discount'  => 'decimal:2',
            'ship_promotion_discount'  => 'decimal:2',
            'is_business_order'   => 'boolean',
            'raw_row'             => 'array',
            'created_at'          => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
