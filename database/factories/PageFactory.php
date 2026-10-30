<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(2, true);

        return [
            'title' => Str::title($title),
            'slug' => Str::slug($title),
            'body' => '<p>'.fake()->paragraph().'</p>',
        ];
    }

    /**
     * A body laid out with Nova's Flexible Content rather than raw HTML.
     */
    public function flexible(): static
    {
        return $this->state(fn (): array => [
            'body' => json_encode([[
                'layout' => 'copy',
                'key' => 'c'.substr(bin2hex(random_bytes(8)), 0, 15),
                'attributes' => ['text' => '<p>'.fake()->paragraph().'</p>'],
            ]]),
        ]);
    }
}
