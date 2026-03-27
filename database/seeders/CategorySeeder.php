<?php
// database/seeders/CategorySeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // THERAPEUTIC CATEGORIES
        $therapeutic = $this->createCategory([
            'category_code' => 'THER',
            'category_name' => 'Therapeutic Categories',
            'category_type' => 'THERAPEUTIC',
            'level' => 0,
        ]);

        $immune = $this->createCategory([
            'category_code' => 'THER-IMM',
            'category_name' => 'Immune Support',
            'parent_id' => $therapeutic->id,  // Changed from category_id
            'category_type' => 'THERAPEUTIC',
            'level' => 1,
        ]);

        $this->createCategory([
            'category_code' => 'THER-IMM-ADAP',
            'category_name' => 'Adaptogens',
            'parent_id' => $immune->id,  // Changed from category_id
            'category_type' => 'THERAPEUTIC',
            'level' => 2,
        ]);

        $this->createCategory([
            'category_code' => 'THER-IMM-ANTIV',
            'category_name' => 'Antivirals',
            'parent_id' => $immune->id,  // Changed from category_id
            'category_type' => 'THERAPEUTIC',
            'level' => 2,
        ]);

        $cardio = $this->createCategory([
            'category_code' => 'THER-CARD',
            'category_name' => 'Cardiovascular',
            'parent_id' => $therapeutic->id,  // Changed from category_id
            'category_type' => 'THERAPEUTIC',
            'level' => 1,
        ]);

        $this->createCategory([
            'category_code' => 'THER-CARD-HYP',
            'category_name' => 'Hypertension Support',
            'parent_id' => $cardio->id,  // Changed from category_id
            'category_type' => 'THERAPEUTIC',
            'level' => 2,
        ]);

        $cognitive = $this->createCategory([
            'category_code' => 'THER-COG',
            'category_name' => 'Cognitive Support',
            'parent_id' => $therapeutic->id,  // Changed from category_id
            'category_type' => 'THERAPEUTIC',
            'level' => 1,
        ]);

        $digestive = $this->createCategory([
            'category_code' => 'THER-DIG',
            'category_name' => 'Digestive Health',
            'parent_id' => $therapeutic->id,  // Changed from category_id
            'category_type' => 'THERAPEUTIC',
            'level' => 1,
        ]);

        // BOTANICAL CATEGORIES (Part Used)
        $botanical = $this->createCategory([
            'category_code' => 'BOT',
            'category_name' => 'Botanical Parts',
            'category_type' => 'BOTANICAL',
            'level' => 0,
        ]);

        $parts = ['Root', 'Leaf', 'Flower', 'Bark', 'Seed', 'Fruit', 'Rhizome', 'Aerial Parts'];
        foreach ($parts as $part) {
            $this->createCategory([
                'category_code' => 'BOT-' . strtoupper(substr($part, 0, 3)),
                'category_name' => $part,
                'parent_id' => $botanical->id,  // Changed from category_id
                'category_type' => 'BOTANICAL',
                'level' => 1,
            ]);
        }

        // REGULATORY CATEGORIES
        $regulatory = $this->createCategory([
            'category_code' => 'REG',
            'category_name' => 'Regulatory Classification',
            'category_type' => 'REGULATORY',
            'level' => 0,
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
                'parent_id' => $regulatory->id,  // Changed from category_id
                'category_type' => 'REGULATORY',
                'level' => 1,
            ]);
        }

        // DOSAGE FORMS
        $forms = $this->createCategory([
            'category_code' => 'FORM',
            'category_name' => 'Dosage Forms',
            'category_type' => 'FORM',
            'level' => 0,
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
                'parent_id' => $forms->id,  // Changed from category_id
                'category_type' => 'FORM',
                'level' => 1,
            ]);
        }

        // SOURCE TYPES
        $source = $this->createCategory([
            'category_code' => 'SRC',
            'category_name' => 'Source/Origin',
            'category_type' => 'SOURCE',
            'level' => 0,
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
                'parent_id' => $source->id,  // Changed from category_id
                'category_type' => 'SOURCE',
                'level' => 1,
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
            $path = $parent->hierarchy_path . '.' . $data['category_code'];
        }

        $data['hierarchy_path'] = $path;

        return Category::create($data);
    }
}
