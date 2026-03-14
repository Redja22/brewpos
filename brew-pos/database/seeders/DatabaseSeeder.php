<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use App\Models\Table;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Roles ---
        $roles = ['admin', 'manager', 'cashier', 'kitchen'];
        foreach ($roles as $role) {
            Role::findOrCreate($role);
        }

        // --- Users ---
        $admin = User::create([
            'name'      => 'Admin User',
            'email'     => 'admin@brewpos.com',
            'password'  => Hash::make('password'),
            'role'      => 'admin',
            'is_active' => true,
        ]);
        $manager = User::create([
            'name'      => 'Manager',
            'email'     => 'manager@brewpos.com',
            'password'  => Hash::make('password'),
            'role'      => 'manager',
            'is_active' => true,
        ]);
        $cashier = User::create([
            'name'      => 'Cashier',
            'email'     => 'cashier@brewpos.com',
            'password'  => Hash::make('password'),
            'role'      => 'cashier',
            'is_active' => true,
        ]);
        $kitchen = User::create([
            'name'      => 'Kitchen Staff',
            'email'     => 'kitchen@brewpos.com',
            'password'  => Hash::make('password'),
            'role'      => 'kitchen',
            'is_active' => true,
        ]);

        $admin?->assignRole('admin');
        $manager?->assignRole('manager');
        $cashier?->assignRole('cashier');
        $kitchen?->assignRole('kitchen');

        // --- Categories ---
        $categories = [
            ['name' => 'Hot Coffee',    'icon' => '☕', 'color' => '#8B5E3C', 'sort_order' => 1],
            ['name' => 'Cold Coffee',   'icon' => '🧊', 'color' => '#4A90D9', 'sort_order' => 2],
            ['name' => 'Milk Tea',      'icon' => '🧋', 'color' => '#C69C6D', 'sort_order' => 3],
            ['name' => 'Frappe',        'icon' => '🥤', 'color' => '#9B59B6', 'sort_order' => 4],
            ['name' => 'Pastries',      'icon' => '🥐', 'color' => '#E67E22', 'sort_order' => 5],
            ['name' => 'Snacks',        'icon' => '🍪', 'color' => '#27AE60', 'sort_order' => 6],
            ['name' => 'Non-Coffee',    'icon' => '🍵', 'color' => '#16A085', 'sort_order' => 7],
        ];

        $createdCategories = [];
        foreach ($categories as $cat) {
            $slug = \Illuminate\Support\Str::slug($cat['name']);
            $createdCategories[$cat['name']] = Category::create(array_merge($cat, ['slug' => $slug, 'is_active' => true]));
        }

        // --- Products ---
        $products = [
            // Hot Coffee
            ['category' => 'Hot Coffee', 'name' => 'Americano',          'price' => 100],
            ['category' => 'Hot Coffee', 'name' => 'Cappuccino',         'price' => 130],
            ['category' => 'Hot Coffee', 'name' => 'Caffe Latte',        'price' => 140],
            ['category' => 'Hot Coffee', 'name' => 'Flat White',         'price' => 150],
            ['category' => 'Hot Coffee', 'name' => 'Espresso',           'price' => 90],
            ['category' => 'Hot Coffee', 'name' => 'Macchiato',          'price' => 110],
            // Cold Coffee
            ['category' => 'Cold Coffee', 'name' => 'Iced Americano',   'price' => 110],
            ['category' => 'Cold Coffee', 'name' => 'Iced Latte',       'price' => 150],
            ['category' => 'Cold Coffee', 'name' => 'Cold Brew',        'price' => 160],
            ['category' => 'Cold Coffee', 'name' => 'Iced Mocha',       'price' => 165],
            // Milk Tea
            ['category' => 'Milk Tea', 'name' => 'Classic Milk Tea',    'price' => 120],
            ['category' => 'Milk Tea', 'name' => 'Taro Milk Tea',       'price' => 135],
            ['category' => 'Milk Tea', 'name' => 'Matcha Latte',        'price' => 145],
            ['category' => 'Milk Tea', 'name' => 'Brown Sugar Milk Tea','price' => 150],
            // Frappe
            ['category' => 'Frappe', 'name' => 'Mocha Frappe',          'price' => 155],
            ['category' => 'Frappe', 'name' => 'Caramel Frappe',        'price' => 155],
            ['category' => 'Frappe', 'name' => 'Cookies & Cream Frappe','price' => 160],
            ['category' => 'Frappe', 'name' => 'Matcha Frappe',         'price' => 155],
            // Pastries
            ['category' => 'Pastries', 'name' => 'Croissant',           'price' => 75],
            ['category' => 'Pastries', 'name' => 'Blueberry Muffin',    'price' => 80],
            ['category' => 'Pastries', 'name' => 'Cinnamon Roll',       'price' => 90],
            ['category' => 'Pastries', 'name' => 'Banana Bread',        'price' => 85],
            // Snacks
            ['category' => 'Snacks', 'name' => 'Chocolate Chip Cookie', 'price' => 60],
            ['category' => 'Snacks', 'name' => 'Cheese Pandesal',       'price' => 45],
            ['category' => 'Snacks', 'name' => 'Club Sandwich',         'price' => 130],
            // Non-Coffee
            ['category' => 'Non-Coffee', 'name' => 'Hot Chocolate',     'price' => 110],
            ['category' => 'Non-Coffee', 'name' => 'Chamomile Tea',     'price' => 95],
            ['category' => 'Non-Coffee', 'name' => 'Lemonade',          'price' => 100],
        ];

        foreach ($products as $i => $p) {
            $category = $createdCategories[$p['category']];
            $product  = Product::create([
                'category_id'     => $category->id,
                'name'            => $p['name'],
                'slug'            => \Illuminate\Support\Str::slug($p['name']) . '-' . ($i + 1),
                'price'           => $p['price'],
                'is_active'       => true,
                'is_available'    => true,
                'track_inventory' => true,
                'sort_order'      => $i,
            ]);
            Inventory::create([
                'product_id'          => $product->id,
                'quantity'            => rand(20, 100),
                'low_stock_threshold' => 10,
                'unit'                => 'pcs',
            ]);
        }

        // --- Tables ---
        $tableLayouts = [
            ['name' => 'Table 1',  'number' => 1,  'capacity' => 2, 'position_x' => 50,  'position_y' => 50,  'shape' => 'circle'],
            ['name' => 'Table 2',  'number' => 2,  'capacity' => 2, 'position_x' => 200, 'position_y' => 50,  'shape' => 'circle'],
            ['name' => 'Table 3',  'number' => 3,  'capacity' => 4, 'position_x' => 350, 'position_y' => 50,  'shape' => 'square'],
            ['name' => 'Table 4',  'number' => 4,  'capacity' => 4, 'position_x' => 500, 'position_y' => 50,  'shape' => 'square'],
            ['name' => 'Table 5',  'number' => 5,  'capacity' => 6, 'position_x' => 50,  'position_y' => 220, 'shape' => 'rectangle'],
            ['name' => 'Table 6',  'number' => 6,  'capacity' => 6, 'position_x' => 300, 'position_y' => 220, 'shape' => 'rectangle'],
            ['name' => 'Table 7',  'number' => 7,  'capacity' => 4, 'position_x' => 550, 'position_y' => 220, 'shape' => 'square'],
            ['name' => 'Table 8',  'number' => 8,  'capacity' => 2, 'position_x' => 50,  'position_y' => 400, 'shape' => 'circle'],
            ['name' => 'Table 9',  'number' => 9,  'capacity' => 2, 'position_x' => 200, 'position_y' => 400, 'shape' => 'circle'],
            ['name' => 'Table 10', 'number' => 10, 'capacity' => 8, 'position_x' => 400, 'position_y' => 400, 'shape' => 'rectangle'],
        ];

        foreach ($tableLayouts as $t) {
            Table::create(array_merge($t, ['status' => 'available']));
        }

        // --- Sample Orders (for testing the Orders page) ---
        $cashierUser = $cashier ?? User::where('role', 'cashier')->first();
        if ($cashierUser) {
            $sampleProducts = Product::take(3)->get();
            if ($sampleProducts->count() >= 2) {
                $order = Order::create([
                    'cashier_id'      => $cashierUser->id,
                    'order_type'      => 'dine_in',
                    'status'          => 'pending',
                    'subtotal'        => $sampleProducts->sum('price'),
                    'tax_amount'      => round($sampleProducts->sum('price') * 0.12, 2),
                    'discount_amount' => 0,
                    'total_amount'    => round($sampleProducts->sum('price') * 1.12, 2),
                    'customer_name'   => 'Walk-in',
                ]);

                foreach ($sampleProducts as $p) {
                    OrderItem::create([
                        'order_id'      => $order->id,
                        'product_id'    => $p->id,
                        'product_name'  => $p->name,
                        'product_price' => $p->price,
                        'quantity'      => 1,
                        'subtotal'      => $p->price,
                    ]);
                }
            }
        }
    }
}
