<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VehicleSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        $vehicles = [
            [
                'VehicleModel'      => 'Toyota Hilux 2023',
                'VehicleType'       => 'light',
                'VehicleLicense'    => 'ABC-1234',
                'MaxWeightCapacity' => 1000.00,
                'Status'            => 'Active',
                'Current_odometer'  => 12500.50,
                'MaxVolume'         => 5.00,
                'MarketValue'       => 85000,
                'CreatedAt'         => $now,
                'UpdatedAt'         => $now,
            ],
            [
                'VehicleModel'      => 'Isuzu NPR 2022',
                'VehicleType'       => 'heavy',
                'VehicleLicense'    => 'XYZ-9876',
                'MaxWeightCapacity' => 4500.00,
                'Status'            => 'Active',
                'Current_odometer'  => 34200.00,
                'MaxVolume'         => 15.50,
                'MarketValue'       => 150000,
                'CreatedAt'         => $now,
                'UpdatedAt'         => $now,
            ],
            [
                'VehicleModel'      => 'Mercedes-Benz Sprinter',
                'VehicleType'       => 'refrigerated',
                'VehicleLicense'    => 'RTY-5544',
                'MaxWeightCapacity' => 2500.00,
                'Status'            => 'Maintenance',
                'Current_odometer'  => 85000.75,
                'MaxVolume'         => 12.00,
                'MarketValue'       => 120000,
                'CreatedAt'         => $now,
                'UpdatedAt'         => $now,
            ],
            [
                'VehicleModel'      => 'Ford Transit 2021',
                'VehicleType'       => 'light',
                'VehicleLicense'    => 'LMN-1122',
                'MaxWeightCapacity' => 1200.00,
                'Status'            => 'Active',
                'Current_odometer'  => 45000.25,
                'MaxVolume'         => 6.50,
                'MarketValue'       => 75000,
                'CreatedAt'         => $now,
                'UpdatedAt'         => $now,
            ],
            [
                'VehicleModel'      => 'Volvo FH16',
                'VehicleType'       => 'heavy',
                'VehicleLicense'    => 'TRK-9988',
                'MaxWeightCapacity' => 18000.00,
                'Status'            => 'Active',
                'Current_odometer'  => 150000.00,
                'MaxVolume'         => 33.00,
                'MarketValue'       => 350000,
                'CreatedAt'         => $now,
                'UpdatedAt'         => $now,
            ],
        ];

        DB::table('vehicles')->insert($vehicles);
    }
}
