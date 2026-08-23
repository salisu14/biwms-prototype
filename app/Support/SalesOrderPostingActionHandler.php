<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SalesOrderStatus;
use App\Exceptions\BusinessException;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\PostedSalesInvoice;
use App\Models\SalesOrder;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderPostingActionHandler
{
    public function postShipment(SalesOrder $order): void
    {
        try {
            $order->postShipment();

            Notification::make()
                ->title('Shipment Posted')
                ->success()
                ->send();
        } catch (ValidationException|BusinessException $exception) {
            $this->notifyExpectedFailure($exception, 'Unable to post shipment');
        }
    }

    public function postInvoice(SalesOrder $order): void
    {
        try {
            $order->postInvoice();

            Notification::make()
                ->title('Invoice Posted')
                ->success()
                ->send();
        } catch (ValidationException|BusinessException $exception) {
            $this->notifyExpectedFailure($exception, 'Unable to post invoice');
        }
    }

    public function postAndInvoice(SalesOrder $order): ?RedirectResponse
    {
        try {
            $postedInvoice = DB::transaction(function () use ($order): PostedSalesInvoice {
                if (in_array($order->status, [SalesOrderStatus::APPROVED, SalesOrderStatus::RELEASED], true)) {
                    $order->postShipment();
                    $order->refresh();
                }

                return $order->postInvoice();
            });

            Notification::make()
                ->title('Shipment and Invoice Posted')
                ->success()
                ->send();

            return redirect(SalesInvoiceResource::getUrl('posted', [
                'tableSearch' => $postedInvoice->document_number,
            ]));
        } catch (ValidationException|BusinessException $exception) {
            $this->notifyExpectedFailure($exception, 'Unable to post and invoice');

            return null;
        }
    }

    private function notifyExpectedFailure(ValidationException|BusinessException $exception, string $fallbackTitle): void
    {
        $message = $exception instanceof ValidationException
            ? (collect($exception->errors())->flatten()->first() ?: $fallbackTitle)
            : $exception->getMessage();

        $title = $exception instanceof BusinessException
            ? $exception->title()
            : $fallbackTitle;

        Notification::make()
            ->title($title)
            ->body($message)
            ->danger()
            ->persistent()
            ->send();
    }
}
