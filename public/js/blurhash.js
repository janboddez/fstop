(() => {
    document.querySelectorAll('[data-blurhash]').forEach((element) => {
        const canvas = document.createElement('canvas');
        canvas.setAttribute('width', 32);
        canvas.setAttribute('height', 32);

        const context = canvas.getContext('2d');
        const imageData = context.createImageData(32, 32);
        const pixels = blurhash.decode(element.dataset.blurhash, 32, 32);

        imageData.data.set(pixels);
        context.putImageData(imageData, 0, 0);

        element.parentElement.prepend(canvas);
    });
})();
