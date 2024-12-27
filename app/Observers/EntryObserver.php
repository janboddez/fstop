<?php

namespace App\Observers;

use App\Jobs\ActivityPub\SendActivity;
use App\Models\Entry;
use Carbon\Carbon;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use TorMorten\Eventy\Facades\Events as Eventy;

class EntryObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Runs whenever an entry is saved (unless of course it's saved "quietly").
     */
    public function saving(Entry $entry): void
    {
        // Ensure each entry gets a "title."
        if (empty($entry->name)) {
            // Generate a title off the (current) content.
            $name = $entry->content;
            $name = preg_replace('~<sup id="fnref:\d+">.*?</sup>~', '', $name); // Strip "footnote" `sup` tags.
            $name = strip_tags($name); // Strip tags.
            $name = Str::words($name, 10, ' …'); // Shorten.
            $name = html_entity_decode($name); // Decode quotes, etc. (We escape on output.)
            $name = Str::replaceEnd('… …', '…', $name);
            $name = preg_replace('~\s+~', ' ', $name); // Get rid of excess whitespace.
            $name = Str::limit($name, 250, '…'); // Shorten (again).
        }

        // Allow plugins to override entry titles.
        $entry->name = Eventy::filter('entries:set_name', $name ?? $entry->name ?? __('(No Title)'), $entry);

        // Allow plugins to completely bypass (automatic) slug generation.
        $slug = Eventy::filter('entries:set_slug', '', $entry);

        if (empty($slug)) {
            // If no plugin-generated slug was set.
            if ($entry->type === 'page') {
                // We use our own `slugify()` helper on pages, to also allow forward slashes.
                $slug = ! empty($entry->slug)
                    ? slugify($entry->slug)
                    : slugify($entry->name);
            } else {
                // Everything else gets a slug based on its name, using the "normal" slug helper.
                $slug = ! empty($entry->slug)
                    ? Str::slug($entry->slug)
                    : Str::slug($entry->name);
            }
        }

        if (empty($slug)) {
            // If somehow still no proper slug was generated, like when the suggested slug got sanitized down to an
            // empty string.
            $slug = random_slug(); // Create a random slug.
        } else {
            // Trim down very long slugs.
            $slug = Str::limit($slug, 250);

            // Ensure the generated slug is unique.
            $counter = 1;

            while (
                Entry::where('slug', $slug)
                    ->where('id', '!=', $entry->id ?? 0) // If this is an update, ignore the entry being updated.
                    ->withTrashed()
                    ->exists()
            ) {
                $newSlug = "$slug-$counter";
                $counter++;
            }
        }

        $entry->slug = $newSlug ?? $slug;

        // Ensure `created_at` is always set. There's no need to do this for, or otherwise modify `updated_at`, as
        // Laravel should take care of it automatically.
        if (
            preg_match('~\d{4}-\d{2}-\d{2}~', request()->input('created_at')) &&
            preg_match('~\d{2}:\d{2}~', request()->input('time'))
        ) {
            // If we were given a date and a time, use those.
            $createdAt = Carbon::parse(request()->input('created_at') . ' ' . request()->input('time'));
        } else {
            // Keep unchanged, or fall back to "now."
            $createdAt = $entry->created_at ?? now();
        }

        $entry->created_at = $createdAt;
    }

    public function saved(Entry $entry): void
    {
        /**
         * Note the existence of `Eventy::action('entries:saved', $entry);`, a hook we call from several controllers
         * directly, in order to have any callback functions run *after also metadata* is saved.
         */
    }

    public function restoring(Entry $entry): void
    {
        // Ensure entries restored from trash become "draft."
        $entry->status = 'draft';
    }

    /**
     * Won't run for "mass-deleted" entries (but you know that).
     */
    public function deleted(Entry $entry): void
    {
        /** @todo Send webmentions on delete. */
        /** @todo Send Deletes also for "unpublishing." */

        if (($hash = $entry->meta()->firstWhere('key', 'activitypub_hash')) && ! empty($hash->value[0])) {
            // Entry was federated before. (We don't know to what servers, but we also don't know what other servers it
            // ended up on, so that should be okay.)
            Log::debug("[ActivityPub] Deleted entry. Scheduling Delete");

            $inboxes = [];
            foreach ($entry->user->followers as $follower) {
                $inboxes[] = $follower->shared_inbox;
            }

            $inboxes = array_unique(array_filter($inboxes));
            foreach ($inboxes as $inbox) {
                SendActivity::dispatch('Delete', $inbox, $entry); // One job per follower/inbox.
            }

            // Delete any trace of previous federation.
            $entry->meta()
                ->firstWhere('key', 'activitypub_hash')
                ->delete();
        }
    }
}
