(() => {
    String.prototype.format = function() {
        return [...arguments].reduce((p, c) => p.replace(/%s/, c), this);
    };

    const escapeHtml = function(str) {
        const lookup = {
            '&': '&amp;',
            '"': '&quot;',
            '\'': '&apos;',
            '<': '&lt;',
            '>': '&gt;',
        };

        return str.replace(/[&"'<>]/g, c => lookup[c]);
    };

    const isValidUrl = function(str) {
        try {
            new URL(str);
        } catch (error) {
            return false;
        }

        return true;
    };

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
        value = window.bookmarklets_obj.bookmarked.format('[' + bookmarkOf + '](' + bookmarkOf + '){.u-bookmark-of}');
        value = '*' + value + '*';
    }

    const inReplyTo = urlParams?.get('in_reply_to');
    if (inReplyTo && isValidUrl(inReplyTo)) {
        value = window.bookmarklets_obj.in_reply_to.format('[' + inReplyTo + '](' + inReplyTo + '){.u-in-reply-to}');
        value = '*' + value + '*';
    }

    const likeOf = urlParams?.get('like_of');
    if (likeOf && isValidUrl(likeOf)) {
        value = window.bookmarklets_obj.likes.format('[' + likeOf + '](' + likeOf + '){.u-like-of}');
        value = '*' + value + '*';
    }

    let selectedText = urlParams?.get('selected_text');
    if (selectedText) {
        // Loop over all lines and prepend them with `> `.
        const lines = selectedText.split(/\r\n|(?!\r\n)[\n-\r\x85\u2028\u2029]/g);
        selectedText = '';

        lines.forEach(line => {
            selectedText += '> ' + escapeHtml(line) + "\r\n";
        });

        const repostOf = urlParams?.get('repost_of');
        if (repostOf && isValidUrl(repostOf)) {
            value = `<div class="u-repost-of h-cite" markdown="1">
*` + window.bookmarklets_obj.reposted.format('[' + repostOf + '](' + repostOf + '){.u-url}') + `*
<blockquote class="e-content" markdown="1">
` + escapeHtml(selectedText) + `
</blockquote>
</div>`;
        } else {
            value += "\r\n\r\n<div class=\"e-content\" markdown=\"1\">\r\n" + selectedText + '</div>';
        }
    } else if (value) {
        // Add empty `div.e-content`.
        value += "\r\n\r\n<div class=\"e-content\" markdown=\"1\">\r\n</div>";
    }

    content.value = value.trim();
})();
