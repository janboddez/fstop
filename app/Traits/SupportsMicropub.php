<?php

namespace App\Traits;

use Illuminate\Support\Arr;

trait SupportsMicropub
{
    public static $supportedProperties = [
        'name',
        'content',
        'summary',
        'published',
        'status',
        'visibility',
        'meta' => [
            'mp-syndicate-to',
            'in-reply-to',
            'like-of',
            'repost-of',
            'bookmark-of',
            'photo',
            'featured',
            'geo',
        ],
        'category',
    ];

    public function getCategoryAttribute(): array
    {
        return $this->tags
            ->pluck('name')
            ->toArray();
    }

    public function getCategoryIdsAttribute(): array
    {
        return $this->tags
            ->pluck('id')
            ->toArray();
    }

    public function getProperties(array $properties = []): array
    {
        $properties = array_filter($properties);

        if (empty($properties)) {
            $properties = static::$supportedProperties;
        } else {
            $properties = array_intersect($properties, static::$supportedProperties);
        }

        $properties = Arr::flatten($properties);

        $value = [];

        foreach ($properties as $property) {
            if ($property === 'published') {
                // Prevent Carbon from casting to the default format.
                $value[$property] = (array) $this->published->format('c');
            } else {
                $value[$property] = (array) $this->$property;
            }
        }

        return array_filter($value);
    }

    /**
     * @todo: Really need to tackle this differently.
     */
    public static function validationRules(): array
    {
        $rules = [
            'action' => 'in:create,update,delete,undelete',
            'url' => 'url',
        ];

        foreach (['properties', 'replace'] as $array) {
            $rules["$array.*"] = 'nullable|array'; // Each array item should be an array, too!

            // These items are single-value, always.
            $rules["$array.name.0"] = 'nullable|string|max:255';

            $rules["$array.slug.0"] = 'nullable|string|max:255';
            $rules["$array.mp-slug.0"] = 'nullable|string|max:255';

            $rules["$array.published.0"] = 'nullable|date';
            $rules["$array.post-status.0"] = 'nullable|in:published,draft';
            $rules["$array.visibility.0"] = 'nullable|in:public,unlisted,private';

            $rules["$array.latitude.0"] = 'nullable|numeric';
            $rules["$array.longitude.0"] = 'nullable|numeric';

            // These can have mulitple values.
            $rules["$array.category.*"] = 'nullable|string|max:255';
            $rules["$array.mp-syndicate-to.*"] = 'nullable|url|max:255';
            $rules["$array.in-reply-to.*"] = 'nullable|url|max:255';
            $rules["$array.like-of.*"] = 'nullable|url|max:255';
            $rules["$array.repost-of.*"] = 'nullable|url|max:255';

            // For these, it depends.
            foreach (['photo', 'featured'] as $property) {
                $rules["$array.$property.0"] = "nullable|url|max:255";
                $rules["$array.$property.value"] = 'nullable|url|max:255';
                $rules["$array.$property.alt"] = 'nullable|string|max:255';
            }

            // $rules["$array.featured.*"] = 'array:value,alt';
            // $rules["$array.video.*"] = 'array:value,alt';
            // $rules["$array.audio.*"] = 'array:value,alt';
        }

        return $rules;
    }
}
