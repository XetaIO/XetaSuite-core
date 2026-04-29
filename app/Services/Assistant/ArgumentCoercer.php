<?php

declare(strict_types=1);

namespace XetaSuite\Services\Assistant;

class ArgumentCoercer
{
    /**
     * Coerce string booleans produced by some LLMs ("true"/"false") to native booleans.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function coerce(array $args): array
    {
        return array_map(function (mixed $value): mixed {
            if ($value === 'true') {
                return true;
            }

            if ($value === 'false') {
                return false;
            }

            return $value;
        }, $args);
    }
}
