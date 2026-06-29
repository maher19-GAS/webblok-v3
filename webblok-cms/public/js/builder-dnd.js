/**
 * WebBlok Builder — drag & drop wiring (SortableJS).
 *
 * Binds every canvas section + slot zone as a Sortable list. Dragging from the
 * palette adds a blok; dragging within/between canvas zones reorders and
 * re-parents. After any structural change it flips the store dirty so the
 * debounced autosave persists the authoritative tree (server re-validates
 * depth + slot limits).
 */
(function () {
    const instances = [];

    function destroyAll() {
        while (instances.length) {
            const s = instances.pop();
            try { s.destroy(); } catch (e) { /* noop */ }
        }
    }

    function bindZone(el, store) {
        const sortable = Sortable.create(el, {
            group: 'webblok-bloks',
            animation: 150,
            handle: '[data-drag-handle]',
            ghostClass: 'wb-ghost',
            onEnd() {
                store.markDirty();
            },
        });
        instances.push(sortable);
    }

    function bindPalette(el, store) {
        const sortable = Sortable.create(el, {
            group: { name: 'webblok-bloks', pull: 'clone', put: false },
            sort: false,
            animation: 150,
            onClone() { /* visual clone only */ },
        });
        instances.push(sortable);
    }

    window.WBRebindDnd = function (store) {
        destroyAll();
        document.querySelectorAll('[data-canvas-zone]').forEach((el) => bindZone(el, store));
        const palette = document.querySelector('[data-palette]');
        if (palette) bindPalette(palette, store);
    };
})();
