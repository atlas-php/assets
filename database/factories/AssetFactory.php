<?php

declare(strict_types=1);

namespace Atlas\Assets\Database\Factories;

use Atlas\Assets\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Class AssetFactory
 *
 * Generates asset records for automated testing scenarios.
 * PRD Reference: Atlas Assets Overview — Database Schema.
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        $fileName = $this->faker->unique()->word.'.'.$this->faker->fileExtension();

        return [
            'user_id' => null,
            'group_id' => null,
            'model_type' => null,
            'model_id' => null,
            'type' => $this->faker->optional()->randomElement(['hero', 'gallery', 'document']),
            'sort_order' => 0,
            'file_mime_type' => $this->faker->mimeType(),
            'file_ext' => strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION)),
            'file_path' => $this->faker->lexify('assets/'.Str::uuid().'/'.$fileName),
            'file_size' => $this->faker->numberBetween(1_024, 5_242_880),
            'name' => $fileName,
            'original_file_name' => $fileName,
            'label' => $this->faker->optional()->word(),
            'category' => $this->faker->optional()->word(),
        ];
    }
}
