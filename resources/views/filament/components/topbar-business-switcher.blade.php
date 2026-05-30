@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Business> $businesses */
    $activeBusinessId = (int) ($activeBusinessId ?? 0);
    $activeBusiness = $businesses->firstWhere('id', $activeBusinessId);
@endphp

<div class="fi-topbar-item flex items-center gap-2">
    <label for="global-business-switcher" class="text-sm font-medium text-gray-600 dark:text-gray-300">
        Company
    </label>

    <select
        id="global-business-switcher"
        class="fi-select-input block rounded-lg border-gray-300 bg-white px-3 py-1.5 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900"
        onchange="
            const url = new URL(window.location.href);
            url.searchParams.set('business_id', this.value);
            window.location.assign(url.toString());
        "
    >
        @foreach ($businesses as $business)
            <option value="{{ $business->id }}" @selected($business->id === $activeBusinessId)>
                {{ $business->name }}
            </option>
        @endforeach
    </select>

    @if ($activeBusiness)
        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
            Active: {{ $activeBusiness->code }}
        </span>
    @endif
</div>

