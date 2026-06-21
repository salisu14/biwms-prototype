<?php

namespace App\Services\Sales;

use App\Enums\QuoteStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\SalesOrderType;
use App\Models\SalesOrder;
use App\Models\SalesQuote;
use App\Models\SalesQuoteItem;
use App\Services\NumberSeriesService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesQuoteService
{
    public function canConvertToOrder(SalesQuote $quote): bool
    {
        if ($quote->status !== QuoteStatus::ACCEPTED) {
            return false;
        }

        if ($quote->approval_status !== 'approved') {
            return false;
        }

        if ($quote->valid_until && $quote->valid_until->isPast()) {
            return false;
        }

        return $quote->items()->exists();
    }

    public function markAsSent(SalesQuote $quote): SalesQuote
    {
        $quote->update([
            'status' => QuoteStatus::SENT,
        ]);

        return $quote->fresh();
    }

    public function approve(SalesQuote $quote, int $approverId): SalesQuote
    {
        $quote->update([
            'approval_status' => 'approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        return $quote->fresh();
    }

    public function reject(SalesQuote $quote): SalesQuote
    {
        $quote->update([
            'approval_status' => 'rejected',
        ]);

        return $quote->fresh();
    }

    public function convertToOrder(SalesQuote $quote): SalesOrder
    {
        if (! $this->canConvertToOrder($quote)) {
            throw ValidationException::withMessages([
                'status' => 'Quote must be accepted, approved, unexpired, and have at least one line before conversion.',
            ]);
        }

        return DB::transaction(function () use ($quote): SalesOrder {
            /** @var SalesOrder|null $existingOrder */
            $existingOrder = SalesOrder::query()
                ->where('customer_id', $quote->customer_id)
                ->where('external_document_number', $quote->quote_no)
                ->first();

            if ($existingOrder) {
                return $existingOrder;
            }

            $customer = $quote->customer()->firstOrFail();

            $order = SalesOrder::query()->create([
                'order_number' => NumberSeriesService::getNextNo('S-ORD'),
                'external_document_number' => $quote->quote_no,
                'order_type' => SalesOrderType::SalesOrder,
                'customer_id' => $quote->customer_id,
                'customer_name' => $customer->name,
                'customer_address' => $customer->address,
                'ship_to_name' => $customer->name,
                'ship_to_address' => $customer->address,
                'order_date' => now()->toDateString(),
                'requested_delivery_date' => $quote->valid_until?->toDateString() ?? now()->addDays(7)->toDateString(),
                'payment_terms_code' => $customer->payment_terms_code,
                'status' => SalesOrderStatus::DRAFT,
                'currency_code' => $customer->currency_code ?? 'USD',
                'is_price_inclusive' => (bool) ($quote->is_price_inclusive ?? false),
            ]);

            /** @var Collection<int, SalesQuoteItem> $quoteItems */
            $quoteItems = $quote->items()->with('item')->get();

            foreach ($quoteItems as $quoteItem) {
                $item = $quoteItem->item;
                $uomCode = $item?->base_unit_of_measure ?? 'PCS';
                $qtyPerUom = $item?->getConversionFactorForUom($uomCode) ?? 1.0;
                $lineTotal = (float) $quoteItem->line_total;
                $lineDiscountAmount = (float) $quoteItem->discount;
                $lineDiscountPercent = $lineTotal > 0
                    ? round(($lineDiscountAmount / $lineTotal) * 100, 2)
                    : 0.0;

                $order->lines()->create([
                    'item_id' => $quoteItem->item_id,
                    'item_code' => $item?->item_code,
                    'description' => $item?->description ?? 'Quote Item',
                    'description_2' => $item?->description_2,
                    'quantity' => $quoteItem->quantity,
                    'unit_price' => $quoteItem->unit_price,
                    'unit_of_measure_code' => $uomCode,
                    'qty_per_unit_of_measure' => $qtyPerUom > 0 ? $qtyPerUom : 1.0,
                    'quantity_base' => (float) $quoteItem->quantity * ($qtyPerUom > 0 ? $qtyPerUom : 1.0),
                    'line_discount_percent' => $lineDiscountPercent,
                    'line_discount_amount' => $lineDiscountAmount,
                    'line_total' => $lineTotal,
                    'line_amount' => $lineTotal - $lineDiscountAmount,
                    'location_id' => $customer->location_id,
                    'requested_delivery_date' => $order->requested_delivery_date,
                    'promised_delivery_date' => $order->requested_delivery_date,
                ]);
            }

            $order->load('lines');
            $order->recalculateTotals();
            $order->save();

            return $order;
        });
    }
}
