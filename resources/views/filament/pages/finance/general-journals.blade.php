<x-filament::page>
    @php
        $panelPath = trim(filament()->getCurrentPanel()->getPath(), '/');
    @endphp
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
            box-shadow: 0 20px 25px -5px rgba(0,0,0,.1), 0 10px 10px -5px rgba(0,0,0,.04);
        }
        .hub-header > * {
            position: relative;
            z-index: 1;
        }
        .hub-header h2 {
            font-size: 2.25rem;
            font-weight: 800;
            letter-spacing: -0.025em;
            margin-bottom: 0.5rem;
            line-height: 1.1;
            text-shadow: 0 1px 1px rgba(15, 23, 42, 0.12);
            text-rendering: geometricPrecision;
            -webkit-font-smoothing: antialiased;
        }
        .hub-header p {
            font-size: 1.125rem;
            opacity: 1;
            max-width: 36rem;
            line-height: 1.6;
            color: rgba(255, 255, 255, 0.96);
            text-shadow: 0 1px 1px rgba(15, 23, 42, 0.1);
            text-rendering: geometricPrecision;
            -webkit-font-smoothing: antialiased;
        }
        .hub-header::after {
            content: '';
            position: absolute;
            top: -2rem;
            right: -2rem;
            width: 15rem;
            height: 15rem;
            background: rgba(255,255,255,.08);
            border-radius: 50%;
            filter: blur(56px);
            z-index: 0;
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
        @media (min-width: 768px)  { .hub-grid { grid-template-columns: repeat(2, 1fr); } }
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
            box-shadow: 0 1px 2px 0 rgba(0,0,0,.05);
        }
        .dark .hub-card {
            background: #111827;
            border-color: #374151;
        }
        .hub-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px -5px rgba(0,0,0,.1);
            border-color: rgb(var(--primary-500));
        }
        .hub-card-featured {
            background: #ffffff;
            border-color: #2563eb;
            border-width: 1.5px;
            box-shadow:
                inset 0 0 0 1px rgba(147, 197, 253, 0.35),
                0 10px 22px -18px rgba(37, 99, 235, 0.28);
            position: relative;
        }
        .hub-card-featured::before {
            content: '';
            position: absolute;
            inset: 0 0 auto 0;
            height: 0.3rem;
            background: linear-gradient(90deg, #2563eb 0%, #1d4ed8 55%, #1e40af 100%);
            border-radius: 1rem 1rem 0 0;
        }
        .hub-card-featured .counter {
            background: #dbeafe;
            color: #1d4ed8;
            border: 1px solid #93c5fd;
        }
        .hub-card-featured .card-body h3 {
            color: #0f172a;
            font-weight: 800;
            letter-spacing: -0.01em;
            text-shadow: none;
        }
        .hub-card-featured .card-body p {
            color: #334155;
            font-weight: 500;
            text-shadow: none;
            opacity: 1;
        }
        .dark .hub-card-featured {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.92) 0%, rgba(17, 24, 39, 0.95) 100%);
            border-color: rgba(129, 140, 248, 0.45);
            box-shadow: 0 18px 36px -20px rgba(129, 140, 248, 0.45);
        }
        .dark .hub-card-featured .counter {
            background: rgba(129, 140, 248, 0.16);
            color: #c7d2fe;
        }
        .dark .hub-card-featured .card-body h3 {
            color: #eef2ff;
        }
        .dark .hub-card-featured .card-body p {
            color: #cbd5e1;
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
            white-space: nowrap;
        }
        .dark .counter { background: #374151; color: #d1d5db; }

        .card-body h3 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.375rem;
            color: #111827;
            text-rendering: geometricPrecision;
            -webkit-font-smoothing: antialiased;
        }
        .dark .card-body h3 { color: white; }
        .card-body p {
            font-size: 0.8125rem;
            line-height: 1.4;
            color: #6b7280;
            text-rendering: geometricPrecision;
            -webkit-font-smoothing: antialiased;
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

        /* Colour themes */
        .theme-blue  .card-icon { background: #dbeafe; color: #1d4ed8; }
        .theme-amber .card-icon { background: #fef3c7; color: #b45309; }
        .theme-violet .card-icon { background: #ede9fe; color: #6d28d9; }
        .theme-emerald .card-icon { background: #d1fae5; color: #047857; }

        .dark .theme-blue   .card-icon { background: rgba(29,78,216,.15);  color: #93c5fd; }
        .dark .theme-amber  .card-icon { background: rgba(180,83,9,.15);   color: #fbbf24; }
        .dark .theme-violet .card-icon { background: rgba(109,40,217,.15); color: #c4b5fd; }
        .dark .theme-emerald .card-icon { background: rgba(4,120,87,.15);  color: #34d399; }
    </style>

    <div class="hub-container">

        {{-- Header --}}
        <div class="hub-header">
            <h2>Journals Hub</h2>
            <p>Enter, review, and post financial entries across every journal type — from general adjustments to item movements, fixed assets, and payroll.</p>
        </div>

        {{-- General Journals --}}
        <div class="category-section theme-blue">
            <div class="category-title text-blue-700 dark:text-blue-400">
                <x-heroicon-o-book-open style="width: 1.125rem; height: 1.125rem;" />
                <span>General Journal</span>
            </div>
            <div class="hub-grid">
                <a href="{{ url('/' . $panelPath . '/general-journal-batches') }}" class="hub-card hub-card-featured">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-pencil-square style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['gen_journal_batches'] }} Batches</div>
                    </div>
                    <div class="card-body">
                        <h3>General Journal</h3>
                        <p>Manual Dr/Cr entry for G/L adjustments, reclassifications, and ad-hoc postings.</p>
                    </div>
                    <div class="card-footer text-blue-600 dark:text-blue-400">
                        Open Journal <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="{{ url('/' . $panelPath . '/general-journal-templates') }}" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-clipboard-document-list style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['gen_journal_templates'] }} Templates</div>
                    </div>
                    <div class="card-body">
                        <h3>General Journal Templates</h3>
                        <p>Configure journal types, number series, balancing rules, and posting controls.</p>
                    </div>
                    <div class="card-footer text-blue-600 dark:text-blue-400">
                        Manage Templates <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="{{ url('/' . $panelPath . '/journal-lines') }}" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-rectangle-stack style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">Entries</div>
                    </div>
                    <div class="card-body">
                        <h3>Journal Lines</h3>
                        <p>View and manage posted and draft journal lines in one place for detailed review.</p>
                    </div>
                    <div class="card-footer text-blue-600 dark:text-blue-400">
                        Open Journal Lines <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>
            </div>
        </div>

        {{-- Item Journals --}}
        <div class="category-section theme-amber">
            <div class="category-title text-amber-700 dark:text-amber-400">
                <x-heroicon-o-archive-box style="width: 1.125rem; height: 1.125rem;" />
                <span>Item Journal</span>
            </div>
            <div class="hub-grid">
                <a href="{{ url('/' . $panelPath . '/item-journal-batches') }}" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-inbox-arrow-down style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['item_journal_batches'] }} Batches</div>
                    </div>
                    <div class="card-body">
                        <h3>Item Journals</h3>
                        <p>Post inventory adjustments, positive/negative corrections, and item reclassifications.</p>
                    </div>
                    <div class="card-footer text-amber-600 dark:text-amber-400">
                        Open Journal <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="{{ url('/' . $panelPath . '/item-journal-templates') }}" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-clipboard-document-list style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['item_journal_templates'] }} Templates</div>
                    </div>
                    <div class="card-body">
                        <h3>Item Journal Templates</h3>
                        <p>Define item journal types, entry restrictions, and default locations.</p>
                    </div>
                    <div class="card-footer text-amber-600 dark:text-amber-400">
                        Manage Templates <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>
            </div>
        </div>

        {{-- Fixed Asset Journals --}}
        <div class="category-section theme-violet">
            <div class="category-title text-violet-700 dark:text-violet-400">
                <x-heroicon-o-building-office-2 style="width: 1.125rem; height: 1.125rem;" />
                <span>Fixed Asset Journal</span>
            </div>
            <div class="hub-grid">
                <a href="{{ url('/' . $panelPath . '/fa-journal-batches') }}" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-adjustments-horizontal style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['fa_journal_batches'] }} Batches</div>
                    </div>
                    <div class="card-body">
                        <h3>FA Journals</h3>
                        <p>Post acquisition, depreciation, disposal, and write-down entries for fixed assets.</p>
                    </div>
                    <div class="card-footer text-violet-600 dark:text-violet-400">
                        Open Journal <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="{{ url('/' . $panelPath . '/fa-journal-templates') }}" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-clipboard-document-list style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['fa_journal_templates'] }} Templates</div>
                    </div>
                    <div class="card-body">
                        <h3>FA Journal Templates</h3>
                        <p>Configure FA journal types, depreciation book links, and posting rules.</p>
                    </div>
                    <div class="card-footer text-violet-600 dark:text-violet-400">
                        Manage Templates <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>
            </div>
        </div>

        {{-- Other Journals --}}
        <div class="category-section theme-emerald">
            <div class="category-title text-emerald-700 dark:text-emerald-400">
                <x-heroicon-o-squares-2x2 style="width: 1.125rem; height: 1.125rem;" />
                <span>Other Journals</span>
            </div>
            <div class="hub-grid">
                <a href="{{ url('/' . $panelPath . '/recurring-journal-batches') }}" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-arrow-path style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['recurring_journal_batches'] }} Batches</div>
                    </div>
                    <div class="card-body">
                        <h3>Recurring Journals</h3>
                        <p>Automate periodic entries for rent, subscriptions, and standing allocations.</p>
                    </div>
                    <div class="card-footer text-emerald-600 dark:text-emerald-400">
                        Open Journal <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="{{ url('/' . $panelPath . '/recurring-journal-templates') }}" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-clipboard-document-list style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['recurring_journal_templates'] }} Templates</div>
                    </div>
                    <div class="card-body">
                        <h3>Recurring Journal Templates</h3>
                        <p>Configure recurring methods, frequency formulas, and auto-reversal rules.</p>
                    </div>
                    <div class="card-footer text-emerald-600 dark:text-emerald-400">
                        Manage Templates <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="{{ url('/' . $panelPath . '/production-journal-batches') }}" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-cog-6-tooth style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['prod_journal_batches'] }} Batches</div>
                    </div>
                    <div class="card-body">
                        <h3>Production Journal</h3>
                        <p>Post consumption and output entries for production orders directly.</p>
                    </div>
                    <div class="card-footer text-emerald-600 dark:text-emerald-400">
                        Open Journal <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="{{ url('/' . $panelPath . '/production-journal-templates') }}" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-clipboard-document-list style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['prod_journal_templates'] }} Templates</div>
                    </div>
                    <div class="card-body">
                        <h3>Production Journal Templates</h3>
                        <p>Configure production journal templates, posting defaults, and controlled entry setup.</p>
                    </div>
                    <div class="card-footer text-emerald-600 dark:text-emerald-400">
                        Manage Templates <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="{{ url('/' . $panelPath . '/warehouse-journal-batches') }}" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-inbox style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['warehouse_journal_batches'] }} Batches</div>
                    </div>
                    <div class="card-body">
                        <h3>Warehouse Journals</h3>
                        <p>Bin-level physical inventory, movements, picks, put-aways, and adjustments.</p>
                    </div>
                    <div class="card-footer text-emerald-600 dark:text-emerald-400">
                        Open Journal <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="{{ url('/' . $panelPath . '/warehouse-journal-templates') }}" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon"><x-heroicon-o-clipboard-document-list style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">{{ $counts['warehouse_journal_templates'] }} Templates</div>
                    </div>
                    <div class="card-body">
                        <h3>Warehouse Journal Templates</h3>
                        <p>Configure journal types, bin/zone controls, and physical inventory settings.</p>
                    </div>
                    <div class="card-footer text-emerald-600 dark:text-emerald-400">
                        Manage Templates <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>
            </div>
        </div>

        {{-- Receivables & Payables Journals --}}
        <div class="category-section" style="--cat-color: #0e7490;">
            <div class="category-title" style="color: #0e7490;">
                <x-heroicon-o-banknotes style="width: 1.125rem; height: 1.125rem;" />
                <span>Receivables & Payables</span>
            </div>
            <div class="hub-grid">
                <a href="{{ url('/' . $panelPath . '/cash-receipt-lines') }}" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon" style="background:#cffafe; color:#0e7490;"><x-heroicon-o-banknotes style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">Cash Receipt Journal</div>
                    </div>
                    <div class="card-body">
                        <h3>Cash Receipt Journal</h3>
                        <p>Record customer payments received, apply to open invoices, and post to G/L.</p>
                    </div>
                    <div class="card-footer" style="color:#0e7490;">
                        Open Journal <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>

                <a href="{{ url('/' . $panelPath . '/payment-journal-lines') }}" class="hub-card">
                    <div class="card-top">
                        <div class="card-icon" style="background:#cffafe; color:#0e7490;"><x-heroicon-o-credit-card style="width: 1rem; height: 1rem;" /></div>
                        <div class="counter">Payment Journal</div>
                    </div>
                    <div class="card-body">
                        <h3>Payment Journal</h3>
                        <p>Schedule and post vendor disbursements, apply to outstanding invoices.</p>
                    </div>
                    <div class="card-footer" style="color:#0e7490;">
                        Open Journal <x-heroicon-m-arrow-right class="w-3 h-3" />
                    </div>
                </a>
            </div>
        </div>

    </div>
</x-filament::page>
