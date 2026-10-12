<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The short code behind a texted link.
 *
 * Deliberately separate from `reference_number`: the reference is a business
 * identifier that is printed on PDFs and read aloud, so tying the URL scheme to
 * it would freeze both together. This code exists only to be tapped.
 *
 * @property-read int $id
 * @property-read string $code
 * @property-read string $linkable_type
 * @property-read int $linkable_id
 * @property-read CarbonImmutable|null $last_visited_at
 * @property-read int $visits
 * @property-read CarbonImmutable $created_at
 * @property-read CarbonImmutable $updated_at
 * @property-read Model $linkable
 */
#[Fillable(['code', 'linkable_type', 'linkable_id', 'last_visited_at', 'visits'])]
#[Hidden(['id'])]
#[RouteKey('code')]
final class ShortLink extends Model
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_visited_at' => 'datetime',
        ];
    }
}
