<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Modules\Maintenance\Models\SparePart;

class SparePartSeeder extends Seeder
{
    public function run(): void
    {
        $parts = [
            [
                'name' => 'Synthetic Engine Oil 5W-40',
                'sku' => 'OIL-5W40-001',
                'category' => 'oil',
                'unit_price' => 450.00,
                'stock_quantity' => 150,
                'minimum_stock' => 50,
                'reorder_level' => 300,
                'supplier_name' => 'TotalEnergies',
                'supplier_lead_days' => 3,
                'unit' => 'liter',
                'description' => 'Warehouse A, Row 1, Shelf B',
            ],
            [
                'name' => 'Heavy Duty Brake Pads (Front)',
                'sku' => 'BRK-FR-001',
                'category' => 'brake',
                'unit_price' => 850.00,
                'stock_quantity' => 15,    // Low Stock Alert
                'minimum_stock' => 20,
                'reorder_level' => 100,
                'supplier_name' => 'Brembo',
                'supplier_lead_days' => 5,
                'unit' => 'pcs',
                'description' => 'Warehouse A, Row 2, Shelf A',
            ],
            [
                'name' => 'Commercial Truck Tire 295/80R22.5',
                'sku' => 'TIR-HD-295',
                'category' => 'tire',
                'unit_price' => 8500.00,
                'stock_quantity' => 8,     // Low Stock Alert
                'minimum_stock' => 10,
                'reorder_level' => 40,
                'supplier_name' => 'Michelin',
                'supplier_lead_days' => 7,
                'unit' => 'pcs',
                'description' => 'Warehouse B, Tire Rack 1',
            ],
            [
                'name' => 'Premium Air Filter Element',
                'sku' => 'FLT-AIR-001',
                'category' => 'filter',
                'unit_price' => 320.00,
                'stock_quantity' => 0,     // Out of Stock
                'minimum_stock' => 15,
                'reorder_level' => 50,
                'supplier_name' => 'Bosch',
                'supplier_lead_days' => 2,
                'unit' => 'pcs',
                'description' => 'Warehouse A, Row 3, Shelf C',
            ],
            [
                'name' => '12V 200AH Commercial Battery',
                'sku' => 'BAT-12V-200AH',
                'category' => 'battery',
                'unit_price' => 4200.00,
                'stock_quantity' => 25,
                'minimum_stock' => 5,
                'reorder_level' => 30,
                'supplier_name' => 'VARTA',
                'supplier_lead_days' => 4,
                'unit' => 'pcs',
                'description' => 'Warehouse B, Battery Rack',
            ],
            [
                'name' => 'Headlight Halogen Bulb H7',
                'sku' => 'BLB-H7-001',
                'category' => 'bulb',
                'unit_price' => 150.00,
                'stock_quantity' => 120,
                'minimum_stock' => 30,
                'reorder_level' => 200,
                'supplier_name' => 'Philips',
                'supplier_lead_days' => 2,
                'unit' => 'pcs',
                'description' => 'Warehouse A, Row 1, Shelf A',
            ],
        ];

        foreach ($parts as $part) {
            SparePart::create($part);
        }
    }
}