<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use Illuminate\Database\Seeder;

class ChecklistItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['category' => 'Documents', 'label' => 'Original Call-up Letter'],
            ['category' => 'Documents', 'label' => 'Green Card'],
            ['category' => 'Documents', 'label' => 'Statement of Result/Certificate'],
            ['category' => 'Documents', 'label' => 'Medical Fitness Certificate'],
            ['category' => 'Documents', 'label' => 'Passport Photographs'],
            ['category' => 'Camp Essentials', 'label' => 'White Shorts'],
            ['category' => 'Camp Essentials', 'label' => 'White Canvas'],
            ['category' => 'Camp Essentials', 'label' => 'White Socks'],
            ['category' => 'Camp Essentials', 'label' => 'Toiletries'],
            ['category' => 'Camp Essentials', 'label' => 'Bucket'],
            ['category' => 'Camp Essentials', 'label' => 'Mosquito Net'],
            ['category' => 'Camp Essentials', 'label' => 'Torchlight'],
            ['category' => 'Camp Essentials', 'label' => 'Other essential camp items'],
        ];

        foreach ($items as $index => $item) {
            ChecklistItem::query()->updateOrCreate(
                ['category' => $item['category'], 'label' => $item['label']],
                ['sort_order' => $index],
            );
        }
    }
}
