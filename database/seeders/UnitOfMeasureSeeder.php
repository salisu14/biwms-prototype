<?php

// database/seeders/UnitOfMeasureSeeder.php

namespace Database\Seeders;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class UnitOfMeasureSeeder extends Seeder
{
    public function run(): void
    {
        $uoms = [
            // Base units (is_base_uom = true)
            [
                'uom_code' => 'PCS',
                'description' => 'Pieces',
                'conversion_factor' => 1.000000,
                'is_base_uom' => true,
            ],
            [
                'uom_code' => 'EA',
                'description' => 'Each',
                'conversion_factor' => 1.000000,
                'is_base_uom' => true,
            ],
            [
                'uom_code' => 'KG',
                'description' => 'Kilogram',
                'conversion_factor' => 1.000000,
                'is_base_uom' => true,
            ],
            [
                'uom_code' => 'G',
                'description' => 'Gram',
                'conversion_factor' => 1.000000,
                'is_base_uom' => true,
            ],
            [
                'uom_code' => 'L',
                'description' => 'Liter',
                'conversion_factor' => 1.000000,
                'is_base_uom' => true,
            ],
            [
                'uom_code' => 'ML',
                'description' => 'Milliliter',
                'conversion_factor' => 1.000000,
                'is_base_uom' => true,
            ],
            [
                'uom_code' => 'M',
                'description' => 'Meter',
                'conversion_factor' => 1.000000,
                'is_base_uom' => true,
            ],
            [
                'uom_code' => 'CM',
                'description' => 'Centimeter',
                'conversion_factor' => 1.000000,
                'is_base_uom' => true,
            ],
            [
                'uom_code' => 'MM',
                'description' => 'Millimeter',
                'conversion_factor' => 1.000000,
                'is_base_uom' => true,
            ],
            [
                'uom_code' => 'FT',
                'description' => 'Foot',
                'conversion_factor' => 1.000000,
                'is_base_uom' => true,
            ],
            [
                'uom_code' => 'IN',
                'description' => 'Inch',
                'conversion_factor' => 1.000000,
                'is_base_uom' => true,
            ],
            [
                'uom_code' => 'LB',
                'description' => 'Pound',
                'conversion_factor' => 1.000000,
                'is_base_uom' => true,
            ],
            [
                'uom_code' => 'OZ',
                'description' => 'Ounce',
                'conversion_factor' => 1.000000,
                'is_base_uom' => true,
            ],
            [
                'uom_code' => 'GAL',
                'description' => 'Gallon',
                'conversion_factor' => 1.000000,
                'is_base_uom' => true,
            ],

            // Derived units (is_base_uom = false, with conversion factors)
            [
                'uom_code' => 'DZ',
                'description' => 'Dozen',
                'conversion_factor' => 12.000000,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'GRS',
                'description' => 'Gross (144)',
                'conversion_factor' => 144.000000,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'BX',
                'description' => 'Box',
                'conversion_factor' => 24.000000,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'CS',
                'description' => 'Case',
                'conversion_factor' => 48.000000,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'PK',
                'description' => 'Pack',
                'conversion_factor' => 12.000000,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'CT',
                'description' => 'Carton',
                'conversion_factor' => 288.000000,
                'is_base_uom' => true,
            ],
            [
                'uom_code' => 'RL',
                'description' => 'Roll',
                'conversion_factor' => 100.000000,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'SET',
                'description' => 'Set',
                'conversion_factor' => 1.000000,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'KT',
                'description' => 'Kit',
                'conversion_factor' => 1.000000,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'PR',
                'description' => 'Pair',
                'conversion_factor' => 2.000000,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'BG',
                'description' => 'Bag',
                'conversion_factor' => 25.000000,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'DR',
                'description' => 'Drum',
                'conversion_factor' => 55.000000,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'PLT',
                'description' => 'Pallet',
                'conversion_factor' => 480.000000,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'TON',
                'description' => 'Metric Ton',
                'conversion_factor' => 1000.000000,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'MG',
                'description' => 'Milligram',
                'conversion_factor' => 0.001000,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'KM',
                'description' => 'Kilometer',
                'conversion_factor' => 1000.000000,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'YD',
                'description' => 'Yard',
                'conversion_factor' => 0.914400,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'FT2',
                'description' => 'Square Foot',
                'conversion_factor' => 0.092903,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'FT3',
                'description' => 'Cubic Foot',
                'conversion_factor' => 0.028317,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'QT',
                'description' => 'Quart',
                'conversion_factor' => 0.946353,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'PT',
                'description' => 'Pint',
                'conversion_factor' => 0.473176,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'CUP',
                'description' => 'Cup',
                'conversion_factor' => 0.236588,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'TSP',
                'description' => 'Teaspoon',
                'conversion_factor' => 0.004929,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'TBSP',
                'description' => 'Tablespoon',
                'conversion_factor' => 0.014787,
                'is_base_uom' => false,
            ],
            [
                'uom_code' => 'FL_OZ',
                'description' => 'Fluid Ounce',
                'conversion_factor' => 0.029574,
                'is_base_uom' => false,
            ],
        ];

        foreach ($uoms as $uom) {
            UnitOfMeasure::firstOrCreate(
                ['uom_code' => $uom['uom_code']],
                $uom
            );
        }

        $this->command->info('Unit of Measures seeded successfully!');
        $this->command->info('Total: '.count($uoms).' UOMs');
        $this->command->info('Base UOMs: '.collect($uoms)->where('is_base_uom', true)->count());
        $this->command->info('Derived UOMs: '.collect($uoms)->where('is_base_uom', false)->count());
    }
}
