<?php

namespace App\Providers;

use App\Jobs\ActivityPub\SendActivity;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use TorMorten\Eventy\Facades\Events as Eventy;

use function App\Support\ActivityPub\fetch_profile;
use function App\Support\ActivityPub\generate_activity;

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

            $entry->refresh();

            if (($hash = $entry->meta->firstWhere('key', 'activitypub_hash')) && ! empty($hash->value[0])) {
                // Update. But first, verify anything actually changed.
                $array = $entry->serialize();
                unset($array['updated']);
                $newHash = md5(json_encode($array));

                if ($hash->value[0] === $newHash) {
                    Log::debug('[ActivityPub] Previously federated this entry, but nothing seems to have changed');
                } else {
                    Log::debug('[ActivityPub] Previously federated this entry; scheduling "Update"');

                    // Generate the "activity" just once.
                    if (! $activity = generate_activity('Update', $entry)) {
                        return;
                    }

                    $inboxes = [];
                    foreach ($entry->user->followers as $follower) {
                        $inboxes[] = $follower->shared_inbox ?? $follower->inbox;
                    }

                    foreach ($entry->mentions as $actorUrl) {
                        // Include mentioned actors' inboxes.
                        $profile = fetch_profile($actorUrl, $entry->user);
                        $inboxes[] = $profile['shared_inbox'] ?? $profile['inbox'] ?? null;
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
            if (! $activity = generate_activity('Create', $entry)) {
                return;
            }

            $inboxes = [];
            foreach ($entry->user->followers as $follower) {
                $inboxes[] = $follower->shared_inbox;
            }

            foreach ($entry->mentions as $actorUrl) {
                // Include mentioned actors' inboxes.
                $profile = fetch_profile($actorUrl, $entry->user);
                $inboxes[] = $profile['shared_inbox'] ?? $profile['inbox'] ?? null;
            }

            $inboxes = array_unique(array_filter($inboxes));
            foreach ($inboxes as $inbox) {
                SendActivity::dispatch($inbox, $activity, $entry, $newHash);
            }
        }, PHP_INT_MAX, 2); // Execute, or rather, schedule (!) after, well, everything else.

        Eventy::addAction('entries:deleted', function (Entry $entry) {
            static::sendDelete($entry);
        }, PHP_INT_MAX); // Execute, or rather, schedule (!) after, well, everything else.

        Eventy::addAction('users:saved', function (User $user) {
            // Generate the "activity" just once.
            $array = $user->serialize();
            unset($array['updated']);
            $newHash = md5(json_encode($array));

            if (! $activity = generate_activity('Update', $user)) {
                return;
            }

            $inboxes = [];
            foreach ($user->followers as $follower) {
                $inboxes[] = $follower->shared_inbox;
            }

            $inboxes = array_unique(array_filter($inboxes));
            foreach ($inboxes as $inbox) {
                SendActivity::dispatch($inbox, $activity, $user, $newHash);
            }
        }, PHP_INT_MAX);
    }

    protected static function sendDelete(Entry $entry): void
    {
        if (($hash = $entry->meta()->firstWhere('key', 'activitypub_hash')) && ! empty($hash->value[0])) {
            // Entry was federated before. (We don't know to what servers, but we also don't know what other servers
            // it ended up on, so that should be okay.)
            Log::debug('[ActivityPub] Deleted entry; scheduling "Delete"');

            if (! $activity = generate_activity('Delete', $entry)) {
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
}
