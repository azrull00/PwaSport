<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\UserPreferredArea;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserPreferredArea>
 */
class UserPreferredAreaFactory extends Factory
{
    protected $model = UserPreferredArea::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cities = [
            ['name' => 'Jakarta Selatan', 'lat' => -6.2297, 'lon' => 106.8176, 'district' => 'Senayan'],
            ['name' => 'Jakarta Pusat', 'lat' => -6.1863, 'lon' => 106.8230, 'district' => 'Menteng'],
            ['name' => 'Jakarta Utara', 'lat' => -6.1389, 'lon' => 106.8639, 'district' => 'Kelapa Gading'],
            ['name' => 'Bandung', 'lat' => -6.9175, 'lon' => 107.6191, 'district' => 'Dago'],
            ['name' => 'Surabaya', 'lat' => -7.2756, 'lon' => 112.7503, 'district' => 'Gubeng'],
            ['name' => 'Yogyakarta', 'lat' => -7.7956, 'lon' => 110.3695, 'district' => 'Malioboro'],
            ['name' => 'Semarang', 'lat' => -6.9666, 'lon' => 110.4167, 'district' => 'Simpang Lima'],
            ['name' => 'Medan', 'lat' => 3.5952, 'lon' => 98.6722, 'district' => 'Medan Baru'],
        ];

        $city = $this->faker->randomElement($cities);

        return [
            'user_id' => User::factory(),
            'area_name' => $city['name'] . ' - ' . $this->faker->randomElement(['Center', 'Mall Area', 'Sport Center', 'Residential']),
            'center_latitude' => $city['lat'] + $this->faker->randomFloat(4, -0.05, 0.05), // Add some variance
            'center_longitude' => $city['lon'] + $this->faker->randomFloat(4, -0.05, 0.05),
            'radius_km' => $this->faker->numberBetween(5, 25),
            'address' => $this->faker->address(),
            'city' => $city['name'],
            'district' => $city['district'],
            'province' => $this->getProvinceForCity($city['name']),
            'country' => 'Indonesia',
            'is_active' => $this->faker->boolean(90), // 90% chance to be active
            'priority_order' => $this->faker->numberBetween(1, 5),
        ];
    }

    private function getProvinceForCity($cityName)
    {
        $provinceMap = [
            'Jakarta Selatan' => 'DKI Jakarta',
            'Jakarta Pusat' => 'DKI Jakarta',
            'Jakarta Utara' => 'DKI Jakarta',
            'Bandung' => 'Jawa Barat',
            'Surabaya' => 'Jawa Timur',
            'Yogyakarta' => 'DI Yogyakarta',
            'Semarang' => 'Jawa Tengah',
            'Medan' => 'Sumatera Utara',
        ];

        return $provinceMap[$cityName] ?? 'DKI Jakarta';
    }
}
