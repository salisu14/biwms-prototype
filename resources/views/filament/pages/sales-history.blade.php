<x-filament::page>
    <style>
        .history-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .history-header {
            margin-bottom: 0.25rem;
        }
        .history-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
            color: #111827;
        }
        .dark .history-header h2 {
            color: #f9fafb;
        }
        .history-header p {
            font-size: 0.8125rem;
            color: #6b7280;
            margin: 0.125rem 0 0 0;
        }
        .dark .history-header p {
            color: #9ca3af;
        }
        .history-grid {
            display: grid;
            grid-template-cols: 1fr;
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .history-grid {
                grid-template-cols: repeat(2, 1fr);
            }
        }
        @media (min-width: 1024px) {
            .history-grid {
                grid-template-cols: repeat(3, 1fr);
            }
        }
        .history-card {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            padding: 1rem;
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.625rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            text-decoration: none;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .dark .history-card {
            background-color: #1f2937;
            border-color: #374151;
        }
        .history-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .history-card.card-primary:hover { border-color: #fbbf24; }
        .history-card.card-success:hover { border-color: #10b981; }
        .history-card.card-warning:hover { border-color: #f59e0b; }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .icon-box {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: background-color 0.2s;
        }
        .card-primary .icon-box { background-color: #fffbeb; color: #d97706; }
        .dark .card-primary .icon-box { background-color: rgba(217, 119, 6, 0.1); color: #fbbf24; }
        .card-success .icon-box { background-color: #ecfdf5; color: #059669; }
        .dark .card-success .icon-box { background-color: rgba(16, 185, 129, 0.1); color: #34d399; }
        .card-warning .icon-box { background-color: #fffbeb; color: #d97706; }
        .dark .card-warning .icon-box { background-color: rgba(245, 158, 11, 0.1); color: #fbbf24; }

        .count-text {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
        }
        .dark .count-text { color: #ffffff; }

        .card-content h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #111827;
            margin: 0;
        }
        .dark .card-content h3 { color: #ffffff; }
        .card-content p {
            font-size: 0.8125rem;
            color: #6b7280;
            margin: 0.125rem 0 0 0;
            line-height: 1.125rem;
        }
        .dark .card-content p { color: #9ca3af; }

        .card-footer {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: auto;
            transition: gap 0.2s;
        }
        .card-primary .card-footer { color: #d97706; }
        .card-success .card-footer { color: #059669; }
        .card-warning .card-footer { color: #d97706; }
        .history-card:hover .card-footer {
            gap: 0.375rem;
        }
    </style>

    <div class="history-container">
        <header class="history-header">
            <h2>Sales History</h2>
            <p>Access historical Sales waybills, invoices, and archived orders.</p>
        </header>

        <div class="history-grid">
            {{-- Posted Shipments Card --}}
            <a href="{{ \App\Filament\Resources\SalesShipmentHeaders\SalesShipmentHeaderResource::getUrl('posted') }}" class="history-card card-primary">
                <div class="card-header">
                    <div class="icon-box">
                        <x-heroicon-o-truck style="width: 1.5rem; height: 1.5rem;"/>
                    </div>
                    <div class="count-text">{{ $postedShipmentCount ?? 0 }}</div>
                </div>
                <div class="card-content">
                    <h3>Posted Shipments</h3>
                    <p>Track finalized waybills and deliveries.</p>
                </div>
                <div class="card-footer">
                    Manage Records <x-heroicon-m-arrow-right style="width: 0.875rem; height: 0.875rem;"/>
                </div>
            </a>

            @if($canAccessPostedInvoices ?? false)
                {{-- Posted Sales Invoices Card --}}
                <a href="{{ \App\Filament\Resources\SalesInvoices\SalesInvoiceResource::getUrl('posted') }}" class="history-card card-success">
                    <div class="card-header">
                        <div class="icon-box">
                            <x-heroicon-o-document-text style="width: 1.5rem; height: 1.5rem;"/>
                        </div>
                        <div class="count-text">{{ $postedInvoiceCount ?? 0 }}</div>
                    </div>
                    <div class="card-content">
                        <h3>Posted Invoices</h3>
                        <p>Verified billing and payment history.</p>
                    </div>
                    <div class="card-footer">
                        View Ledger <x-heroicon-m-arrow-right style="width: 0.875rem; height: 0.875rem;"/>
                    </div>
                </a>
            @else
                {{-- Finance-safe Receivables Card (no invoice access) --}}
                <a href="{{ $financeReceivablesUrl }}" class="history-card card-success">
                    <div class="card-header">
                        <div class="icon-box">
                            <x-heroicon-o-book-open style="width: 1.5rem; height: 1.5rem;"/>
                        </div>
                        <div class="count-text">{{ $postedInvoiceCount ?? 0 }}</div>
                    </div>
                    <div class="card-content">
                        <h3>Customer Subledger</h3>
                        <p>Finance receivables view without direct invoice access.</p>
                    </div>
                    <div class="card-footer">
                        Open Subledger <x-heroicon-m-arrow-right style="width: 0.875rem; height: 0.875rem;"/>
                    </div>
                </a>
            @endif

            {{-- Archived Sales Orders Card --}}
            <a href="{{ \App\Filament\Resources\SalesOrders\SalesOrderResource::getUrl('archived') }}" class="history-card card-warning">
                <div class="card-header">
                    <div class="icon-box">
                        <x-heroicon-o-archive-box style="width: 1.5rem; height: 1.5rem;"/>
                    </div>
                    <div class="count-text">{{ $archivedOrderCount ?? 0 }}</div>
                </div>
                <div class="card-content">
                    <h3>Archived Sales Orders</h3>
                    <p>Closed orders for historical auditing.</p>
                </div>
                <div class="card-footer">
                    Open Archive <x-heroicon-m-arrow-right style="width: 0.875rem; height: 0.875rem;"/>
                </div>
            </a>
        </div>
    </div>
</x-filament::page>
