<?php

declare(strict_types=1);

namespace Noria\Keel\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Noria\Keel\Db\KeyType;

trait HasConfiguredKey
{
    use HasUniqueStringIds;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return KeyType::forModels()->generates() ? [$this->getKeyName()] : [];
    }

    public function newUniqueId(): string
    {
        return KeyType::forModels()->newId();
    }

    protected function isValidUniqueId(mixed $value): bool
    {
        return KeyType::forModels()->accepts($value);
    }
}
