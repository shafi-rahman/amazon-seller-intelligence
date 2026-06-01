<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportBatch>
 */
class ImportBatchFactory extends Factory
{
    protected $model = ImportBatch::class;

    public function definition(): array
    {
        return [
            'workspace_id'      => Workspace::factory(),
            'user_id'           => User::factory(),
            'type'              => fake()->randomElement(['orders', 'settlements', 'bank_statement', 'gst_report', 'products']),
            'original_filename' => fake()->word() . '.csv',
            'status'            => 'completed',
            'total_rows'        => fake()->numberBetween(10, 5000),
            'processed_rows'    => fn(array $a) => $a['total_rows'],
            'failed_rows'       => 0,
            'meta'              => [],
        ];
    }
}
