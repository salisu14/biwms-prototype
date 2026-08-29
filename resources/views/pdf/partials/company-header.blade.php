@php
    $companyName = $company['name'] ?? config('app.name', 'Bifli WMS');
@endphp

<div class="company-header">
    @if(!empty($company['logo_data_uri']))
        <div class="company-logo">
            <img src="{{ $company['logo_data_uri'] }}" alt="Company Logo">
        </div>
    @endif

    <div class="company-name">{{ $companyName }}</div>

    @if(!empty($company['trading_name']) && $company['trading_name'] !== $companyName)
        <div class="company-trading">{{ $company['trading_name'] }}</div>
    @endif

    @if(!empty($company['address_lines']))
        <div class="company-meta">{{ implode(' • ', array_filter($company['address_lines'])) }}</div>
    @endif

    <div class="company-meta">
        @if(!empty($company['phone']))
            <span>Tel: {{ $company['phone'] }}</span>
        @endif
        @if(!empty($company['phone']) && !empty($company['email']))
            <span class="divider">•</span>
        @endif
        @if(!empty($company['email']))
            <span>{{ $company['email'] }}</span>
        @endif
        @if((!empty($company['phone']) || !empty($company['email'])) && !empty($company['website']))
            <span class="divider">•</span>
        @endif
        @if(!empty($company['website']))
            <span>{{ $company['website'] }}</span>
        @endif
    </div>

    <div class="company-meta">
        @if(!empty($company['registration_no']))
            <span>Reg No: {{ $company['registration_no'] }}</span>
        @endif
        @if(!empty($company['registration_no']) && !empty($company['tax_no']))
            <span class="divider">•</span>
        @endif
        @if(!empty($company['tax_no']))
            <span>TIN/VAT: {{ $company['tax_no'] }}</span>
        @endif
    </div>
</div>
