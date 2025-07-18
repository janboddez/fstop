<?php

namespace App\Providers;

use App\Jobs\ActivityPub\SendActivity;
use App\Models\Entry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use TorMorten\Eventy\Facades\Events as Eventy;

class ActivityPubServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Serves a similar purpose as the `EntryObserver::saved()` method, but runs after tags and metadata are saved,
        // too.
        Eventy::addAction('entries:saved', function (Entry $entry, ?string $previousStatus = null) {
            if ($entry->trashed()) {
                // Do nothing. (Actual deletes are dealt with elsewhere!)
                Log::debug('[ActivityPub] Deleted entry; skip for now');

                return;
            }

            if ($entry->visibility === 'private') {
                Log::debug('[ActivityPub] Private entry; let’s not federate those');

                return;
            }

            $supportedTypes = Eventy::filter('activitypub:entry_types', ['article', 'note', 'like']);
            if (! in_array($entry->type, $supportedTypes, true)) {
                // Again added these types explicitly, as the filter doesn't seem to run in time.
                Log::debug('[ActivityPub] Unsupported entry type');

                return;
            }

            if ($entry->status !== 'published' && $previousStatus === 'published') {
                Log::debug('[ActivityPub] Freshly “unpublished” entry; treat as delete');
                static::sendDelete($entry); /** @todo Figure out why somehow this doesn't work. */

                Log::debug('[ActivityPub] All good, let’s stop here');

                return;
            }

            if ($entry->status !== 'published') {
                Log::debug('[ActivityPub] Previously “unpublished” entry; quitting');

                return;
            }

            $entry->load('meta');

            if (($hash = $entry->meta()->firstWhere('key', 'activitypub_hash')) && ! empty($hash->value[0])) {
                // Update. But first, verify anything actually changed.
                $array = $entry->serialize();
                unset($array['updated']);
                $newHash = md5(json_encode($array));

                if ($hash->value[0] === $newHash) {
                    Log::debug('[ActivityPub] Previously federated this entry, but nothing seems to have changed');
                } else {
                    Log::debug('[ActivityPub] Previously federated this entry; scheduling "Update"');

                    // Generate the "activity" just once.
                    if (! $activity = static::generateActivity('Update', $entry)) {
                        return;
                    }

                    $inboxes = [];
                    foreach ($entry->user->followers as $follower) {
                        $inboxes[] = $follower->shared_inbox;
                    }

                    $inboxes = array_unique(array_filter($inboxes));
                    foreach ($inboxes as $inbox) {
                        SendActivity::dispatch($inbox, $activity, $entry, $newHash);
                    }
                }

                return;
            }

            // Create.
            Log::debug('[ActivityPub] Newly federated entry; scheduling "Create"');

            // Calculate this "hash" just once.
            $array = $entry->serialize();
            unset($array['updated']);
            $newHash = md5(json_encode($array));

            // Generate the "activity" just once.
            if (! $activity = static::generateActivity('Create', $entry)) {
                return;
            }

            $inboxes = [];
            foreach ($entry->user->followers as $follower) {
                $inboxes[] = $follower->shared_inbox;
            }

            $inboxes = array_unique(array_filter($inboxes));
            foreach ($inboxes as $inbox) {
                SendActivity::dispatch($inbox, $activity, $entry, $newHash);
            }
        }, PHP_INT_MAX, 2); // Execute, or rather, schedule (!) after, well, everything else.

        Eventy::addAction('entries:deleted', function (Entry $entry) {
            static::sendDelete($entry);

            return;
        });
    }

    protected static function sendDelete(Entry $entry): void
    {
        if (($hash = $entry->meta()->firstWhere('key', 'activitypub_hash')) && ! empty($hash->value[0])) {
            // Entry was federated before. (We don't know to what servers, but we also don't know what other servers
            // it ended up on, so that should be okay.)
            Log::debug('[ActivityPub] Deleted entry; scheduling "Delete"');

            if (! $activity = static::generateActivity('Delete', $entry)) {
                return;
            }

            $inboxes = [];
            foreach ($entry->user->followers as $follower) {
                $inboxes[] = $follower->shared_inbox;
            }

            $inboxes = array_unique(array_filter($inboxes));
            foreach ($inboxes as $inbox) {
                SendActivity::dispatch($inbox, $activity, $entry);
            }

            // Delete any trace of previous federation.
            $entry->meta()
                ->firstWhere('key', 'activitypub_hash')
                ->delete();
        }
    }

    protected static function generateActivity(string $type, Entry $entry): ?array
    {
        $object = $entry->serialize();

        $activity = array_filter([
            '@context' => ['https://www.w3.org/ns/activitystreams'],
            'type' => $type,
            'actor' => $entry->user->author_url,
            'object' => $object,
            'published' => $object['published'],
            'updated' => $type === 'Create' ? null : ($object['updated'] ?? null),
            'to' => $object['to'] ?? ['https://www.w3.org/ns/activitystreams#Public'],
            'cc' => $object['cc'] ?? [url("activitypub/users/{$entry->user->id}/followers")],
        ]);

        /**
         * This part's kinda "nasty"; it's where we try to add Like and Announce support.
         */
        if (($likeOf = $entry->meta->firstWhere('key', '_like_of')) && ! empty($likeOf->value[0])) {
            // Convert to Like activity.
            if ($type === 'Create') {
                $activity['type'] = 'Like';

                /** @todo Verify the liked page even supports ActivityPub, and return early if it doesn't. */
                $activity['object'] = filter_var($likeOf->value[0], FILTER_VALIDATE_URL);

                /** @todo Add the remote page's author to our mentions even if they weren't mentioned explicitly. */

                unset($activity['updated']);
            } elseif ($type === 'Delete') {
                if (($like = $entry->meta->firstWhere('key', '_activitypub_activity')) && ! empty($like->value)) {
                    // Undoing a previous like.
                    $activity['type'] = 'Undo';
                    $activity['object'] = $like->value; // The Like activity from before.
                    unset($activity['updated']);
                } else {
                    return null;
                }
            }
        } elseif (($repostOf = $entry->meta->firstWhere('key', '_repost_of')) && ! empty($repostOf->value[0])) {
            // Convert to Announce activity.
            if ($type === 'Create') {
                $activity['type'] = 'Announce';

                /** @todo Verify the "reposted" page even supports ActivityPub, and return early if it doesn't. */
                $activity['object'] = filter_var($repostOf->value[0], FILTER_VALIDATE_URL);

                /** @todo Add the remote page's author to our mentions even if they weren't mentioned explicitly. */

                unset($activity['updated']);
            } elseif ($type === 'Delete') {
                if (($announce = $entry->meta->firstWhere('key', '_activitypub_activity')) && ! empty($announce->value)) {
                    // Undoing a previous Announce.
                    $activity['type'] = 'Undo';
                    $activity['object'] = $announce->value; // The Announce activity from before.
                    unset($activity['updated']);
                } else {
                    return null;
                }
            }
        }

        if ($activity['type'] === 'Update') {
            // We want (only) Updates to have a truly unique activity ID.
            $activity['id'] = $object['id'] . '#' . strtolower($activity['type'] ?? $type) . '-'
                . bin2hex(random_bytes(16));
        } else {
            $activity['id'] = $object['id'] . '#' . strtolower($activity['type']);
        }

        return $activity;
    }
}
