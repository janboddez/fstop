<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Actor extends Model
{
    /**
     * @var array<int, string>
     *
     * @todo Move most actor meta (inbox, public key, etc.) over to the main table.
     */
    protected $fillable = [
        'url',
    ];

    /**
     * @var array<int, string>
     */
    protected $with = ['meta'];

    public function meta(): MorphMany
    {
        return $this->morphMany(Meta::class, 'metable');
    }

    /**
     * The users followed by this actor.
     */
    // public function users(): BelongsToMany
    public function following(): BelongsToMany
    {
        return $this->BelongsToMany(User::class, 'follower_user', 'actor_id', 'user_id');
    }

    // /**
    //  * This actor's followers.
    //  */
    // public function followers(): BelongsToMany
    // {
    //     return $this->BelongsToMany(User::class, 'following_user', 'actor_id', 'user_id');
    // }

    // protected function handle(): Attribute
    // {
    //     return Attribute::make(
    //         get: function () {
    //             $username = ($meta = $this->meta->firstWhere('key', 'username'))
    //                 ? strip_tags($meta->value[0])
    //                 : null;

    //             if ($username && strpos($username, '@') === false) {
    //                 /** @todo This isn't always correct. */
    //                 $handle = '@' . $username . '@' . parse_url($this->id, PHP_URL_HOST);
    //             }

    //             return $handle ?? null;
    //         }
    //     );
    // }

    protected function inbox(): Attribute
    {
        return Attribute::make(
            get: fn () => ($meta = $this->meta->firstWhere('key', 'inbox'))
                ? filter_var($meta->value[0], FILTER_SANITIZE_URL)
                : null
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

    protected function publicKey(): Attribute
    {
        return Attribute::make(
            get: fn () => ($meta = $this->meta->firstWhere('key', 'public_key'))
                ? $meta->value[0]
                : null
        );
    }

    protected function sharedInbox(): Attribute
    {
        return Attribute::make(
            get: fn () => ($meta = $this->meta->firstWhere('key', 'shared_inbox'))
                ? filter_var($meta->value[0], FILTER_SANITIZE_URL)
                : $this->inbox // Fall back to their "normal" inbox.
        )->shouldCache();
    }

    /**
     * The URL to an actor's profile page, which may be different from their "ID."
     */
    protected function profile(): Attribute
    {
        return Attribute::make(
            get: fn () => ($meta = $this->meta->firstWhere('key', 'url'))
                ? filter_var($meta->value[0], FILTER_SANITIZE_URL)
                : $this->url // Fall back to their "actor ID."
        )->shouldCache();
    }
}
