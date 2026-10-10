<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<PurchaseOrder>
 */
final class PurchaseOrderFactory extends Factory
{
    #[Override]
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'number' => mb_strtoupper(fake()->bothify('PO-#####')),
        ];
    }
}
