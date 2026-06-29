<?php

declare(strict_types=1);

namespace App\Enums;

enum BlokSection: string
{
    case HEADER = 'header';
    case BODY = 'body';
    case FOOTER = 'footer';
    case SIDEBAR = 'sidebar';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
