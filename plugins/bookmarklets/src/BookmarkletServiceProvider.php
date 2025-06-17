<?php

namespace Plugins\Bookmarklets;

use Illuminate\Support\ServiceProvider;

class BookmarkletServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
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
                                <a class="button" href="javascript:(() => {window.open('<?php echo route('admin.entries.create', ['type' => 'note']); // phpcs:ignore Generic.Files.LineLength.TooLong ?>&bookmark_of=' + encodeURIComponent(window.location.href) + '&selected_text=' + encodeURIComponent(window.getSelection()?.toString()));})();"><?php echo __('Bookmark'); ?></a>
                                <a class="button" href="javascript:(() => {window.open('<?php echo route('admin.entries.create', ['type' => 'like']); // phpcs:ignore Generic.Files.LineLength.TooLong ?>&like_of=' + encodeURIComponent(window.location.href) + '&selected_text=' + encodeURIComponent(window.getSelection()?.toString()));})();"><?php echo __('Like'); ?></a>
                                <a class="button" href="javascript:(() => {window.open('<?php echo route('admin.entries.create', ['type' => 'note']); // phpcs:ignore Generic.Files.LineLength.TooLong ?>&in_reply_to=' + encodeURIComponent(window.location.href) + '&selected_text=' + encodeURIComponent(window.getSelection()?.toString()));})();"><?php echo __('Reply'); ?></a>
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

            ?>
<script>
(() => {
    const escapeHtml = (str) => {
        const lookup = {
            '&': '&amp;',
            '"': '&quot;',
            '\'': '&apos;',
            '<': '&lt;',
            '>': '&gt;',
        };

        return str.replace(/[&"'<>]/g, c => lookup[c]);
    };

    const isValidUrl = (str) => {
        try {
            new URL(str);
        } catch (error) {
            return false;
        }

        return true;
    }

    const content = document.getElementById('content');
    if (! content) {
        return;
    }

    const queryString = window.location.search;
    if (! queryString) {
        return;
    }

    const urlParams = new URLSearchParams(queryString);
    let value = '';

    const bookmarkOf = urlParams?.get('bookmark_of');
    if (bookmarkOf && isValidUrl(bookmarkOf)) {
        value = '*Bookmarked [' + bookmarkOf + '](' + bookmarkOf + '){.u-bookmark-of}.*';
    }

    const likeOf = urlParams?.get('like_of');

    if (likeOf && isValidUrl(likeOf)) {
        value = '*Likes [' + likeOf + '](' + likeOf + '){.u-like-of}.*';
    }

    const inReplyTo = urlParams?.get('in_reply_to');
    if (inReplyTo && isValidUrl(inReplyTo)) {
        value = '*In reply to [' + inReplyTo + '](' + inReplyTo + '){.u-in-reply-to}.*';
    }

    const selectedText = urlParams?.get('selected_text');

    if (selectedText) {
        // @todo Loop over all lines, and prepend each line with `> `.
        value += "\n\n<div class=\"e-content\" markdown=\"1\">\n" + escapeHtml(selectedText) + '\n</div>';
    }

    content.value = value.trim();
})();
</script>
            <?php
        });
    }
}
