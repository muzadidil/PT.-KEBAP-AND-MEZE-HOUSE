<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Owner;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Isi awal supaya aplikasi bisa langsung dicoba: dua pemilik, dua akun,
 * daftar menu, dan beberapa pemasok. Aman dijalankan ulang, semuanya pakai
 * updateOrCreate sehingga tidak menggandakan data yang sudah ada.
 *
 * Kata sandi bawaannya sengaja jelas dan harus diganti sebelum dipakai
 * sungguhan; lihat README.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->owners();
        $this->users();
        $this->menu();
        $this->suppliers();

        // Data induk pembukuan bulanan; lihat ZeytinSeeder.
        $this->call(ZeytinSeeder::class);
    }

    protected function owners(): void
    {
        // Kesepakatan bagi tanggungan: Aslan 60%, Leo 40%.
        foreach ([['Aslan', 60], ['Leo', 40]] as [$name, $percent]) {
            Owner::updateOrCreate(
                ['name' => $name],
                ['share_percent' => $percent, 'active' => true],
            );
        }
    }

    protected function users(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@kebaphouse.test'],
            [
                'name' => 'Owner',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'locale' => 'en',
                'active' => true,
            ],
        );

        User::updateOrCreate(
            ['email' => 'kasir@kebaphouse.test'],
            [
                'name' => 'Cashier',
                'password' => Hash::make('password'),
                'role' => UserRole::Cashier,
                'locale' => 'id',
                'active' => true,
            ],
        );
    }

    protected function menu(): void
    {
        $menu = [
            'Kebab' => ['Kebab', [
                ['Adana Kebab', 'Kebab Adana', 78000],
                ['Urfa Kebab', 'Kebab Urfa', 78000],
                ['Chicken Shish', 'Sate Ayam Turki', 72000],
                ['Lamb Shish', 'Sate Kambing Turki', 95000],
                ['Iskender Kebab', 'Kebab Iskender', 98000],
                ['Doner Wrap', 'Kebab Gulung', 55000],
            ]],
            'Meze' => ['Meze', [
                ['Hummus', 'Hummus', 45000],
                ['Baba Ganoush', 'Baba Ganoush', 48000],
                ['Cacik', 'Cacik', 38000],
                ['Haydari', 'Haydari', 42000],
                ['Ezme', 'Ezme', 38000],
                ['Dolma', 'Dolma', 46000],
                ['Mixed Meze Platter', 'Meze Campur', 135000],
            ]],
            'Pide & Lahmacun' => ['Pide & Lahmacun', [
                ['Lahmacun', 'Lahmacun', 52000],
                ['Cheese Pide', 'Pide Keju', 68000],
                ['Sucuk Pide', 'Pide Sosis Turki', 75000],
            ]],
            'Drinks' => ['Minuman', [
                ['Ayran', 'Ayran', 22000],
                ['Turkish Tea', 'Teh Turki', 18000],
                ['Turkish Coffee', 'Kopi Turki', 32000],
                ['Soft Drink', 'Minuman Ringan', 20000],
                ['Mineral Water', 'Air Mineral', 12000],
            ]],
            'Dessert' => ['Hidangan Penutup', [
                ['Baklava', 'Baklava', 42000],
                ['Kunefe', 'Kunefe', 55000],
                ['Sutlac', 'Puding Beras Turki', 35000],
            ]],
        ];

        $categorySort = 0;

        foreach ($menu as $nameEn => [$nameId, $products]) {
            $category = Category::updateOrCreate(
                ['name_en' => $nameEn],
                ['name_id' => $nameId, 'sort' => $categorySort += 10, 'active' => true],
            );

            $productSort = 0;

            foreach ($products as [$productEn, $productId, $price]) {
                Product::updateOrCreate(
                    ['name_en' => $productEn],
                    [
                        'category_id' => $category->id,
                        'name_id' => $productId,
                        'price' => $price,
                        'sort' => $productSort += 10,
                        'active' => true,
                    ],
                );
            }
        }
    }

    protected function suppliers(): void
    {
        $suppliers = [
            ['Anadolu Meat Supply', 'Fresh lamb & beef', 'Mehmet', '0812-1000-2001'],
            ['Pasar Segar Produce', 'Vegetables & herbs', 'Ibu Sri', '0813-1000-2002'],
            ['Bosphorus Dairy', 'Yoghurt, cheese & milk', 'Dimas', '0814-1000-2003'],
            ['Istanbul Spice House', 'Spices & dry goods', 'Ayse', '0815-1000-2004'],
        ];

        foreach ($suppliers as [$name, $supplies, $contact, $phone]) {
            Supplier::updateOrCreate(
                ['name' => $name],
                ['supplies' => $supplies, 'contact_person' => $contact, 'phone' => $phone, 'active' => true],
            );
        }
    }
}
