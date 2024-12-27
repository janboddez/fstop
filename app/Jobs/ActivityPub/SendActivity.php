<?php

namespace App\Jobs\ActivityPub;

use App\Models\Entry;
use App\Models\User;
use App\Support\HttpSignature;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SendActivity implements ShouldQueue
{
    use Queueable;

    protected string $type;
    protected string $inbox;
    protected Entry|User $object;
    protected string $hash;

    /**
     * Create a new job instance.
     */
    public function __construct(string $type, string $inbox, Entry|User $object, string $hash = null)
    {
        $this->type = $type;
        $this->inbox = $inbox;
        $this->object = $object->withoutRelations(); // Looks like it'll nevertheless autoload `meta`, _which is good_.

        if ($hash) {
            $this->hash = $hash; // We've calculated this upfront.
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {

        if ($this->object instanceof Entry) {
            if ($this->type !== 'Delete' && $this->object->trashed()) {
                return;
            }

            if ($this->object->status !== 'published') {
                return;
            }

            if ($this->object->visibility === 'private') {
                return;
            }

            $object = $this->object->serialize();
            $body = json_encode(array_filter([
                '@context' => ['https://www.w3.org/ns/activitystreams'],
                'id' => $this->object['id'] . '#' . strtolower($this->type) . '-' . bin2hex(random_bytes(16)),
                'type' => $this->type,
                'actor' => $this->object->user->actor_url,
                'object' => $object,
                'published' => $object['published'],
                'updated' => $this->type !== 'Create' ? $object['updated'] : null,
                'to' => $object['to'] ?? ['https://www.w3.org/ns/activitystreams#Public'],
                'cc' => $object['cc'] ?? [url("activitypub/users/{$this->object->user->id}/followers")],
            ]));

            $headers = HttpSignature::sign(
                $this->object->user,
                $this->inbox,
                $body,
                [
                    'Accept' => 'application/activity+json, application/json',
                    'Content-Type' => 'application/activity+json', // Same as the `$contentType` argument below.
                ],
            );

            $response = Http::withHeaders($headers)
                ->withBody($body, 'application/activity+json')
                ->post($this->inbox);

            if ($response->successful()) {
                Log::debug("[ActivityPub] Successfully sent {$this->type} activity to {$this->inbox}");

                if (! $this->hash || $this->type !== 'Delete') {
                    // This is where we store a hash of the _body minus any `updated` property, to avoid sending the
                    // same version of a post over and over again. Except for Deletes; for those, we've probably already
                    // deleted the previously stored value.
                    $this->object->meta()->updateOrCreate(
                        ['key' => 'activitypub_hash'],
                        ['value' => (array) $this->hash]
                    );
                }

                // The bad thing is we sort of do this for every job rather than once at the end (because we run them
                // asynchronously). And if we somehow successfully sent a Create to, like, only half our followers, then
                // the next update of the entry in question would result in an Update for _all_ followers.
                // Alternatively, we could run all jobs inline and only store the "hash" once, but that'd lead to other
                // problems, or store one hash per follower inbox (or shared inbox), which would lead to quite some
                // metadata but otherwise seems like it could work.
            } else {
                Log::error("[ActivityPub] Something went wrong sending {$this->type} activity to {$this->inbox}");
                Log::debug($body);
                Log::debug($response);
            }
        }

        if ($this->object instanceof User) {
            // We'll have to figure this out later.
        }
    }
}
