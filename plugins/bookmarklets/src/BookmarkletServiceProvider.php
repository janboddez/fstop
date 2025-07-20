<?php

namespace Plugins\Bookmarklets;

use Illuminate\Support\ServiceProvider;

class BookmarkletServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../public' => public_path('vendor/bookmarklets'),
        ], 'public');

        if (! class_exists('\\Plugins\\EntryTypes\\EntryTypesServiceProvider')) {
            // Do nothing.
            return;
        }

        $this->registerHooks();
    }

    protected function registerHooks(): void
    {
        add_action('admin:dashboard', function () {
            ?>
            <div class="card mt-5">
                <table class="table is-fullwidth is-striped">
                    <thead>
                        <tr>
                            <th colspan="2"><?php echo __('Bookmarklets'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <a class="button" href="javascript:(() => {window.open('<?php echo route('admin.entries.create', ['type' => 'note']); // phpcs:ignore Generic.Files.LineLength.TooLong?>&bookmark_of=' + encodeURIComponent(window.location.href) + '&selected_text=' + encodeURIComponent(window.getSelection()?.toString()));})();"><?php echo __('Bookmark'); ?></a>
                                <a class="button" href="javascript:(() => {window.open('<?php echo route('admin.entries.create', ['type' => 'like']); // phpcs:ignore Generic.Files.LineLength.TooLong?>&like_of=' + encodeURIComponent(window.location.href) + '&selected_text=' + encodeURIComponent(window.getSelection()?.toString()));})();"><?php echo __('Like'); ?></a>
                                <a class="button" href="javascript:(() => {window.open('<?php echo route('admin.entries.create', ['type' => 'note']); // phpcs:ignore Generic.Files.LineLength.TooLong?>&in_reply_to=' + encodeURIComponent(window.location.href) + '&selected_text=' + encodeURIComponent(window.getSelection()?.toString()));})();"><?php echo __('Reply'); ?></a>
                                <a class="button" href="javascript:(() => {window.open('<?php echo route('admin.entries.create', ['type' => 'note']); // phpcs:ignore Generic.Files.LineLength.TooLong?>&repost_of=' + encodeURIComponent(window.location.href) + '&selected_text=' + encodeURIComponent(window.getSelection()?.toString()));})();"><?php echo __('Repost'); ?></a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php
        });

        add_action('admin:scripts', function () {
            if (! request()->is('admin/entries/create')) {
                return;
            }

            // Super hacky JS localization.
            echo '<script>
var bookmarklets_obj = {
    bookmarked: "' . e(__('Bookmarked %s.')) . '",
    in_reply_to: "' . e(__('In reply to %s.')) . '",
    likes: "' . e(__('Likes %s.')) . '",
    reposted: "' . e(__('Reposted %s.')) . '",
};
</script>' . PHP_EOL;

            echo '<script src="' . asset('vendor/bookmarklets/bookmarklets.js') . '"></script>' . PHP_EOL;
        });
    }
}
