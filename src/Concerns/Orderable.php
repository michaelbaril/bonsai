<?php

namespace Baril\Bonsai\Concerns;

use Baril\Orderly\Concerns\Orderable as OrderlyOrderable;

trait Orderable
{
    use OrderlyOrderable;

    /**
     * @return string
     */
    public function getGroupColumn()
    {
        return $this->getParentForeignKeyName();
    }
}
