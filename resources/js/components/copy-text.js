/**
 * $copy(text): copies text and resolves true when it worked (owner, 2026-11-15: the order number must be copyable).
 * navigator.clipboard exists only on secure pages (https or localhost), so over plain http — a phone on the local
 * network — it falls back to a hidden textarea and document.execCommand('copy').
 * <button x-on:click="$copy('PH-140508-000024').then((ok) => copied = ok)">
 */
export default function copyText(text) {
    if (window.isSecureContext && navigator.clipboard) {
        return navigator.clipboard.writeText(text).then(() => true, () => fallback(text));
    }

    return Promise.resolve(fallback(text));
}

function fallback(text) {
    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.cssText = 'position:fixed;inset-inline-start:-9999px;opacity:0';
    document.body.appendChild(field);
    field.select();
    field.setSelectionRange(0, text.length);

    let copied = false;

    try {
        copied = document.execCommand('copy');
    } catch {
        copied = false;
    }

    field.remove();

    return copied;
}
