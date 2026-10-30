<?php

namespace Database\Factories;

use App\Models\Advert;
use App\Models\AdvertEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdvertEvent>
 */
class AdvertEventFactory extends Factory
{
    protected $model = AdvertEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'advert_id' => Advert::factory(),
            'type' => 'impression',
            'page' => '/',
            'created_at' => now(),
        ];
    }
}
