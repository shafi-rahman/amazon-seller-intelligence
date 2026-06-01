<?php

namespace App\Modules\Imports\Services;

class ColumnDetector
{
    // Maps normalized header → DB column name for each import type
    private array $maps = [
        'orders' => [
            'amazon-order-id'          => 'amazon_order_id',
            'amazon order id'          => 'amazon_order_id',
            'order id'                 => 'amazon_order_id',
            'merchant-order-id'        => 'merchant_order_id',
            'purchase-date'            => 'purchase_date',
            'purchase date'            => 'purchase_date',
            'order date'               => 'purchase_date',
            'date'                     => 'purchase_date',
            'last-updated-date'        => 'last_updated_date',
            'order-status'             => 'order_status',
            'order status'             => 'order_status',
            'status'                   => 'order_status',
            'fulfillment-channel'      => 'fulfillment_channel',
            'fulfillment channel'      => 'fulfillment_channel',
            'sales-channel'            => 'sales_channel',
            'ship-service-level'       => 'ship_service_level',
            'product-name'             => 'product_name',
            'product name'             => 'product_name',
            'item name'                => 'product_name',
            'sku'                      => 'sku',
            'seller sku'               => 'sku',
            'asin'                     => 'asin',
            'item-status'              => 'item_status',
            'quantity'                 => 'quantity',
            'qty'                      => 'quantity',
            'currency'                 => 'currency',
            'item-price'               => 'item_price',
            'item price'               => 'item_price',
            'selling price'            => 'item_price',
            'item-tax'                 => 'item_tax',
            'item tax'                 => 'item_tax',
            'shipping-price'           => 'shipping_price',
            'shipping-tax'             => 'shipping_tax',
            'gift-wrap-price'          => 'gift_wrap_price',
            'gift-wrap-tax'            => 'gift_wrap_tax',
            'item-promotion-discount'  => 'item_promotion_discount',
            'ship-promotion-discount'  => 'ship_promotion_discount',
            'ship-city'                => 'ship_city',
            'ship-state'               => 'ship_state',
            'ship-postal-code'         => 'ship_postal_code',
            'ship-country'             => 'ship_country',
            'is-business-order'        => 'is_business_order',
        ],

        'settlements' => [
            'settlement-id'            => 'settlement_id',
            'settlement id'            => 'settlement_id',
            'settlement-start-date'    => 'settlement_start_date',
            'settlement-end-date'      => 'settlement_end_date',
            'deposit-date'             => 'deposit_date',
            'total-amount'             => 'deposited_amount',
            'currency'                 => 'currency',
            'transaction-type'         => 'transaction_type',
            'order-id'                 => 'order_id',
            'order id'                 => 'order_id',
            'merchant-order-id'        => 'merchant_order_id',
            'adjustment-id'            => 'adjustment_id',
            'shipment-id'              => 'shipment_id',
            'marketplace-name'         => 'marketplace_name',
            'amount-type'              => 'amount_type',
            'amount-description'       => 'amount_description',
            'amount'                   => 'amount',
            'fulfillment-id'           => 'fulfillment_id',
            'posted-date'              => 'posted_date',
            'posted-date-time'         => 'posted_datetime',
            'sku'                      => 'sku',
            'quantity-purchased'       => 'quantity_purchased',
        ],

        'gst_report' => [
            'transaction type'         => 'transaction_type',
            'invoice date'             => 'invoice_date',
            'invoice number'           => 'invoice_number',
            'order id'                 => 'order_id',
            'transaction id'           => 'transaction_id',
            'asin'                     => 'asin',
            'seller sku'               => 'sku',
            'sku'                      => 'sku',
            'item description'         => 'product_name',
            'qty'                      => 'quantity',
            'ship from state'          => 'ship_from_state',
            'ship to state'            => 'ship_to_state',
            'taxable value (₹)'        => 'taxable_value',
            'taxable value'            => 'taxable_value',
            'igst rate'                => 'igst_rate',
            'igst amount (₹)'          => 'igst_amount',
            'igst amount'              => 'igst_amount',
            'cgst rate'                => 'cgst_rate',
            'cgst amount (₹)'          => 'cgst_amount',
            'cgst amount'              => 'cgst_amount',
            'sgst/utgst rate'          => 'sgst_rate',
            'sgst rate'                => 'sgst_rate',
            'sgst/utgst amount (₹)'    => 'sgst_amount',
            'sgst amount'              => 'sgst_amount',
            'compensatory cess rate'   => 'cess_rate',
            'compensatory cess amount (₹)' => 'cess_amount',
            'invoice amount (₹)'       => 'invoice_amount',
            'invoice amount'           => 'invoice_amount',
            'irn'                      => 'irn',
            'hsn/sac'                  => 'hsn_sac',
            'hsn'                      => 'hsn_sac',
        ],

        'products' => [
            'asin'                     => 'asin',
            'product id'               => 'asin',
            'sku'                      => 'sku',
            'seller sku'               => 'sku',
            'title'                    => 'title',
            'product title'            => 'title',
            'listing title'            => 'title',
            'name'                     => 'title',
            'item name'                => 'title',
            'brand'                    => 'brand',
            'brand name'               => 'brand',
            'category'                 => 'category',
            'product category'         => 'category',
            'bullet_1'                 => 'bullet_1',
            'bullet 1'                 => 'bullet_1',
            'feature 1'                => 'bullet_1',
            'key feature 1'            => 'bullet_1',
            'bullet_2'                 => 'bullet_2',
            'bullet 2'                 => 'bullet_2',
            'feature 2'                => 'bullet_2',
            'key feature 2'            => 'bullet_2',
            'bullet_3'                 => 'bullet_3',
            'bullet 3'                 => 'bullet_3',
            'feature 3'                => 'bullet_3',
            'key feature 3'            => 'bullet_3',
            'bullet_4'                 => 'bullet_4',
            'bullet 4'                 => 'bullet_4',
            'feature 4'                => 'bullet_4',
            'bullet_5'                 => 'bullet_5',
            'bullet 5'                 => 'bullet_5',
            'feature 5'                => 'bullet_5',
            'description'              => 'description',
            'product description'      => 'description',
            'price'                    => 'price',
            'mrp'                      => 'price',
            'selling price'            => 'price',
            'rating'                   => 'rating',
            'avg rating'               => 'rating',
            'average rating'           => 'rating',
            'review count'             => 'review_count',
            'reviews'                  => 'review_count',
            'no of reviews'            => 'review_count',
            'number of reviews'        => 'review_count',
        ],
    ];

