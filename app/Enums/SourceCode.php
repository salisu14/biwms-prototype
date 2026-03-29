<?php

namespace App\Enums;

enum SourceCode: string
{
    case ITEM_JNL = 'ITEM_JNL';
    case TRANSFER = 'TRANSFER';
    case PHYS_INV = 'PHYS_INV';
    case RECLASS = 'RECLASS';
    case CONSUMP = 'CONSUMP';
    case OUTPUT = 'OUTPUT';

    public function label(): string
    {
        return match($this) {
            self::ITEM_JNL => 'Item Journal Entry',
            self::TRANSFER => 'Transfer Order Entry',
            self::PHYS_INV => 'Phys. Inventory Ledger',
            self::RECLASS => 'Reclassification Entry',
            self::CONSUMP => 'Consumption Ledger',
            self::OUTPUT => 'Output Ledger',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::ITEM_JNL, self::TRANSFER => 'bg-slate-100 text-slate-800',
            self::PHYS_INV, self::RECLASS => 'bg-zinc-100 text-zinc-800',
            self::CONSUMP, self::OUTPUT => 'bg-neutral-100 text-neutral-800',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::ITEM_JNL => 'heroicon-o-document-text',
            self::TRANSFER => 'heroicon-o-truck',
            self::PHYS_INV => 'heroicon-o-check-circle',
            self::RECLASS => 'heroicon-o-pencil-square',
            self::CONSUMP => 'heroicon-o-minus-circle',
            self::OUTPUT => 'heroicon-o-plus-circle',
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
            ])
            ->toArray();
    }
}
