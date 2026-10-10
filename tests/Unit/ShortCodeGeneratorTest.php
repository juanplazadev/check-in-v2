<?php

declare(strict_types=1);

use App\Models\CheckIn;
use App\Models\ShortLink;
use App\ShortLink\ShortCodeGenerator;
use Tests\Support\SequencedRandomCode;

it('mints a seven character code from the unambiguous alphabet', function (): void {
    $code = resolve(ShortCodeGenerator::class)->generate();

    // The redirect route constrains itself to this exact pattern, so a code
    // outside it would be minted and then 404 on the way back in.
    expect($code)->toMatch('/^[A-HJKMNP-Z2-9]{7}$/');
});

it('draws again when the code is already taken', function (): void {
    ShortLink::query()->create([
        'code' => 'QRSTUVW',
        'linkable_type' => 'check_in',
        'linkable_id' => CheckIn::factory()->create()->id,
    ]);

    $code = new ShortCodeGenerator(new SequencedRandomCode(['QRSTUVW', 'XYZ2345']))->generate();

    expect($code)->toBe('XYZ2345');
});
