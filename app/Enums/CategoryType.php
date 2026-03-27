<?php
// app/Enums/CategoryType.php

namespace App\Enums;

enum CategoryType: string
{
    case THERAPEUTIC = 'THERAPEUTIC';
    case BOTANICAL = 'BOTANICAL';
    case REGULATORY = 'REGULATORY';
    case FORM = 'FORM';
    case SOURCE = 'SOURCE';
    case PROCESSING = 'PROCESSING';

    public function label(): string
    {
        return match($this) {
            self::THERAPEUTIC => 'Therapeutic Category',
            self::BOTANICAL => 'Botanical/Part Used',
            self::REGULATORY => 'Regulatory Classification',
            self::FORM => 'Dosage Form',
            self::SOURCE => 'Source/Origin',
            self::PROCESSING => 'Processing Type',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::THERAPEUTIC => 'bg-green-100 text-green-800',
            self::BOTANICAL => 'bg-amber-100 text-amber-800',
            self::REGULATORY => 'bg-blue-100 text-blue-800',
            self::FORM => 'bg-purple-100 text-purple-800',
            self::SOURCE => 'bg-teal-100 text-teal-800',
            self::PROCESSING => 'bg-pink-100 text-pink-800',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::THERAPEUTIC => 'heart-pulse',
            self::BOTANICAL => 'leaf',
            self::REGULATORY => 'shield-alt',
            self::FORM => 'capsules',
            self::SOURCE => 'globe',
            self::PROCESSING => 'flask',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::THERAPEUTIC => 'Health benefit categories (immune, cardiovascular, etc.)',
            self::BOTANICAL => 'Plant part used (root, leaf, flower, bark)',
            self::REGULATORY => 'FDA/EMA classification (supplement, drug, cosmetic)',
            self::FORM => 'Final product form (tincture, capsule, tablet, tea)',
            self::SOURCE => 'Cultivation method (organic, wildcrafted, conventional)',
            self::PROCESSING => 'Manufacturing method (extract, whole herb, standardized)',
        };
    }

    public function allowsMultiple(): bool
    {
        return match($this) {
            self::THERAPEUTIC => true,  // Can be both immune and anti-inflammatory
            self::BOTANICAL => false,   // Only one part per item
            self::REGULATORY => false,  // Only one regulatory path
            self::FORM => false,        // Only one dosage form
            self::SOURCE => false,      // Only one source type
            self::PROCESSING => false,  // Only one processing type
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
                'icon' => $case->icon(),
                'description' => $case->description(),
                'allows_multiple' => $case->allowsMultiple(),
            ])
            ->toArray();
    }
}
