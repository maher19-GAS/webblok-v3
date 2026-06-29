<?php

declare(strict_types=1);

namespace App\Actions\Bloks;

use Illuminate\Support\Facades\DB;

final class ReorderBlokInstances
{
    /**
     * @param  list<string>  $orderedIds
     */
    public function execute(array $orderedIds): void
    {
        DB::connection('tenant')->transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $position => $id) {
                DB::connection('tenant')->table('blok_instances')
                    ->where('id', $id)
                    ->update(['sort_order' => $position, 'updated_at' => now()->toISOString()]);
            }
        });
    }
}
