<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 1. إنشاء المنظمات
        $pharmacy = Organization::create(['name' => 'صيدلية الشفاء']);
        $supplier = Organization::create(['name' => 'مستودع الأدوية المركزي']);

        // 2. إنشاء المستخدمين
        User::create([
            'name' => 'صيدلي 1',
            'email' => 'pharmacy@test.com',
            'password' => Hash::make('pharmacy@test.com'),
            'role' => 'pharmacy',
            'organization_id' => $pharmacy->id,
        ]);

        User::create([
            'name' => 'مورد 1',
            'email' => 'supplier@test.com',
            'password' => Hash::make('12345678'),
            'role' => 'supplier',
            'organization_id' => $supplier->id,
        ]);

        // 3. إنشاء منتج تجريبي
        Product::create([
            'supplier_id' => $supplier->id,
            'name' => 'Panadol Extra',
            'price' => 15.50,
            'company' => 'GSK',
            'category' => 'مسكنات',
            'form' => 'حبوب'
        ]);
    }
}