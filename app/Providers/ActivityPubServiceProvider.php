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
        // Serves a similar purpose as the `EntryObserver::saved()` method, but runs _after tags and metadata are
        // saved, too_.
        Eventy::addAction('entries:saved', function (Entry $entry) {
            if ($entry->trashed()) {
                // Do nothing. (Actual deletes are dealt with elsewhere!)
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
    }
}
