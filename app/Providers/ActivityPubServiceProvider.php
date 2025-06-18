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
        Eventy::addAction('entries:saved', function (Entry $entry) {
            if ($entry->trashed()) {
                // Do nothing. (Actual deletes are dealt with elsewhere!)
                return;
            }

            $supportedTypes = Eventy::filter('activitypub:entry_types', ['article', 'note', 'like']);
            if (! in_array($entry->type, $supportedTypes, true)) {
                // Again added these types explicitly, as the filter doesn't seem to run in time.
                return;
            }

            if ($entry->status !== 'published') {
                /**
                 * @todo Implement a proper "unpublish" handler. Should probably go in
                 *       `App\Http\Controllers\Admin\EntryController`.
                 */
                return;
            }

            if ($entry->visibility === 'private') {
                return;
            }

            if (($hash = $entry->meta()->firstWhere('key', 'activitypub_hash')) && ! empty($hash->value[0])) {
                // Update. But first, verify anything actually changed.
                $array = $entry->serialize();
                unset($array['updated']);
                $newHash = md5(json_encode($array));

                if ($hash->value[0] === $newHash) {
                    Log::debug('[ActivityPub] Previously federated this entry, but nothing seems to have changed');
                } else {
                    Log::debug('[ActivityPub] Previously federated this entry; scheduling "Update"');

                    $inboxes = [];
                    foreach ($entry->user->followers as $follower) {
                        $inboxes[] = $follower->shared_inbox;
                    }

                    $inboxes = array_unique(array_filter($inboxes));
                    foreach ($inboxes as $inbox) {
                        SendActivity::dispatch('Update', $inbox, $entry, $newHash);
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

            $inboxes = [];
            foreach ($entry->user->followers as $follower) {
                $inboxes[] = $follower->shared_inbox;
            }

            $inboxes = array_unique(array_filter($inboxes));
            foreach ($inboxes as $inbox) {
                SendActivity::dispatch('Create', $inbox, $entry, $newHash);
            }
        }, PHP_INT_MAX); // Execute, or rather, schedule (!) after, well, everything else.

        Eventy::addAction('entries:deleted', function (Entry $entry) {
            /**
             * Entries' ActivityPub "Delete" activities.
             */
            if (($hash = $entry->meta()->firstWhere('key', 'activitypub_hash')) && ! empty($hash->value[0])) {
                // Entry was federated before. (We don't know to what servers, but we also don't know what other servers
                // it ended up on, so that should be okay.)
                Log::debug('[ActivityPub] Deleted entry; scheduling "Delete"');

                $inboxes = [];
                foreach ($entry->user->followers as $follower) {
                    $inboxes[] = $follower->shared_inbox;
                }

                $inboxes = array_unique(array_filter($inboxes));
                foreach ($inboxes as $inbox) {
                    SendActivity::dispatch('Delete', $inbox, $entry);
                }

                // Delete any trace of previous federation.
                $entry->meta()
                    ->firstWhere('key', 'activitypub_hash')
                    ->delete();
            }
        });
    }
}
