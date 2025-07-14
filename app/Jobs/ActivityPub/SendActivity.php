<?php

namespace App\Jobs\ActivityPub;

use App\Models\Entry;
use App\Models\User;
use App\Support\ActivityPub\HttpSignature;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use TorMorten\Eventy\Facades\Events as Eventy;

class SendActivity implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected string $inbox,
        protected array $activity,
        protected Entry|User $object,
        protected ?string $hash = null
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->object instanceof Entry) {
            if (! $this->object->published) {
                Log::debug('[ActivityPub] Missing `published` date.');

                return;
            }

            if ($this->activity['type'] === 'Create' && $this->object->published->lt(now()->subHours(12))) {
                // Prevent "old" entries from all of a sudden getting federated.
                /** @todo Make smarter. */
                Log::debug("[ActivityPub] Skipping entry {$this->object->id}: too old");

                return;
            }

            if (
                ($this->object->trashed() || $this->object->status !== 'published') &&
                ! in_array($this->activity['type'], ['Delete', 'Undo'], true)
            ) {
                // For trashed or unpublished entries, we support but the Delete and Undo types.
                // phpcs:ignore Generic.Files.LineLength.TooLong
                Log::debug("[ActivityPub] Entry is trashed or unpublished but activity type is {$this->activity['type']}");

                return;
            }

            if ($this->object->visibility === 'private') {
                Log::debug('[ActivityPub] Private entry.');

                return;
            }

            $supportedTypes = Eventy::filter('activitypub:entry_types', ['article', 'note', 'like']);
            if (! in_array($this->object->type, $supportedTypes, true)) {
                Log::debug('[ActivityPub] Invalid entry type.');

                return;
            }

            $body = json_encode($this->activity); // Was generated upfront.
            $contentType = 'application/activity+json';
            $headers = HttpSignature::sign(
                $this->object->user,
                $this->inbox,
                $body,
                [
                    'Accept' => 'application/activity+json, application/json',
                    'Content-Type' => $contentType, // Must be the same as the `$contentType` argument below.
                ],
            );

            $response = Http::withHeaders($headers)
                ->withBody($body, $contentType)
                ->post($this->inbox);

            // Log::debug($headers);
            // Log::debug($body);

            if ($response->successful()) {
                Log::debug("[ActivityPub] Successfully sent {$this->activity['type']} activity to {$this->inbox}");

                // if ($activity['type'] === 'Undo') {
                //     // ~~We sent an Undo and can forget about the original activity.~~
                //     // Wrong! Other servers still need to be served the Undo!
                //     $this->object->meta()
                //         ->where('key', '_activitypub_activity')
                //         ->delete();
                // }

                if (in_array($this->activity['type'], ['Like', 'Announce'], true)) {
                    // Store Like or Announce activities, in case one day we want to Undo them.
                    $this->object->meta()->firstOrCreate(
                        ['key' => '_activitypub_activity'],
                        ['value' => (array) $this->activity]
                    );
                }

                if (! empty($this->hash) && $this->activity['type'] !== 'Delete') {
                    // This is where we store a hash of the _body minus any `updated` property_, to avoid sending the
                    // same version of a post over and over again. Except for Deletes: for those, we've probably already
                    // deleted the previously stored value.
                    $this->object->meta()->firstOrCreate(
                        ['key' => 'activitypub_hash'],
                        ['value' => (array) $this->hash]
                    );
                }
            } else {
                // phpcs:ignore Generic.Files.LineLength.TooLong
                Log::error("[ActivityPub] Something went wrong sending {$this->activity['type']} activity to {$this->inbox}");
                Log::debug($body);
                Log::debug($response);
            }
        }

        if ($this->object instanceof User) {
            // We'll have to figure this out later.
        }
    }
}
