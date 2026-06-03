<x-filament-panels::page>
    @php
        $tableWrapper = 'overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900';
        $tableClass = 'min-w-full border-collapse text-sm';
        $headClass = 'bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300';
        $cellLabelClass = 'w-48 border border-gray-200 px-4 py-3 text-sm font-medium text-gray-500 dark:border-gray-700 dark:text-gray-400';
        $cellValueClass = 'border border-gray-200 px-4 py-3 text-sm text-gray-900 dark:border-gray-700 dark:text-gray-100';
        $headCellClass = 'border border-gray-200 px-4 py-3 dark:border-gray-700';
        $bodyCellClass = 'border border-gray-200 px-4 py-3 text-sm text-gray-900 dark:border-gray-700 dark:text-gray-100';
        $missingPrimaryBankEmployeeIds = $this->employeesMissingPrimaryBank->pluck('id')->all();
    @endphp

    <div class="space-y-6">
        @if($this->employeesMissingPrimaryBank->isNotEmpty())
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
                <div class="mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                        Warning
                    </span>
                    <p class="text-sm font-medium">
                        {{ $this->employeesMissingPrimaryBank->count() }} employee(s) missing a primary bank account. Payroll posting will fail until fixed.
                    </p>
                </div>
                <div class="overflow-x-auto rounded-lg border border-amber-200 bg-white/70 dark:border-amber-800 dark:bg-amber-950/20">
                    <table class="min-w-full divide-y divide-amber-200 text-sm dark:divide-amber-800">
                        <thead class="bg-amber-100/70 text-left text-xs font-semibold uppercase tracking-wide text-amber-900 dark:bg-amber-900/40 dark:text-amber-200">
                            <tr>
                                <th class="px-4 py-3">Employee No.</th>
                                <th class="px-4 py-3">Employee Name</th>
                                <th class="px-4 py-3">Issue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-amber-100 dark:divide-amber-900/40">
                            @foreach($this->employeesMissingPrimaryBank as $employee)
                                <tr>
                                    <td class="px-4 py-3">{{ $employee->employee_number }}</td>
                                    <td class="px-4 py-3">{{ $employee->first_name }} {{ $employee->last_name }}</td>
                                    <td class="px-4 py-3">Primary bank account missing</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-3">
            <section class="{{ $tableWrapper }}">
                <div class="px-4 py-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Earnings</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format((float) $this->record->total_earnings, 2) }}</p>
                </div>
            </section>
            <section class="{{ $tableWrapper }}">
                <div class="px-4 py-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Total Deductions</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format((float) $this->record->total_deductions, 2) }}</p>
                </div>
            </section>
            <section class="{{ $tableWrapper }}">
                <div class="px-4 py-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Net Pay</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format((float) $this->record->total_net_pay, 2) }}</p>
                </div>
            </section>
        </div>

        <section class="{{ $tableWrapper }}">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Payroll Document Overview</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="{{ $tableClass }}">
                    <tbody>
                        <tr>
                            <td class="{{ $cellLabelClass }}">Document Number</td>
                            <td class="{{ $cellValueClass }}">{{ $this->record->document_number }}</td>
                            <td class="{{ $cellLabelClass }}">Status</td>
                            <td class="{{ $cellValueClass }}">{{ $this->record->status?->value ?? $this->record->status }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $cellLabelClass }}">Payroll Period</td>
                            <td class="{{ $cellValueClass }}">{{ optional($this->record->period_start)->format('Y-m-d') }} to {{ optional($this->record->period_end)->format('Y-m-d') }}</td>
                            <td class="{{ $cellLabelClass }}">Employees Seeded</td>
                            <td class="{{ $cellValueClass }}">{{ $this->employeeSummaries->count() }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $cellLabelClass }}">Payroll Period Record</td>
                            <td class="{{ $cellValueClass }}">{{ $this->record->period?->name ?? $this->record->period?->code ?? '—' }}</td>
                            <td class="{{ $cellLabelClass }}">Review Lines</td>
                            <td class="{{ $cellValueClass }}">{{ $this->record->lines->count() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="{{ $tableWrapper }}">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Document Totals</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="{{ $tableClass }}">
                    <thead class="{{ $headClass }}">
                        <tr>
                            <th class="{{ $headCellClass }}">Employees</th>
                            <th class="{{ $headCellClass }}">Review Lines</th>
                            <th class="{{ $headCellClass }}">Missing Bank Setup</th>
                            <th class="{{ $headCellClass }} text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="{{ $bodyCellClass }}">{{ $this->employeeSummaries->count() }}</td>
                            <td class="{{ $bodyCellClass }}">{{ $this->record->lines->count() }}</td>
                            <td class="{{ $bodyCellClass }}">{{ $this->employeesMissingPrimaryBank->count() }}</td>
                            <td class="{{ $bodyCellClass }} text-right">{{ number_format((float) $this->record->total_earnings, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $bodyCellClass }}" colspan="3">Total Deductions</td>
                            <td class="{{ $bodyCellClass }} text-right">{{ number_format((float) $this->record->total_deductions, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="{{ $bodyCellClass }} font-semibold" colspan="3">Total Net Pay</td>
                            <td class="{{ $bodyCellClass }} text-right font-semibold">{{ number_format((float) $this->record->total_net_pay, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="{{ $tableWrapper }}">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Employee Review Summary</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="{{ $tableClass }}">
                    <thead class="{{ $headClass }}">
                        <tr>
                            <th class="{{ $headCellClass }}">Employee</th>
                            <th class="{{ $headCellClass }}">Bank Setup</th>
                            <th class="{{ $headCellClass }} text-right">Earnings</th>
                            <th class="{{ $headCellClass }} text-right">Deductions</th>
                            <th class="{{ $headCellClass }} text-right">Benefits</th>
                            <th class="{{ $headCellClass }} text-right">Net</th>
                            <th class="{{ $headCellClass }} text-right">Lines</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->employeeSummaries as $summary)
                            <tr>
                                <td class="{{ $bodyCellClass }}">{{ $summary['employee_number'] }} - {{ $summary['employee_name'] }}</td>
                                <td class="{{ $bodyCellClass }}">
                                    @if(in_array($summary['employee_id'] ?? null, $missingPrimaryBankEmployeeIds, true))
                                        <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                                            Missing Primary Bank
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                                            Ready
                                        </span>
                                    @endif
                                </td>
                                <td class="{{ $bodyCellClass }} text-right">{{ number_format($summary['earnings'], 2) }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ number_format($summary['deductions'], 2) }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ number_format($summary['benefits'], 2) }}</td>
                                <td class="{{ $bodyCellClass }} text-right font-semibold">{{ number_format($summary['net'], 2) }}</td>
                                <td class="{{ $bodyCellClass }} text-right">{{ $summary['line_count'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="border border-gray-200 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No payroll lines available. Edit document and seed employees first.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="{{ $tableWrapper }}">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Detailed Lines</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="{{ $tableClass }}">
                    <thead class="{{ $headClass }}">
                        <tr>
                            <th class="{{ $headCellClass }}">#</th>
                            <th class="{{ $headCellClass }}">Employee</th>
                            <th class="{{ $headCellClass }}">Pay Code</th>
                            <th class="{{ $headCellClass }}">Type</th>
                            <th class="{{ $headCellClass }} text-right">Amount</th>
                            <th class="{{ $headCellClass }}">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->record->lines as $line)
                            <tr>
                                <td class="{{ $bodyCellClass }} text-gray-500 dark:text-gray-400">{{ $loop->iteration }}</td>
                                <td class="{{ $bodyCellClass }}">{{ $line->employee?->employee_number }} - {{ $line->employee?->first_name }} {{ $line->employee?->last_name }}</td>
                                <td class="{{ $bodyCellClass }}">{{ $line->payCode?->code }} - {{ $line->payCode?->name }}</td>
                                <td class="{{ $bodyCellClass }}">
                                    @php
                                        $lineTypeColor = match ($line->line_type) {
                                            'Earning' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
                                            'Deduction' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
                                            'Benefit' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300',
                                            default => 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $lineTypeColor }}">
                                        {{ $line->line_type }}
                                    </span>
                                </td>
                                <td class="{{ $bodyCellClass }} text-right">{{ number_format((float) $line->amount, 2) }}</td>
                                <td class="{{ $bodyCellClass }}">{{ $line->description }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="border border-gray-200 px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No payroll lines generated yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
