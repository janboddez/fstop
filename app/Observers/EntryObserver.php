<?php

namespace App\Observers;

use App\Jobs\SendWebmention;
use App\Models\Entry;
use Carbon\Carbon;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Str;
use TorMorten\Eventy\Facades\Events as Eventy;

class EntryObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Runs whenever an entry is saved (created, updated ...), except when it is saved or updated "quietly."
     */
    public function saving(Entry $entry): void
    {
        if (empty($entry->name)) {
            // Generate a title off the (current) content.
            $name = strip_tags($entry->content); // Strip tags.
            $name = Str::words($name, 10, ' …'); // Shorten.
            $name = html_entity_decode($name); // Decode quotes, etc. (We escape on output.)
            $name = preg_replace('~… …$~', '…', $name);
            $name = preg_replace('~\s+~', ' ', $name); // Get rid of excess whitespace.
            $name = Str::limit($name, 250, '…'); // Shorten (again).
        }

        // Allow plugins to override the title.
        $entry->name = Eventy::filter(
            'entries.set_name',
            $name ?? $entry->name ?? __('(No Title)'),
            $entry
        );

        // Allow plugins to bypass automatic slug generation.
        $slug = Eventy::filter('entries.set_slug', '', $entry);

        if (empty($slug)) {
            // If no plugin-generated slug was set.
            if ($entry->type === 'page') {
                // We use our own slug helper on pages, to also allow forward slashes.
                $slug = ! empty($entry->slug)
                    ? Entry::slug($entry->slug)
                    : Entry::slug($entry->name);
            } else {
                // Everything else gets a slug based on its name, using the "normal" slug helper.
                $slug = ! empty($entry->slug)
                    ? Str::slug($entry->slug)
                    : Str::slug($entry->name);
            }
        }

        if (empty($slug)) {
            // If somehow still no proper slug was generated.
            $slug = random_slug(); // Create a random slug.
        } else {
            // Ensure the generated slug is unique.
            $slug = Str::limit($slug, 250);

            $counter = 1;

            // Note the need to ignore, in the case of updates, the
            // entry being updated.
            while (
                Entry::where('slug', $slug)
                    ->where('id', '!=', $entry->id ?? 0)
                    ->withTrashed()
                    ->exists()
            ) {
                $newSlug = "$slug-$counter";
                $counter++;
            }
        }

        $entry->slug = $newSlug ?? $slug;

        // Ensure `created_at` is always set.
        if (
            preg_match('~\d{4}-\d{2}-\d{2}~', request()->input('created_at')) &&
            preg_match('~\d{2}:\d{2}~', request()->input('time'))
        ) {
            // If we were given a date and a time, use those.
            $createdAt = Carbon::parse(request()->input('created_at') . ' ' . request()->input('time'));
        } else {
            // Keep unchanged, or fall back to "now."
            $createdAt = $entry->created_at ?? Carbon::now();
        }

        $entry->created_at = $createdAt;

        Eventy::action('entries.saving', $entry);
    }

    public function saved(Entry $entry): void
    {
        SendWebmention::dispatch($entry);

        Eventy::action('entries.saved', $entry);
    }

    public function restoring(Entry $entry): void
    {
        $entry->status = 'draft';
    }
}