    public function suggest(string $importType, array $headers): array
    {
        // competitors_csv uses same map as products
        $mapKey = $importType === 'competitors_csv' ? 'products' : $importType;

        $map = $this->maps[$mapKey] ?? [];

        $result = [];
        foreach ($headers as $header) {
            $normalized = strtolower(trim($header));
            $result[$header] = $map[$normalized] ?? null;
        }

        return $result;
    }

    public function suggestBankColumns(array $headers): array
    {
        $datePatterns    = ['date', 'txn date', 'transaction date', 'posting date', 'value date'];
        $descPatterns    = ['description', 'narration', 'particulars', 'details', 'remarks', 'remarks/narration'];
        $debitPatterns   = ['debit', 'dr', 'withdrawal', 'debit amount', 'dr amount'];
        $creditPatterns  = ['credit', 'cr', 'deposit', 'credit amount', 'cr amount'];
        $amountPatterns  = ['amount', 'transaction amount'];
        $balancePatterns = ['balance', 'closing balance', 'running balance', 'bal'];
        $refPatterns     = ['ref no', 'reference', 'cheque no', 'chq no', 'utr', 'ref no./cheque no.'];

        $result = [];
        foreach ($headers as $header) {
            $n = strtolower(trim($header));

            $result[$header] = match(true) {
                in_array($n, $datePatterns)    => 'transaction_date',
                in_array($n, $descPatterns)    => 'description',
                in_array($n, $debitPatterns)   => 'debit_amount',
                in_array($n, $creditPatterns)  => 'credit_amount',
                in_array($n, $amountPatterns)  => 'amount', // split by sign later
                in_array($n, $balancePatterns) => 'balance',
                in_array($n, $refPatterns)     => 'reference',
                str_contains($n, 'value date') => 'value_date',
                default                        => null,
            };
        }

        return $result;
    }
}
