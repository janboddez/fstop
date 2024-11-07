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

        $posts = Entry::all();

        foreach ($posts as $post) {
            if (! empty($post->name)) {
                $post->name = str_replace($search, $replace, $post->name);
            }

            $post->content = str_replace($search, $replace, $post->content);

            $meta = $post->meta;
            if ($meta) {
                array_walk_recursive(
                    $meta,
                    function (&$value) use ($search, $replace) {
                        if (is_string($value)) {
                            $value = str_replace($search, $replace, $value);
                        }

                        return $value;
                    }
                );

                $post->meta = $meta;
            }

            $post->saveQuietly();
        }
    }
}
