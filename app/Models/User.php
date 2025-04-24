<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use TorMorten\Eventy\Facades\Events as Eventy;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'url',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var array<int, string>
     */
    protected $with = ['meta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function followers(): BelongsToMany
    {
        return $this->BelongsToMany(Actor::class, 'follower_user', 'user_id', 'actor_id')
            ->withTimestamps();
    }

    // /**
    //  * The actors followed by this user.
    //  */
    // public function following(): BelongsToMany
    // {
    //     return $this->BelongsToMany(Actor::class, 'following_user', 'user_id', 'actor_id');
    // }

    public function meta(): MorphMany
    {
        return $this->morphMany(Meta::class, 'metable');
    }

    protected function actorUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => Eventy::filter('activitypub:actor_url', url('@' . $this->login))
        );
    }

    protected function authorUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => route('users.show', $this)
        );
    }

    protected function avatar(): Attribute
    {
        return Attribute::make(
            get: fn () => ($meta = $this->meta->firstWhere('key', 'avatar'))
                ? $meta->value[0]
                : null
        )->shouldCache();
    }

    protected function bio(): Attribute
    {
        return Attribute::make(
            get: fn () => ($meta = $this->meta->firstWhere('key', 'bio'))
                ? $meta->value[0]
                : null
        )->shouldCache();
    }

    protected function inbox(): Attribute
    {
        return Attribute::make(
            get: fn () => route('activitypub.inbox', $this)
        );
    }

    protected function outbox(): Attribute
    {
        return Attribute::make(
            get: fn () => route('activitypub.outbox', $this)
        );
    }

    protected function privateKey(): Attribute
    {
        return Attribute::make(
            get: fn () => ($meta = $this->meta->firstWhere('key', 'private_key'))
                ? $meta->value[0]
                : null
        );
    }

    protected function publicKey(): Attribute
    {
        return Attribute::make(
            get: fn () => ($meta = $this->meta->firstWhere('key', 'public_key'))
                ? $meta->value[0]
                : null
        )->shouldCache();
    }

    public function serialize(): array
    {
        $output = [
            '@context' => [
                'https://www.w3.org/ns/activitystreams',
                'https://w3id.org/security/v1',
                [
                    'manuallyApprovesFollowers' => 'as:manuallyApprovesFollowers',
                    'toot' => 'http://joinmastodon.org/ns#',
                    'featured' => [
                        '@id' => 'toot:featured',
                        '@type' => '@id',
                    ],
                    'featuredTags' => [
                        '@id' => 'toot:featuredTags',
                        '@type' => '@id',
                    ],
                    'alsoKnownAs' => [
                        '@id' => 'as:alsoKnownAs',
                        '@type' => '@id',
                    ],
                    'movedTo' => [
                        '@id' => 'as:movedTo',
                        '@type' => '@id',
                    ],
                    'schema' => 'http://schema.org#',
                    'PropertyValue' => 'schema:PropertyValue',
                    'value' => 'schema:value',
                    'discoverable' => 'toot:discoverable',
                    'Device' => 'toot:Device',
                    'Ed25519Signature' => 'toot:Ed25519Signature',
                    'Ed25519Key' => 'toot:Ed25519Key',
                    'Curve25519Key' => 'toot:Curve25519Key',
                    'EncryptedMessage' => 'toot:EncryptedMessage',
                    'publicKeyBase64' => 'toot:publicKeyBase64',
                    'deviceId' => 'toot:deviceId',
                    'claim' => [
                        '@type' => '@id',
                        '@id' => 'toot:claim',
                    ],
                    'fingerprintKey' => [
                        '@type' => '@id',
                        '@id' => 'toot:fingerprintKey',
                    ],
                    'identityKey' => [
                        '@type' => '@id',
                        '@id' => 'toot:identityKey',
                    ],
                    'devices' => [
                        '@type' => '@id',
                        '@id' => 'toot:devices',
                    ],
                    'messageFranking' => 'toot:messageFranking',
                    'messageType' => 'toot:messageType',
                    'cipherText' => 'toot:cipherText',
                    'suspended' => 'toot:suspended',
                    'Emoji' => 'toot:Emoji',
                    'focalPoint' => [
                        '@container' => '@list',
                        '@id' => 'toot:focalPoint',
                    ],
                ],
            ],
            'id' => $this->author_url,
            'type' => 'Person',
            // 'following' => route('activitypub.following', $this),
            'followers' => route('activitypub.followers', $this),
            'inbox' => route('activitypub.inbox', $this),
            'outbox' => route('activitypub.outbox', $this),
            'preferredUsername' => $this->login, // Username.
            'name' => $this->name, // Full name (or whatever one filled out).
            'summary' => strip_tags($this->bio),
            'url' => $this->actor_url,
            'manuallyApprovesFollowers' => false,
            'discoverable' => true,
            'published' => $this->created_at
                ? str_replace('+00:00', 'Z', $this->created_at->toIso8601String())
                : str_replace('+00:00', 'Z', now()->toIso8601String()),
            'publicKey' => [
                'id' => $this->author_url . '#main-key',
                'owner' => $this->author_url,
                'publicKeyPem' => $this->public_key,
            ],
            'endpoints' => [
                'sharedInbox' => url('activitypub/inbox'),
            ],

            // Profile "links."
            // 'attachment' => [[]],

            // Header image.
            // 'image' => [
            //     'type' => 'Image',
            //     'mediaType' => 'image/jpeg',
            //     'url' => <...>,
            // ],
        ];

        if (Str::isUrl($this->avatar, ['http', 'https'])) {
            $output['icon'] = [
                'type' => 'Image',
                'mediaType' => ($attachment = url_to_attachment($this->avatar))
                    ? $attachment->mime_type
                    : 'application/octet-stream',
                'url' => filter_var($this->avatar, FILTER_SANITIZE_URL),
            ];
        }

        return $output;
    }
}
