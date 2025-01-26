((FloatingUIDOM) => {
    const {autoUpdate, computePosition} = FloatingUIDOM;

    const cleanup = {}; // To "hold" the "cleanup" functions (to stop auto-updating popovers' positions).

    document.querySelectorAll('[popover]').forEach((element) => {
        const id = element.getAttribute('id');
        const button = document.querySelector(`[popovertarget="${id}"`);
        if (! button) {
            return;
        }

        const updatePosition = () => {
            computePosition(button, element, {placement: 'bottom-start'})
            .then(({x, y}) => {
                Object.assign(element.style, {
                    left: `${x}px`,
                    top: `${y}px`,
                });
            });
        };

        let cleanup = null;

        element.addEventListener('toggle', (event) => {
            if (event.newState === 'open') {
                // Start auto-updating.
                cleanup = autoUpdate(button, element, updatePosition);
            } else if (typeof cleanup === 'function') {
                // Stop auto-updating.
                cleanup();
                Object.assign(element.style, {
                    top: '-999rem', // Avoid any "jumps" when `element` is next made visible.
                });
            }
        });
    });
})(window.FloatingUIDOM);
