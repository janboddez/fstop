<?php

namespace App\Console\Commands;

use App\Models\Entry;
use Illuminate\Console\Command;

class SearchReplace extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'entries:search-replace {search} {replace}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Search-replace in entries';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $search = $this->argument('search');
        $replace = $this->argument('replace');

        if ($search === $replace) {
            // All done. ;-)
            return;
        }

        $entries = Entry::all();

        foreach ($entries as $entry) {
            if (! empty($entry->name)) {
                $entry->name = str_replace($search, $replace, $entry->name);
            }

            // $entry->content = str_replace($search, $replace, $entry->content);
            $entry->content = str_replace($search, $replace, $entry->rawContent);

            foreach ($entry->meta as $meta) {
                $value = (array) $meta->value;

                array_walk_recursive(
                    $value,
                    function (&$item) use ($search, $replace) {
                        if (is_string($item)) {
                            $item = str_replace($search, $replace, $item);
                        }

                        return $item;
                    }
                );

                $meta->update(['value' => (array) $value]);
            }

            $entry->saveQuietly();
        }
    }
}
