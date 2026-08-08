<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\ItemType;
use App\Enums\ProductionBomLineBasis;
use App\Enums\ProductionHierarchyNodeType;
use App\Models\Item;
use App\Models\Manufacturing\ProductionBom;
use App\Models\Manufacturing\ProductionBomLine;
use App\Models\Manufacturing\ProductionBomVersion;
use App\Models\Manufacturing\ProductionBomVersionLine;
use App\Models\Manufacturing\ProductionOrder;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class MultiLevelBomExplosionService
{
    private const CERTIFIED_STATUS = 'CERTIFIED';

    /**
     * @return array{
     *     root: array<string, mixed>,
     *     nodes: array<int, array<string, mixed>>,
     *     manufactured_count: int,
     *     node_count: int,
     *     max_depth: int
     * }
     */
    public function explode(ProductionOrder $rootOrder, int $maxDepth = 25): array
    {
        if ($maxDepth < 1 || $maxDepth > 25) {
            throw new RuntimeException('Multi-level BOM explosion depth must be between 1 and 25.');
        }

        $rootOrder->loadMissing(['item.baseUom', 'productionBom.versions.lines.item.baseUom', 'productionBom.lines.item.baseUom']);

        $rootItem = $rootOrder->item;
        if (! $rootItem) {
            throw new RuntimeException('Production order has no output item.');
        }

        $rootBom = $this->resolveBomForOrder($rootOrder);
        $rootVersion = $rootBom->getActiveVersion();
        $rootQuantityBase = DecimalMath::quantity($rootOrder->quantity_base ?: $rootOrder->quantity ?: '0');

        $root = [
            'path' => '1',
            'level' => 0,
            'item_id' => $rootItem->id,
            'item_no' => $rootItem->item_code,
            'description' => $rootItem->description,
            'unit_of_measure_code' => $this->baseUomCode($rootItem, $rootOrder->unit_of_measure_code),
            'required_quantity_base' => $rootQuantityBase,
            'planned_output_quantity_base' => $rootQuantityBase,
            'source_bom_id' => $rootBom->id,
            'source_bom_version_id' => $rootVersion?->id,
        ];

        $nodes = $this->explodeBom(
            bom: $rootBom,
            version: $rootVersion,
            parentPath: '1',
            parentOrderPath: '1',
            parentQuantityBase: $rootQuantityBase,
            level: 1,
            maxDepth: $maxDepth,
            bomStack: [$rootBom->id],
        );

        return [
            'root' => $root,
            'nodes' => $nodes,
            'manufactured_count' => collect($nodes)->where('is_manufactured_requirement', true)->count(),
            'node_count' => count($nodes) + 1,
            'max_depth' => $maxDepth,
        ];
    }

    /**
     * @param  array<int, int>  $bomStack
     * @return array<int, array<string, mixed>>
     */
    private function explodeBom(
        ProductionBom $bom,
        ?ProductionBomVersion $version,
        string $parentPath,
        string $parentOrderPath,
        string $parentQuantityBase,
        int $level,
        int $maxDepth,
        array $bomStack,
    ): array {
        if ($level > $maxDepth) {
            throw new RuntimeException("BOM explosion exceeded maximum depth of {$maxDepth}.");
        }

        $lines = $this->resolveLines($bom, $version);
        $exploded = [];
        $siblingIndex = 1;

        foreach ($lines as $line) {
            $item = $this->resolveLineItem($line);
            $relatedBom = $this->resolveRelatedBom($line, $item);
            $normalizedQuantityPer = $this->normalizedQuantityPer($line, $version);
            $requiredQuantity = $this->lineRequiredQuantity($parentQuantityBase, $normalizedQuantityPer, $line->scrap_percent ?? '0');
            $requiredQuantityBase = $this->convertLineQuantityToItemBase($requiredQuantity, $line, $item);
            $isManufactured = $this->isManufacturedRequirement($item, $relatedBom);
            $nodePath = "{$parentPath}.{$siblingIndex}";

            $node = [
                'path' => $nodePath,
                'parent_path' => $parentPath,
                'parent_order_path' => $parentOrderPath,
                'level' => $level,
                'node_type' => $this->nodeTypeFor($item, $isManufactured),
                'item_id' => $item->id,
                'item_no' => $item->item_code,
                'description' => $line->description ?: $item->description,
                'unit_of_measure_code' => $this->baseUomCode($item, $line->unit_of_measure_code),
                'required_quantity' => DecimalMath::quantity($requiredQuantity),
                'required_quantity_base' => DecimalMath::quantity($requiredQuantityBase),
                'planned_output_quantity_base' => $isManufactured ? DecimalMath::quantity($requiredQuantityBase) : DecimalMath::quantity('0'),
                'quantity_per' => DecimalMath::quantity($normalizedQuantityPer),
                'scrap_percent' => DecimalMath::toScale($line->scrap_percent ?? '0', 2),
                'source_bom_id' => $bom->id,
                'source_bom_version_id' => $version?->id,
                'source_bom_line_id' => $line instanceof ProductionBomLine ? $line->id : null,
                'source_bom_version_line_id' => $line instanceof ProductionBomVersionLine ? $line->id : null,
                'source_line_number' => (int) $line->line_number,
                'source_bom_code' => $bom->code,
                'line_basis' => ProductionBomLineBasis::PerUnit,
                'is_manufactured_requirement' => $isManufactured,
                'child_bom_id' => $relatedBom?->id,
                'child_bom_version_id' => $relatedBom?->getActiveVersion()?->id,
                'line_type' => $line->type,
                'flushing_method' => $line->flushing_method ?? 'MANUAL',
                'routing_link_code' => $line->routing_link_code,
                'location_code' => $line->location_code,
                'bin_code' => $line->bin_code,
            ];

            $exploded[] = $node;

            if ($isManufactured && $relatedBom) {
                if (in_array($relatedBom->id, $bomStack, true)) {
                    throw new RuntimeException("Circular production BOM reference detected at BOM {$relatedBom->code}.");
                }

                $exploded = [
                    ...$exploded,
                    ...$this->explodeBom(
                        bom: $relatedBom,
                        version: $relatedBom->getActiveVersion(),
                        parentPath: $nodePath,
                        parentOrderPath: $nodePath,
                        parentQuantityBase: DecimalMath::quantity($requiredQuantityBase),
                        level: $level + 1,
                        maxDepth: $maxDepth,
                        bomStack: [...$bomStack, $relatedBom->id],
                    ),
                ];
            }

            $siblingIndex++;
        }

        return $exploded;
    }

    private function resolveBomForOrder(ProductionOrder $order): ProductionBom
    {
        $bom = $order->productionBom ?: $this->resolveBomForItem($order->item);

        if (! $bom) {
            throw new RuntimeException('Production order output item has no certified production BOM.');
        }

        return $this->assertCertifiedBom($bom);
    }

    private function resolveBomForItem(?Item $item): ?ProductionBom
    {
        if (! $item) {
            return null;
        }

        if ($item->production_bom_id) {
            return ProductionBom::query()->find($item->production_bom_id);
        }

        return ProductionBom::query()
            ->where('item_id', $item->id)
            ->where('status', self::CERTIFIED_STATUS)
            ->orderByDesc('id')
            ->first();
    }

    private function assertCertifiedBom(ProductionBom $bom): ProductionBom
    {
        if ((string) $bom->status !== self::CERTIFIED_STATUS) {
            throw new RuntimeException("Production BOM {$bom->code} is not certified.");
        }

        return $bom;
    }

    /**
     * @return Collection<int, ProductionBomLine|ProductionBomVersionLine>
     */
    private function resolveLines(ProductionBom $bom, ?ProductionBomVersion $version): Collection
    {
        if ($version) {
            if (! $version->isActive()) {
                throw new RuntimeException("Production BOM version {$version->version_code} is not certified or active.");
            }

            return $version->lines()
                ->with(['item.baseUom', 'relatedBom.item.baseUom', 'relatedBom.versions.lines.item.baseUom', 'relatedBom.lines.item.baseUom'])
                ->orderBy('line_number')
                ->get();
        }

        return $bom->lines()
            ->with(['item.baseUom', 'relatedBom.item.baseUom', 'relatedBom.versions.lines.item.baseUom', 'relatedBom.lines.item.baseUom'])
            ->orderBy('line_number')
            ->get();
    }

    private function resolveLineItem(ProductionBomLine|ProductionBomVersionLine $line): Item
    {
        if ($line->item) {
            return $line->item;
        }

        if ($line->type === ProductionBomLine::TYPE_PRODUCTION_BOM && $line->relatedBom?->item) {
            return $line->relatedBom->item;
        }

        throw new RuntimeException("BOM line {$line->line_number} has no item to plan.");
    }

    private function resolveRelatedBom(ProductionBomLine|ProductionBomVersionLine $line, Item $item): ?ProductionBom
    {
        $bom = $line->type === ProductionBomLine::TYPE_PRODUCTION_BOM
            ? $line->relatedBom
            : $this->resolveBomForItem($item);

        return $bom ? $this->assertCertifiedBom($bom) : null;
    }

    private function normalizedQuantityPer(ProductionBomLine|ProductionBomVersionLine $line, ?ProductionBomVersion $version): string
    {
        $quantityPer = DecimalMath::quantity($line->quantity_per ?: '0');

        if ($line instanceof ProductionBomVersionLine && $version && DecimalMath::compare($version->quantity_per ?? '1', '1') > 0) {
            return DecimalMath::div($quantityPer, $version->quantity_per, DecimalPrecision::QUANTITY_SCALE);
        }

        return $quantityPer;
    }

    private function lineRequiredQuantity(string $parentQuantityBase, string $quantityPer, mixed $scrapPercent): string
    {
        $baseQuantity = DecimalMath::mul($parentQuantityBase, $quantityPer, DecimalPrecision::QUANTITY_SCALE);
        $scrapMultiplier = DecimalMath::add(
            '1',
            DecimalMath::div($scrapPercent ?? '0', '100', DecimalPrecision::CONVERSION_SCALE),
            DecimalPrecision::CONVERSION_SCALE,
        );

        return DecimalMath::mul($baseQuantity, $scrapMultiplier, DecimalPrecision::QUANTITY_SCALE);
    }

    private function convertLineQuantityToItemBase(string $quantity, ProductionBomLine|ProductionBomVersionLine $line, Item $item): string
    {
        $lineUomCode = (string) ($line->unit_of_measure_code ?: $this->baseUomCode($item, null));
        $baseUomCode = $this->baseUomCode($item, $lineUomCode);

        if (strtoupper($lineUomCode) === strtoupper($baseUomCode)) {
            return DecimalMath::quantity($quantity);
        }

        $conversionFactor = $item->getConversionFactorForUomDecimal($lineUomCode);

        return DecimalMath::mul($quantity, $conversionFactor, DecimalPrecision::QUANTITY_SCALE);
    }

    private function isManufacturedRequirement(Item $item, ?ProductionBom $relatedBom): bool
    {
        if (! $relatedBom) {
            return false;
        }

        $itemType = $item->item_type instanceof ItemType ? $item->item_type : ItemType::tryFrom((string) $item->item_type);

        return in_array($itemType, [ItemType::SEMI_FINISHED, ItemType::FINISHED_GOOD], true);
    }

    private function nodeTypeFor(Item $item, bool $isManufactured): ProductionHierarchyNodeType
    {
        if ($isManufactured) {
            return ProductionHierarchyNodeType::ManufacturedComponent;
        }

        $itemType = $item->item_type instanceof ItemType ? $item->item_type : ItemType::tryFrom((string) $item->item_type);

        return match ($itemType) {
            ItemType::PACKAGING => ProductionHierarchyNodeType::PackagingComponent,
            ItemType::CONSUMABLE, ItemType::SPARE_PART => ProductionHierarchyNodeType::ConsumableComponent,
            ItemType::SERVICE => ProductionHierarchyNodeType::ServiceComponent,
            default => ProductionHierarchyNodeType::PurchasedComponent,
        };
    }

    private function baseUomCode(Item $item, ?string $fallback): string
    {
        return (string) ($item->baseUom?->uom_code ?: $fallback ?: 'PCS');
    }
}
