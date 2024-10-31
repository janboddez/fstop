<?php

namespace App\Observers;

use App\Jobs\SendWebmention;
use App\Models\Entry;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Michelf\MarkdownExtra;
use TorMorten\Eventy\Facades\Events as Eventy;

class EntryObserver
{
    /**
     * Handle events after all transactions are committed.
     *
     * @var bool
     */
    public $afterCommit = true;

    /**
     * Runs whenever an entry is saved (created, updated ...), except when it is saved or updated "quietly."
     *
     * Remark: Some entry properties are filterable, to allow plugins to override them, but plugins could just as well
     * register their own observer, so maybe we don't actually need these?
     */
    public function saving(Entry $entry): void
    {
        // Ensure a title is set.
        if (empty($entry->name)) {
            // Generate a title off the (current) content.
            $parser = new MarkdownExtra();
            $parser->no_markup = false; // Do not escape markup already present.

            $content = $parser->defaultTransform($entry->content);
            $content = trim(strip_tags($content));

            $name = Str::words($content, 10, ' …');

            // Decode quotes, etc. (We escape on output.)
            $name = html_entity_decode($name, ENT_HTML5, 'UTF-8');
            $name = preg_replace('~\s+~', ' ', $name); // Get rid of excess whitespace.
            $name = Str::limit($name, 250, '…'); // Shorten (again).
        }

        // Allow plugins to override the title.
        $entry->name = Eventy::filter('entries.set_name', $name ?? $entry->name ?? '(No Title)', $entry);

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

        /**
         * Ensure `created_at` is always set.
         */
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

        // Normalize line endings.
        $entry->content = preg_replace('~\R~u', "\r\n", $entry->content); // `$entry->content` is required!

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
