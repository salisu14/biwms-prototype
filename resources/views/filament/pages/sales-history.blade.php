<x-filament::page>
    <x-filament::section>
        <x-slot name="heading">
            Sales History Navigation
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

            {{-- Posted Shipments --}}
            <x-filament::section class="bg-primary-50 dark:bg-primary-900/20">
                <a href="{{ \App\Filament\Resources\SalesShipmentHeaders\SalesShipmentHeaderResource::getUrl('posted') }}" class="flex items-center gap-3 p-4">
                    <x-heroicon-o-truck class="w-5 h-5 text-primary-600"/>
                    <div>
                        <span class="font-medium text-gray-900 dark:text-white">Posted Shipments</span>
                        <p class="text-sm text-gray-500">Waybills & Delivery Notes</p>
                    </div>
                </a>
            </x-filament::section>

            {{-- Posted Sales Invoices --}}
            <x-filament::section class="bg-success-50 dark:bg-success-900/20">
                <a href="{{ \App\Filament\Resources\SalesInvoices\SalesInvoiceResource::getUrl('posted') }}" class="flex items-center gap-3 p-4">
                    <x-heroicon-o-document-text class="w-5 h-5 text-success-600"/>
                    <div>
                        <span class="font-medium text-gray-900 dark:text-white">Posted Sales Invoices</span>
                        <p class="text-sm text-gray-500">
                            {{ $postedInvoiceCount ?? \App\Models\SalesInvoice::whereNotNull('posted_at')->count() }} records
                        </p>
                    </div>
                </a>
            </x-filament::section>

            {{-- Archived Sales Orders --}}
            <x-filament::section class="bg-warning-50 dark:bg-warning-900/20">
                <a href="{{ \App\Filament\Resources\SalesOrders\SalesOrderResource::getUrl('archived') }}" class="flex items-center gap-3 p-4">
                    <x-heroicon-o-archive-box class="w-5 h-5 text-warning-600"/>
                    <div>
                        <span class="font-medium text-gray-900 dark:text-white">Archived Sales Orders</span>
                        <p class="text-sm text-gray-500">Completed & Closed Orders</p>
                    </div>
                </a>
            </x-filament::section>

        </div>
    </x-filament::section>
</x-filament::page>
