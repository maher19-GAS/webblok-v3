<?php

declare(strict_types=1);

namespace App\Actions\Bloks;

use App\Models\Tenant\BlokInstance;

final class DeleteBlokInstance
{
    public function execute(string $instanceId): void
    {
        /** @var BlokInstance $instance */
        $instance = BlokInstance::query()->findOrFail($instanceId);

        // Cascade is enforced by the FK (ON DELETE CASCADE) for children.
        $instance->delete();
    }
}
