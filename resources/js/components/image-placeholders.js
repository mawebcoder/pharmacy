/**
 * Clears the placeholder (dominant colour or ThumbHash) behind an image once the photo has loaded (x-media::picture
 * marks such images with data-placeholder). A photo contained in a wider well (product cards) would otherwise show
 * coloured bars beside it. Covers images already loaded when this runs and those added later (wire:navigate, morphs).
 */
function clear(image) {
    image.style.removeProperty('background-color');
    image.style.removeProperty('background-image');
    image.style.removeProperty('background-size');
    image.removeAttribute('data-placeholder');
}

function clearLoaded(root = document) {
    root.querySelectorAll('img[data-placeholder]').forEach((image) => image.complete && image.naturalWidth > 0 && clear(image));
}

export default function imagePlaceholders() {
    // Load events do not bubble, so listen while they go down (capture).
    document.addEventListener('load', (event) => event.target instanceof HTMLImageElement && event.target.hasAttribute('data-placeholder') && clear(event.target), true);
    clearLoaded();
    document.addEventListener('livewire:navigated', () => clearLoaded());
}
