@php
    $logoUrl = $getRecord()->logo_url;
@endphp

@if($logoUrl)
    <img
        src="{{ $logoUrl }}?v={{ optional($getRecord()->updated_at)->timestamp }}"
        alt="Company Logo"
        class="h-8 w-8 rounded-full object-cover border border-gray-200"
        loading="lazy"
        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
    >
    <div class="hidden h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-[10px] text-gray-500 border border-gray-200">
        N/A
    </div>
@else
    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-[10px] text-gray-500 border border-gray-200">
        N/A
    </div>
@endif
