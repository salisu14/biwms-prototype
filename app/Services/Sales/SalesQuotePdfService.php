<?php

namespace App\Services\Sales;

use App\Models\SalesQuote;
use App\Models\SalesQuoteRevision;
use Barryvdh\DomPDF\Facade\Pdf;

class SalesQuotePdfService
{
    /**
     * Recursively convert all strings in array/object to UTF-8
     */
    protected function utf8ize($mixed)
    {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = $this->utf8ize($value);
            }
        } elseif (is_object($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed->$key = $this->utf8ize($value);
            }
        } elseif (is_string($mixed)) {
            // Force UTF-8 encoding
            return mb_convert_encoding($mixed, 'UTF-8', 'UTF-8');
        }
        return $mixed;
    }

    /**
     * Generate a PDF instance
     */
//    public function generate(SalesQuote $quote, ?SalesQuoteRevision $revision = null)
//    {
//        // Eager load relations
//        $quote->load(['customer', 'items.item']);
//
//        $items = $quote->items;
//
//        if ($revision) {
//            $revisionChanges = is_array($revision->changes)
//                ? $revision->changes
//                : json_decode($revision->changes ?? '[]', true);
//
//            foreach ($revisionChanges ?? [] as $change) {
//                $line = $items->firstWhere('item_id', $change['item_id'] ?? null);
//                if ($line) {
//                    $line->quantity = $change['new_qty'] ?? $line->quantity;
//                    $line->line_total = ($line->quantity * $line->unit_price) - $line->discount;
//                }
//            }
//        }
//
//        // Sanitize strings recursively, but keep objects
//        $this->utf8ize($quote);
//        $this->utf8ize($items);
//        $revision && $this->utf8ize($revision);
//
//        $totalAmount = $items->sum('line_total');
//
//        return Pdf::loadView('pdf.sales-quote', [
//            'quote' => $quote,
//            'items' => $items,
//            'totalAmount' => $totalAmount,
//            'revision' => $revision,
//        ]);
//    }

    public function generate(SalesQuote $quote, ?SalesQuoteRevision $revision = null)
    {
        $quote->load(['customer', 'items.item']);

        // 🔥 Decide data source ONCE
        if ($revision && isset($revision->changes['items'])) {

            $items = collect($revision->changes['items'])->map(function ($item) {
                return (object) $item;
            });

        } else {
            $items = $quote->items;
        }

        // 🔥 Ensure line_total exists
        $items = $items->map(function ($item) {
            if (!isset($item->line_total)) {
                $item->line_total = ($item->quantity * $item->unit_price) - ($item->discount ?? 0);
            }
            return $item;
        });

        $totalAmount = $items->sum('line_total');

        return Pdf::loadView('pdf.sales-quote', [
            'quote' => $quote,
            'items' => $items,
            'totalAmount' => $totalAmount,
            'revision' => $revision,
        ]);
    }

    /**
     * Download the PDF
     */
    public function download(SalesQuote $quote, ?SalesQuoteRevision $revision = null)
    {
        $fileName = $revision
            ? "Quote-{$quote->quote_no}-v{$revision->version}.pdf"
            : "Quote-{$quote->quote_no}.pdf";

        // Ensure file name is UTF-8 safe
        $fileName = mb_convert_encoding($fileName, 'UTF-8', 'UTF-8');

        return $this->generate($quote, $revision)->download($fileName);
    }
}
