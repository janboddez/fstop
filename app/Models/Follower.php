<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Follower extends Model
{
    protected $fillable = [
        'url',
    ];

    /**
     * Always autoload meta.
     *
     * @var array
     */
    protected $with = ['meta'];

    public function meta(): MorphMany
    {
        return $this->morphMany(Meta::class, 'metable');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function inbox(): Attribute
    {
        return Attribute::make(
            get: fn () => ($meta = $this->meta->firstWhere('key', 'inbox'))
                ? filter_var($meta->value[0], FILTER_SANITIZE_URL)
                : null
        )->shouldCache();
    }

    protected function sharedInbox(): Attribute
    {
        return Attribute::make(
            get: fn () => ($meta = $this->meta->firstWhere('key', 'shared_inbox'))
                ? filter_var($meta->value[0], FILTER_SANITIZE_URL)
                : $this->inbox // Fall back to their "normal" inbox.
        )->shouldCache();
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => ($meta = $this->meta->firstWhere('key', 'name'))
                ? strip_tags($meta->value[0])
                : null
        );
    }

    protected function handle(): Attribute
    {
        return Attribute::make(
            get: function () {
                $username = ($meta = $this->meta->firstWhere('key', 'username'))
                    ? strip_tags($meta->value[0])
                    : null;

                if ($username && strpos($username, '@') === false) {
                    /** @todo This isn't always correct. */
                    $handle = '@' . $username . '@' . parse_url($this->id, PHP_URL_HOST);
                }

                return $handle ?? null;
            }
        );
    }

    protected function publicKey(): Attribute
    {
        return Attribute::make(
            get: fn () => ($meta = $this->meta->firstWhere('key', 'public_key'))
                ? $meta->value[0]
                : null
        );
    }
}
