<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $blok_instance_id
 * @property string $page_id
 * @property string $locale
 * @property array<string, mixed> $data
 * @property string|null $ip_address
 * @property bool $is_read
 * @property Carbon $submitted_at
 */
final class FormSubmission extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $connection = 'tenant';

    protected $table = 'form_submissions';

    /** @var array<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_read' => 'boolean',
            'submitted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
