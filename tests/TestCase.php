<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Owner;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function admin(array $attributes = []): User
    {
        return $this->makeUser(UserRole::Admin, $attributes);
    }

    protected function cashier(array $attributes = []): User
    {
        return $this->makeUser(UserRole::Cashier, $attributes);
    }

    protected function makeUser(UserRole $role, array $attributes = []): User
    {
        static $sequence = 0;
        $sequence++;

        return User::create([
            'name' => ucfirst($role->value).' '.$sequence,
            'email' => $role->value.$sequence.'@kebaphouse.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'locale' => 'en',
            'active' => true,
            ...$attributes,
        ]);
    }

    /** Aslan 60 / Leo 40, kesepakatan yang dipakai di seluruh tes pembagian. */
    protected function owners(): array
    {
        return [
            Owner::create(['name' => 'Aslan', 'share_percent' => 60, 'active' => true]),
            Owner::create(['name' => 'Leo', 'share_percent' => 40, 'active' => true]),
        ];
    }

    protected function product(int $price = 50000, string $name = 'Adana Kebab'): Product
    {
        $category = Category::firstOrCreate(
            ['name_en' => 'Kebab'],
            ['name_id' => 'Kebab', 'sort' => 10, 'active' => true],
        );

        return Product::create([
            'category_id' => $category->id,
            'name_en' => $name,
            'price' => $price,
            'active' => true,
        ]);
    }
}
