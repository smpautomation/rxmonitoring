<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Oven;
use App\Models\ProductModel;
use Illuminate\Database\Seeder;

class RxMonitoringSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            ['code' => 'NCP2', 'name' => 'NCP Line 2'],
            ['code' => 'NCP3', 'name' => 'NCP Line 3'],
            ['code' => 'NCP8', 'name' => 'NCP Line 8'],
            ['code' => 'GLASSCLOTH', 'name' => 'Glass Cloth Lamination'],
        ];

        foreach ($areas as $data) {
            Area::updateOrCreate(['code' => $data['code']], [
                'name' => $data['name'],
                'chamber_count' => 10,
                'layer_count' => 20,
                'is_active' => true,
            ]);
        }

        $this->seedRealOvens();
        $this->seedDemoProductModels();
    }

    private function seedRealOvens(): void
    {
        $rows = [
            ['area' => 'NCP3', 'oven_no' => 'RX-06', 'capacity_kg' => null, 'peak_temp_target_c' => null],
            ['area' => 'NCP8', 'oven_no' => 'RX-04', 'capacity_kg' => 205, 'peak_temp_target_c' => 300],
            ['area' => 'NCP8', 'oven_no' => 'RX-07', 'capacity_kg' => 205, 'peak_temp_target_c' => 300],
            ['area' => 'NCP2', 'oven_no' => 'RX-03', 'capacity_kg' => 205, 'peak_temp_target_c' => 300],
            ['area' => 'GLASSCLOTH', 'oven_no' => 'OV-20 (150 °C)', 'capacity_kg' => 500, 'peak_temp_target_c' => 150],
            ['area' => 'GLASSCLOTH', 'oven_no' => 'OV-02 (150 °C)', 'capacity_kg' => 500, 'peak_temp_target_c' => 150],
            ['area' => 'GLASSCLOTH', 'oven_no' => 'OV-30 (150 °C)', 'capacity_kg' => 500, 'peak_temp_target_c' => 150],
            ['area' => 'GLASSCLOTH', 'oven_no' => 'OV-01 (150 °C)', 'capacity_kg' => 500, 'peak_temp_target_c' => 150],
            ['area' => 'GLASSCLOTH', 'oven_no' => 'OV-03 (150 °C)', 'capacity_kg' => 500, 'peak_temp_target_c' => 150],
            ['area' => 'GLASSCLOTH', 'oven_no' => 'OV-03 (140 °C)', 'capacity_kg' => 500, 'peak_temp_target_c' => 140],
            ['area' => 'GLASSCLOTH', 'oven_no' => 'OV-01 (140 °C)', 'capacity_kg' => 500, 'peak_temp_target_c' => 140],
            ['area' => 'GLASSCLOTH', 'oven_no' => 'OV-30 (140 °C)', 'capacity_kg' => 500, 'peak_temp_target_c' => 140],
            ['area' => 'GLASSCLOTH', 'oven_no' => 'OV-02 (140 °C)', 'capacity_kg' => 500, 'peak_temp_target_c' => 140],
            ['area' => 'GLASSCLOTH', 'oven_no' => 'OV-20 (140 °C)', 'capacity_kg' => 500, 'peak_temp_target_c' => 140],
            ['area' => 'GLASSCLOTH', 'oven_no' => 'OV-18 (140 °C)', 'capacity_kg' => 500, 'peak_temp_target_c' => 140],
            ['area' => 'GLASSCLOTH', 'oven_no' => 'OV-15 (140 °C)', 'capacity_kg' => 500, 'peak_temp_target_c' => 140],
            ['area' => 'GLASSCLOTH', 'oven_no' => 'OV-10 (140 °C)', 'capacity_kg' => 500, 'peak_temp_target_c' => 140],
            ['area' => 'GLASSCLOTH', 'oven_no' => 'OV-10 (150 °C)', 'capacity_kg' => 500, 'peak_temp_target_c' => 140], // sic, see note above
            ['area' => 'GLASSCLOTH', 'oven_no' => 'OV-18 (150 °C)', 'capacity_kg' => 500, 'peak_temp_target_c' => 140], // sic, see note above
            ['area' => 'NCP3', 'oven_no' => 'RX-08', 'capacity_kg' => 205, 'peak_temp_target_c' => 300],
            ['area' => 'NCP2', 'oven_no' => 'RX-05', 'capacity_kg' => 205, 'peak_temp_target_c' => 300],
            ['area' => 'NCP2', 'oven_no' => 'RX-09', 'capacity_kg' => 205, 'peak_temp_target_c' => 300],
        ];

        $areaIds = Area::pluck('id', 'code');

        foreach ($rows as $row) {
            Oven::updateOrCreate(
                ['area_id' => $areaIds[$row['area']], 'oven_no' => $row['oven_no']],
                [
                    'capacity_kg' => $row['capacity_kg'],
                    'peak_temp_target_c' => $row['peak_temp_target_c'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedDemoProductModels(): void
    {
        foreach (Area::all() as $area) {
            foreach (['LAM-100', 'LAM-200', 'LAM-300'] as $model) {
                ProductModel::updateOrCreate(
                    ['area_id' => $area->id, 'model_name' => $model],
                    ['unit_weight_grams' => 850]
                );
            }
        }
    }

}
