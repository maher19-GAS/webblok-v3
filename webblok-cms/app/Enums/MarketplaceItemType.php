<?php

declare(strict_types=1);

namespace App\Enums;

enum MarketplaceItemType: string
{
    case TEMPLATE = 'template';
    case THEME = 'theme';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
