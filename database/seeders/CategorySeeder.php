<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    private int $sortOrder = 0;

    public function run(): void
    {
        // THERAPEUTIC CATEGORIES
        $therapeutic = $this->createCategory([
            'category_code' => 'THER',
            'category_name' => 'Therapeutic Categories',
            'category_type' => 'THERAPEUTIC',
            'level' => 0,
            'description' => 'Root category for all therapeutic classifications',
        ]);

        $immune = $this->createCategory([
            'category_code' => 'THER-IMM',
            'category_name' => 'Immune Support',
            'parent_id' => $therapeutic->id,
            'category_type' => 'THERAPEUTIC',
            'level' => 1,
            'description' => 'Categories related to immune system support',
        ]);

        $this->createCategory([
            'category_code' => 'THER-IMM-ADAP',
            'category_name' => 'Adaptogens',
            'parent_id' => $immune->id,
            'category_type' => 'THERAPEUTIC',
            'level' => 2,
            'description' => 'Herbs that help the body adapt to stress',
        ]);

        $this->createCategory([
            'category_code' => 'THER-IMM-ANTIV',
            'category_name' => 'Antivirals',
            'parent_id' => $immune->id,
            'category_type' => 'THERAPEUTIC',
            'level' => 2,
            'description' => 'Herbs with antiviral properties',
        ]);

        $cardio = $this->createCategory([
            'category_code' => 'THER-CARD',
            'category_name' => 'Cardiovascular',
            'parent_id' => $therapeutic->id,
            'category_type' => 'THERAPEUTIC',
            'level' => 1,
            'description' => 'Categories for cardiovascular health',
        ]);

        $this->createCategory([
            'category_code' => 'THER-CARD-HYP',
            'category_name' => 'Hypertension Support',
            'parent_id' => $cardio->id,
            'category_type' => 'THERAPEUTIC',
            'level' => 2,
            'description' => 'Support for healthy blood pressure',
        ]);

        $cognitive = $this->createCategory([
            'category_code' => 'THER-COG',
            'category_name' => 'Cognitive Support',
            'parent_id' => $therapeutic->id,
            'category_type' => 'THERAPEUTIC',
            'level' => 1,
            'description' => 'Categories for brain and cognitive health',
        ]);

        $digestive = $this->createCategory([
            'category_code' => 'THER-DIG',
            'category_name' => 'Digestive Health',
            'parent_id' => $therapeutic->id,
            'category_type' => 'THERAPEUTIC',
            'level' => 1,
            'description' => 'Categories for digestive system health',
        ]);

        // BOTANICAL CATEGORIES
        $botanical = $this->createCategory([
            'category_code' => 'BOT',
            'category_name' => 'Botanical Parts',
            'category_type' => 'BOTANICAL',
            'level' => 0,
            'description' => 'Classification by plant part used',
        ]);

        $parts = ['Root', 'Leaf', 'Flower', 'Bark', 'Seed', 'Fruit', 'Rhizome', 'Aerial Parts'];
        foreach ($parts as $part) {
            $this->createCategory([
                'category_code' => 'BOT-'.strtoupper(substr($part, 0, 3)),
                'category_name' => $part,
                'parent_id' => $botanical->id,
                'category_type' => 'BOTANICAL',
                'level' => 1,
                'description' => "Products using {$part} as primary material",
            ]);
        }

        // REGULATORY CATEGORIES
        $regulatory = $this->createCategory([
            'category_code' => 'REG',
            'category_name' => 'Regulatory Classification',
            'category_type' => 'REGULATORY',
            'level' => 0,
            'description' => 'Regulatory and legal classification categories',
        ]);

        $regTypes = [
            'Dietary Supplement' => 'DS',
            'Homeopathic Drug' => 'HP',
            'Cosmetic' => 'COS',
            'Food/Beverage' => 'FOOD',
            'Traditional Herbal Medicine' => 'THM',
        ];
        foreach ($regTypes as $name => $code) {
            $this->createCategory([
                'category_code' => "REG-$code",
                'category_name' => $name,
                'parent_id' => $regulatory->id,
                'category_type' => 'REGULATORY',
                'level' => 1,
                'description' => "Regulatory classification: {$name}",
            ]);
        }

        // DOSAGE FORMS
        $forms = $this->createCategory([
            'category_code' => 'FORM',
            'category_name' => 'Dosage Forms',
            'category_type' => 'FORM',
            'level' => 0,
            'description' => 'Physical forms and delivery methods',
        ]);

        $formTypes = [
            'Tincture' => 'TINC',
            'Capsule' => 'CAP',
            'Tablet' => 'TAB',
            'Softgel' => 'SG',
            'Tea/Cut' => 'TEA',
            'Powder' => 'PWD',
            'Extract' => 'EXT',
            'Cream/Ointment' => 'CRM',
        ];
        foreach ($formTypes as $name => $code) {
            $this->createCategory([
                'category_code' => "FORM-$code",
                'category_name' => $name,
                'parent_id' => $forms->id,
                'category_type' => 'FORM',
                'level' => 1,
                'description' => "Dosage form: {$name}",
            ]);
        }

        // SOURCE TYPES
        $source = $this->createCategory([
            'category_code' => 'SRC',
            'category_name' => 'Source/Origin',
            'category_type' => 'SOURCE',
            'level' => 0,
            'description' => 'Source and origin classifications',
        ]);

        $sourceTypes = [
            'Certified Organic' => 'ORG',
            'Wildcrafted' => 'WILD',
            'Conventional' => 'CONV',
            'Biodynamic' => 'BIO',
            'Fair Trade' => 'FAIR',
        ];
        foreach ($sourceTypes as $name => $code) {
            $this->createCategory([
                'category_code' => "SRC-$code",
                'category_name' => $name,
                'parent_id' => $source->id,
                'category_type' => 'SOURCE',
                'level' => 1,
                'description' => "Source type: {$name}",
            ]);
        }
    }

    private function createCategory(array $data): Category
    {
        $parentId = $data['parent_id'] ?? null;
        $level = $data['level'] ?? 0;

        // Build hierarchy path
        $path = $data['category_code'];
        if ($parentId) {
            $parent = Category::find($parentId);
            if ($parent) {
                $path = $parent->hierarchy_path.'.'.$data['category_code'];
            }
        }

        $data['hierarchy_path'] = $path;
        $data['sort_order'] = $this->sortOrder++;
        $data['description'] = $data['description'] ?? null;
        $data['attributes'] = $data['attributes'] ?? null;
        $data['is_active'] = $data['is_active'] ?? true;

        return Category::create($data);
    }
}
