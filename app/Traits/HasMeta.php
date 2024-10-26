<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasMeta
{
    public function updateMeta(array $keys, array $values): void
    {
        $meta = $this->meta;

        foreach (array_combine($keys, $values) as $key => $value) {
            if (empty($key)) {
                continue;
            }

            if (empty($value)) {
                unset($meta[$key]);
            } elseif (Str::isJson($value)) {
                // *Every* meta field is saved as an array, even if it's single-value.
                $meta[$key] = (array) json_decode($value, true);
            } else {
                $meta[$key] = (array) $value;
            }
        }

        $this->updateQuietly(['meta' => $meta]);
    }

    public function deleteMeta(string $key): void
    {
        $meta = $this->meta;
        unset($meta[$key]);

        $this->updateQuietly(['meta' => $meta]);
    }
}
