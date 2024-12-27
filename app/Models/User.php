<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected $with = ['meta'];

    public function meta(): MorphMany
    {
        return $this->morphMany(Meta::class, 'metable');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    public function followers(): HasMany
    {
        return $this->hasMany(Follower::class);
    }

    protected function actorUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->author_url
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

    /**
     * @todo Add aliases? To let users who come from WordPress (i.e., me) add an alias in custom user meta or something.
     */
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
            'id' => route('users.show', $this),
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
                'id' => $this->actor_url . '#main-key',
                'owner' => route('users.show', $this),
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

        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
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
