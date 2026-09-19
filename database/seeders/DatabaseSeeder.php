<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMedia;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Idempotent — safe to run repeatedly. Reference-data seeders
     * (delivery partners, payment gateways) already use updateOrCreate.
     * Demo-catalog seeders are skipped when their target table has rows
     * so re-runs don't duplicate images or break unique slugs.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('Pass@123'),
                'role'     => 'admin',
            ]
        );

        // Always idempotent.
        $this->call([
            DeliveryPartnerSeeder::class,
            PaymentGatewaySeeder::class,
        ]);

        // Demo catalog — only seed on an empty catalog. Re-running would
        // stack duplicate rows and break the assumed product IDs (1–20)
        // that ProductMediasSeeder relies on.
        if (Category::query()->doesntExist()) {
            $this->call(CategorySeeder::class);
        } else {
            $this->command->warn('CategorySeeder skipped — categories already exist.');
        }

        if (Product::query()->doesntExist()) {
            $this->call(ProductSeeder::class);
        } else {
            $this->command->warn('ProductSeeder skipped — products already exist.');
        }

        if (ProductCategory::query()->doesntExist()) {
            $this->call(ProductCategorySeeder::class);
        } else {
            $this->command->warn('ProductCategorySeeder skipped — mappings already exist.');
        }

        if (ProductMedia::query()->doesntExist()) {
            $this->call(ProductMediasSeeder::class);
        } else {
            $this->command->warn('ProductMediasSeeder skipped — media already exist.');
        }

        // Runs after the catalog: it reads product names to pick matching
        // review copy, so it needs the products to already be in place.
        if (Review::query()->doesntExist()) {
            $this->call(ReviewSeeder::class);
        } else {
            $this->command->warn('ReviewSeeder skipped — reviews already exist.');
        }
    }
}
