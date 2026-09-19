<?php

namespace Database\Seeders;

use App\Models\Category;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();
        $categoryImage = ['cls-jewelry1.webp', 'cls-jewelry2.webp', 'cls-jewelry3.webp', 'cls-jewelry4.webp',  'cls-jewelry5.webp', 'cls-jewelry6.webp'];
        $data = [];
        for ($i = 0; $i < count($categoryImage); $i++) {
            $sourcePath = storage_path('images/'.$categoryImage[$i]);
            $destinationPath = 'category/'.$categoryImage[$i];

            Storage::disk('public')->put($destinationPath, file_get_contents($sourcePath));

            $name = $faker->word();
            $data[] = [
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => $faker->sentence(),
                'image' => $categoryImage[$i],
                'status' => $faker->randomElement([0, 1]),
                'featured' => $faker->randomElement([0, 1]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        Category::insert($data);
    }
}
