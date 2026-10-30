<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether a load type brings freight into the warehouse or takes it out.
 */
enum LoadDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';

    /**
     * In the current locale, so driver pages show it in the driver's language.
     */
    public function label(): string
    {
        return __('messages.loadDirection.'.$this->value);
    }
}
