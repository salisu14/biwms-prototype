<x-filament::page>
    <style>
        .hub-container {
            display: flex;
            flex-direction: column;
            gap: 2rem;
            font-family: 'Inter', ui-sans-serif, system-ui;
        }
        .hub-header {
            position: relative;
            padding: 2.5rem;
            border-radius: 1.5rem;
            background: linear-gradient(135deg, rgb(var(--primary-600)), rgb(var(--primary-800)));
            color: white;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .hub-header h2 {
            font-size: 2.25rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: 0.5rem;
        }
        .hub-header p {
            font-size: 1.125rem;
            opacity: 0.9;
            max-width: 32rem;
            line-height: 1.6;
        }
        .hub-header::after {
            content: '';
            position: absolute;
            top: -2rem;
            right: -2rem;
            width: 15rem;
            height: 15rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            filter: blur(40px);
        }

        .category-section {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .category-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.125rem;
            font-weight: 700;
            color: #374151;
            padding-left: 0.5rem;
        }
        .dark .category-title { color: #f3f4f6; }

        .hub-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
        }
        @media (min-width: 768px) { .hub-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1280px) { .hub-grid { grid-template-columns: repeat(4, 1fr); } }

        .hub-card {
            display: flex;
            flex-direction: column;
            padding: 1.25rem;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .dark .hub-card {
            background: #111827;
            border-color: #374151;
        }
        .hub-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px -5px rgba(0, 0, 0, 0.1);
            border-color: rgb(var(--primary-500));
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        .card-icon {
            padding: 0.5rem;
            border-radius: 0.625rem;
            transition: transform 0.3s;
        }
        .hub-card:hover .card-icon { transform: scale(1.1); }

        .counter {
            font-size: 0.875rem;
            font-weight: 700;
            padding: 0.25rem 0.625rem;
            border-radius: 2rem;
            background: #f3f4f6;
            color: #4b5563;
        }
        .dark .counter { background: #374151; color: #d1d5db; }

        .card-body h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.375rem;
            color: #111827;
        }
        .dark .card-body h3 { color: white; }
        .card-body p {
            font-size: 0.8125rem;
            line-height: 1.4;
            color: #6b7280;
        }
        .dark .card-body p { color: #9ca3af; }

        .card-footer {
            margin-top: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            opacity: 0;
            transform: translateX(-10px);
            transition: all 0.3s;
        }
        .hub-card:hover .card-footer {
            opacity: 1;
            transform: translateX(0);
        }

        /* Category Color Themes */
        .theme-indigo .card-icon { background: #e0e7ff; color: #4338ca; }
        .theme-amber .card-icon { background: #fef3c7; color: #b45309; }
        .theme-emerald .card-icon { background: #d1fae5; color: #047857; }
        .theme-rose .card-icon { background: #ffe4e6; color: #be123c; }

        .dark .theme-indigo .card-icon { background: rgba(67, 56, 202, 0.15); color: #818cf8; }
        .dark .theme-amber .card-icon { background: rgba(180, 83, 9, 0.15); color: #fbbf24; }
        .dark .theme-emerald .card-icon { background: rgba(4, 120, 87, 0.15); color: #34d399; }
        .dark .theme-rose .card-icon { background: rgba(190, 18, 60, 0.15); color: #fb7185; }
    </style>

    <div class="hub-container">
        <div class="hub-header">
            <h2>Posting Setup Center</h2>
            <p>Streamline your financial architecture. Manage how transactions flow into the General Ledger across all operational modules.</p>
        </div>

        {{-- Section: Financial & Core --}}
        <div class="category-section theme-indigo">
            <div class="category-title">
                <x-heroicon-o-building-library style="width: 0.2rem; height: 0.2rem;" />
                <span>Financial & Core Posting</span>
            </div>
            <div class="hub-grid">
                <a href="/admin/general-posting-setups" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-tag style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['gen_posting_setup'] }} Records</div>
                    </div>
                    <div class="card-body">
                        <h3>General Posting Setup</h3>
                        <p>Matrix for G/L account mapping based on business and product groups.</p>
                    </div>
                    <div class="card-footer text-indigo-600 dark:text-indigo-400">
                        Configure Accounts <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="/admin/general-business-posting-groups" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-briefcase style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['gen_bus_posting_group'] }} Groups</div>
                    </div>
                    <div class="card-body">
                        <h3>General Business Groups</h3>
                        <p>Categorize customers and vendors for financial posting.</p>
                    </div>
                    <div class="card-footer text-indigo-600 dark:text-indigo-400">
                        Manage Groups <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="/admin/general-product-posting-groups" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-archive-box style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['gen_prod_posting_group'] }} Groups</div>
                    </div>
                    <div class="card-body">
                        <h3>General Product Groups</h3>
                        <p>Categorize Items for financial posting.</p>
                    </div>
                    <div class="card-footer text-indigo-600 dark:text-indigo-400">
                        Manage Groups <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>
            </div>
        </div>

        {{-- Section: Taxation & VAT --}}
        <div class="category-section theme-amber">
            <div class="category-title text-amber-700 dark:text-amber-400">
                <x-heroicon-o-receipt-percent style="width: 1rem; height: 1rem;" />
                <span>Taxation & VAT Posting</span>
            </div>
            <div class="hub-grid">
                <a href="/admin/vat-posting-setups" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-eye-slash style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['vat_posting_setup'] }} Active</div>
                    </div>
                    <div class="card-body">
                        <h3>VAT Posting Setup</h3>
                        <p>Define VAT percentages and specific ledger accounts.</p>
                    </div>
                    <div class="card-footer text-amber-600 dark:text-amber-400">
                        Tax Matrix <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="/admin/vat-business-posting-groups" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-device-tablet style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['vat_bus_posting_group'] }} Groups</div>
                    </div>
                    <div class="card-body">
                        <h3>VAT Business Groups</h3>
                        <p>Market-level tax categorization for entities.</p>
                    </div>
                    <div class="card-footer text-amber-600 dark:text-amber-400">
                        Manage Business <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="/admin/vat-product-posting-groups" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-rectangle-group style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['vat_prod_posting_group'] }} Groups</div>
                    </div>
                    <div class="card-body">
                        <h3>VAT Product Groups</h3>
                        <p>Item-level tax categorization for goods and services.</p>
                    </div>
                    <div class="card-footer text-amber-600 dark:text-amber-400">
                        Manage Products <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>
            </div>
        </div>

        {{-- Section: Relationships & HR --}}
        <div class="category-section theme-emerald">
            <div class="category-title text-emerald-700 dark:text-emerald-400">
                <x-heroicon-o-users style="width: 1rem; height: 1rem;" />
                <span>Relationship Posting</span>
            </div>
            <div class="hub-grid">
                <a href="/admin/customer-posting-groups" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-user-group style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['customer_posting_group'] }} Groups</div>
                    </div>
                    <div class="card-body">
                        <h3>Customer Posting Groups</h3>
                        <p>Direct receivables and discount ledger mappings.</p>
                    </div>
                    <div class="card-footer text-emerald-600 dark:text-emerald-400">
                        Open Setup <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="/admin/vendor-posting-groups" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-truck style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['vendor_posting_group'] }} Groups</div>
                    </div>
                    <div class="card-body">
                        <h3>Vendor Posting Groups</h3>
                        <p>Direct payables and vendor discount ledger mappings.</p>
                    </div>
                    <div class="card-footer text-emerald-600 dark:text-emerald-400">
                        Open Setup <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="/admin/employee-posting-groups" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-identification style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['employee_posting_group'] }} Groups</div>
                    </div>
                    <div class="card-body">
                        <h3>Employee Posting Groups</h3>
                        <p>Liability and payment account configurations.</p>
                    </div>
                    <div class="card-footer text-emerald-600 dark:text-emerald-400">
                        Open Setup <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="{{ \App\Filament\Resources\PayrollPostingGroups\PayrollPostingGroupResource::getUrl('index') }}" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-banknotes style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['payroll_posting_group'] }} Groups</div>
                    </div>
                    <div class="card-body">
                        <h3>Payroll Posting Groups</h3>
                        <p>Mappings for earnings, tax, and contribution accounts.</p>
                    </div>
                    <div class="card-footer text-emerald-600 dark:text-emerald-400">
                        Open Setup <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>
            </div>
        </div>

        {{-- Section: Assets & Inventory --}}
        <div class="category-section theme-rose">
            <div class="category-title text-rose-700 dark:text-rose-400">
                <x-heroicon-o-cube style="width: 1rem; height: 1rem;" />
                <span>Asset & Inventory Posting</span>
            </div>
            <div class="hub-grid">
                <a href="/admin/inventory-posting-setups" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-archive-box style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['inventory_posting_setup'] }} Configs</div>
                    </div>
                    <div class="card-body">
                        <h3>Inventory Posting Setup</h3>
                        <p>Location-specific item valuation and cost flow.</p>
                    </div>
                    <div class="card-footer text-rose-600 dark:text-rose-400">
                        Adjust Setup <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="/admin/inventory-posting-groups" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-square-3-stack-3d style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['inventory_posting_group'] }} Groups</div>
                    </div>
                    <div class="card-body">
                        <h3>Inventory Posting Groups</h3>
                        <p>Categorize items for inventory valuation and G/L posting.</p>
                    </div>
                    <div class="card-footer text-rose-600 dark:text-rose-400">
                        Manage Groups <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="/admin/f-a-posting-groups" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-adjustments-vertical style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['fa_posting_group'] }} Groups</div>
                    </div>
                    <div class="card-body">
                        <h3>Fixed Asset Groups</h3>
                        <p>Maps asset categories to depreciation accounts.</p>
                    </div>
                    <div class="card-footer text-rose-600 dark:text-rose-400">
                        Adjust Setup <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>
            </div>
        </div>
    </div>
</x-filament::page>
