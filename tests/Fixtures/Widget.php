<?php

declare(strict_types=1);

namespace Noria\Keel\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Noria\Keel\Concerns\BelongsToWorkspace;

class Widget extends Model
{
    use BelongsToWorkspace;
    use HasUuids;

    protected $table = 'widgets';

    protected $guarded = ['id'];

    public $timestamps = false;
}
