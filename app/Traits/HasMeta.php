<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasMeta
{
    public function updateMeta(array $keys, array $values): void
    {
        $meta = array_merge(
            $this->meta ?? [],
            static::prepareMeta($keys, $values)
        );

        $this->updateQuietly(['meta' => $meta]);
    }

    public function deleteMeta(string $key): void
    {
        $meta = $this->meta;
        unset($meta[$key]);

        $this->updateQuietly(['meta' => $meta]);
    }

    public static function prepareMeta(array $keys, array $values): array
    {
        $temp = [];

        foreach (array_combine($keys, $values) as $key => $value) {
            if (empty($key)) {
                continue;
            }

            if (empty($value)) {
                unset($temp[$key]);
            } elseif (Str::isJson($value)) {
                // *Every* meta field is saved as an array, even if it's single-value.
                $temp[$key] = (array) json_decode($value, true);
            } else {
                $temp[$key] = (array) $value;
            }
        }

        return $temp;
    }
}
