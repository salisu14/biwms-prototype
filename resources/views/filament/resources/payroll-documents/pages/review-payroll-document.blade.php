<x-filament-panels::page>
    <div class="space-y-6">
        @if($this->employeesMissingPrimaryBank->isNotEmpty())
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                        Warning
                    </span>
                    <p class="text-sm font-medium">
                        {{ $this->employeesMissingPrimaryBank->count() }} employee(s) missing a primary bank account. Payroll posting will fail until fixed.
                    </p>
                </div>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                    @foreach($this->employeesMissingPrimaryBank as $employee)
                        <li>{{ $employee->employee_number }} - {{ $employee->first_name }} {{ $employee->last_name }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500">Document No.</p>
                <p class="font-semibold">{{ $this->record->document_number }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500">Period</p>
                <p class="font-semibold">
                    {{ optional($this->record->period_start)->format('Y-m-d') }} to {{ optional($this->record->period_end)->format('Y-m-d') }}
                </p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500">Status</p>
                <p class="font-semibold">{{ $this->record->status?->value ?? $this->record->status }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm text-gray-500">Employees</p>
                <p class="font-semibold">{{ $this->employeeSummaries->count() }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <h3 class="mb-3 text-base font-semibold">Employee Review Summary</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">Employee</th>
                            <th class="px-3 py-2 text-right">Earnings</th>
                            <th class="px-3 py-2 text-right">Deductions</th>
                            <th class="px-3 py-2 text-right">Benefits</th>
                            <th class="px-3 py-2 text-right">Net</th>
                            <th class="px-3 py-2 text-right">Lines</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($this->employeeSummaries as $summary)
                            <tr>
                                <td class="px-3 py-2">{{ $summary['employee_number'] }} - {{ $summary['employee_name'] }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($summary['earnings'], 2) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($summary['deductions'], 2) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($summary['benefits'], 2) }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ number_format($summary['net'], 2) }}</td>
                                <td class="px-3 py-2 text-right">{{ $summary['line_count'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-4 text-center text-gray-500">No payroll lines available. Edit document and seed employees first.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <h3 class="mb-3 text-base font-semibold">Detailed Lines</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-3 py-2 text-left">Employee</th>
                            <th class="px-3 py-2 text-left">Pay Code</th>
                            <th class="px-3 py-2 text-left">Type</th>
                            <th class="px-3 py-2 text-right">Amount</th>
                            <th class="px-3 py-2 text-left">Description</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($this->record->lines as $line)
                            <tr>
                                <td class="px-3 py-2">{{ $line->employee?->employee_number }} - {{ $line->employee?->first_name }} {{ $line->employee?->last_name }}</td>
                                <td class="px-3 py-2">{{ $line->payCode?->code }} - {{ $line->payCode?->name }}</td>
                                <td class="px-3 py-2">{{ $line->line_type }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $line->amount, 2) }}</td>
                                <td class="px-3 py-2">{{ $line->description }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-4 text-center text-gray-500">No payroll lines generated yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
