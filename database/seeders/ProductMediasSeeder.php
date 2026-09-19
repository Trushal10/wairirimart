<?php

namespace Database\Seeders;

use App\Models\ProductMedia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ProductMediasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productImages = ['1.webp', '2.webp', '3.webp', '4.webp', '5.webp', '6.webp', '7.webp', '8.webp', '9.webp', '10.webp', '11.webp', '12.webp', '13.webp', '14.webp', '15.webp', '16.webp', '17.webp', '18.webp', '19.webp', '20.webp'];

        $data = [];

        for ($i = 0; $i < count($productImages); $i++) {
            $sourcePath = storage_path('images/'.$productImages[$i]);
            $destinationPath = 'product/'.$productImages[$i];

            Storage::disk('public')->put($destinationPath, file_get_contents($sourcePath));

            $data[] = [
                'product_id' => ($i + 1),
                'url' => $productImages[$i],
                'type' => 'image',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        ProductMedia::insert($data);
    }
}
