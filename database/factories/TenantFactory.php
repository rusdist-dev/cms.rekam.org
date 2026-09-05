<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $slug = Str::slug($this->faker->unique()->words(2, true));

        return [
            'name' => Str::headline($slug),
            'slug' => $slug,
            'db_name' => Tenant::databaseNameFor($slug),
            'domain' => $slug.'.test',
            'features' => collect(config('cms.features'))->map(fn ($f) => (bool) $f['default'])->all(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function withFeatures(array $features): static
    {
        return $this->state(fn () => ['features' => $features]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
