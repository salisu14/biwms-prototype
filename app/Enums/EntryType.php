<?php

// app/Enums/EntryType.php

namespace App\Enums;

enum EntryType: string
{
    case RECEIPT = 'RECEIPT';
    case ISSUE = 'ISSUE';
    case TRANSFER_IN = 'TRANSFER_IN';
    case TRANSFER_OUT = 'TRANSFER_OUT';
    case SALE = 'SALE';
    case RETURN = 'RETURN';
    case ADJUSTMENT_POS = 'ADJUSTMENT_POS';
    case ADJUSTMENT_NEG = 'ADJUSTMENT_NEG';
    case SCRAP = 'SCRAP';
    case PRODUCTION_OUTPUT = 'PRODUCTION_OUTPUT';

    public function label(): string
    {
        return match ($this) {
            self::RECEIPT => 'Goods Receipt',
            self::ISSUE => 'Material Issue',
            self::TRANSFER_IN => 'Transfer In',
            self::TRANSFER_OUT => 'Transfer Out',
            self::SALE => 'Sales Shipment',
            self::RETURN => 'Customer Return',
            self::ADJUSTMENT_POS => 'Positive Adjustment',
            self::ADJUSTMENT_NEG => 'Negative Adjustment',
            self::SCRAP => 'Scrap/Waste',
            self::PRODUCTION_OUTPUT => 'Production Output',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RECEIPT => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::ISSUE => 'bg-amber-100 text-amber-800 border-amber-200',
            self::TRANSFER_IN => 'bg-cyan-100 text-cyan-800 border-cyan-200',
            self::TRANSFER_OUT => 'bg-sky-100 text-sky-800 border-sky-200',
            self::SALE => 'bg-green-100 text-green-800 border-green-200',
            self::RETURN => 'bg-rose-100 text-rose-800 border-rose-200',
            self::ADJUSTMENT_POS => 'bg-lime-100 text-lime-800 border-lime-200',
            self::ADJUSTMENT_NEG => 'bg-orange-100 text-orange-800 border-orange-200',
            self::SCRAP => 'bg-gray-100 text-gray-800 border-gray-200',
            self::PRODUCTION_OUTPUT => 'bg-violet-100 text-violet-800 border-violet-200',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::RECEIPT => 'arrow-down',
            self::ISSUE => 'arrow-up',
            self::TRANSFER_IN => 'sign-in-alt',
            self::TRANSFER_OUT => 'sign-out-alt',
            self::SALE => 'shipping-fast',
            self::RETURN => 'undo-alt',
            self::ADJUSTMENT_POS => 'plus-circle',
            self::ADJUSTMENT_NEG => 'minus-circle',
            self::SCRAP => 'trash-alt',
            self::PRODUCTION_OUTPUT => 'industry',
        };
    }

    /**
     * Whether this entry increases inventory
     */
    public function isInbound(): bool
    {
        return in_array($this, [
            self::RECEIPT,
            self::TRANSFER_IN,
            self::RETURN,
            self::ADJUSTMENT_POS,
            self::PRODUCTION_OUTPUT,
        ]);
    }

    /**
     * Whether this entry decreases inventory
     */
    public function isOutbound(): bool
    {
        return in_array($this, [
            self::ISSUE,
            self::TRANSFER_OUT,
            self::SALE,
            self::ADJUSTMENT_NEG,
            self::SCRAP,
        ]);
    }

    /**
     * Get signed quantity multiplier (+1 or -1)
     */
    public function sign(): int
    {
        return $this->isInbound() ? 1 : -1;
    }

    /**
     * Get transaction nature
     */
    public function nature(): string
    {
        return match (true) {
            $this->isInbound() => 'Inbound',
            $this->isOutbound() => 'Outbound',
            default => 'Neutral',
        };
    }

    /**
     * Whether this requires cost tracking
     */
    public function tracksCost(): bool
    {
        return ! in_array($this, [self::TRANSFER_IN, self::TRANSFER_OUT]);
    }

    /**
     * Whether this is a system-generated entry
     */
    public function isSystemGenerated(): bool
    {
        return in_array($this, [
            self::PRODUCTION_OUTPUT,
            self::ADJUSTMENT_POS,
            self::ADJUSTMENT_NEG,
        ]);
    }

    /**
     * Grouped options for UI
     */
    public static function groupedOptions(): array
    {
        return [
            'Inbound (Increases Stock)' => collect(self::cases())
                ->filter(fn ($e) => $e->isInbound())
                ->map(fn ($e) => [
                    'value' => $e->value,
                    'label' => $e->label(),
                    'color' => $e->color(),
                ])
                ->values()
                ->toArray(),
            'Outbound (Decreases Stock)' => collect(self::cases())
                ->filter(fn ($e) => $e->isOutbound())
                ->map(fn ($e) => [
                    'value' => $e->value,
                    'label' => $e->label(),
                    'color' => $e->color(),
                ])
                ->values()
                ->toArray(),
        ];
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
                'icon' => $case->icon(),
                'nature' => $case->nature(),
                'sign' => $case->sign(),
            ])
            ->toArray();
    }
}
