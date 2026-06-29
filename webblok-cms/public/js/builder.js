/**
 * WebBlok Builder — Alpine.js store.
 *
 * Owns the in-memory blok tree for one page, talks to the BuilderController
 * endpoints (tree fetch / blok create / tree persist / revision), and drives
 * the Inspector Form Wizard. SortableJS (builder-dnd.js) mutates the tree and
 * calls store.persist() after each drag.
 */
window.webblokBuilder = function (config) {
    return {
        pageId: config.pageId,
        locale: config.locale || 'en',
        csrf: config.csrf,
        endpoints: config.endpoints,
        definitions: config.definitions || [],

        sections: { header: [], body: [], footer: [], sidebar: [] },
        selectedId: null,
        device: 'desktop',
        saving: false,
        dirty: false,
        lastSaved: null,

        async init() {
            await this.loadTree();
            window.addEventListener('beforeunload', (e) => {
                if (this.dirty) { e.preventDefault(); e.returnValue = ''; }
            });
        },

        async loadTree() {
            const res = await fetch(`${this.endpoints.tree}?locale=${this.locale}`, {
                headers: { 'Accept': 'application/json' },
            });
            const json = await res.json();
            this.sections = json.data.sections;
            this.$nextTick(() => window.WBRebindDnd && window.WBRebindDnd(this));
        },

        definitionFor(blokKey) {
            return this.definitions.find((d) => d.blok_key === blokKey) || null;
        },

        /** Find a node by id anywhere in the tree. */
        findNode(id, nodes = null) {
            nodes = nodes || this.allRoots();
            for (const node of nodes) {
                if (node.id === id) return node;
                if (node.children && node.children.length) {
                    const found = this.findNode(id, node.children);
                    if (found) return found;
                }
            }
            return null;
        },

        allRoots() {
            return [...this.sections.header, ...this.sections.body,
                    ...this.sections.sidebar, ...this.sections.footer];
        },

        get selected() {
            return this.selectedId ? this.findNode(this.selectedId) : null;
        },

        selectBlok(id) { this.selectedId = id; },

        /** Add a blok from the palette to a section (top level). */
        async addBlok(blokKey, section = 'body') {
            const def = this.definitionFor(blokKey);
            const sortOrder = this.sections[section].length;
            const res = await fetch(this.endpoints.storeBlok, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                },
                body: JSON.stringify({
                    blok_key: blokKey, section, sort_order: sortOrder,
                    parent_id: null, slot_name: null,
                }),
            });
            if (!res.ok) { alert('Could not add blok (rule violation).'); return; }
            const json = await res.json();
            this.sections[section].push({
                id: json.data.id, blok_key: blokKey, section,
                slot_name: null, sort_order: sortOrder, is_visible: true,
                config: def ? (def.default_config || {}) : {}, children: [],
            });
            this.selectBlok(json.data.id);
            this.markDirty();
            this.$nextTick(() => window.WBRebindDnd && window.WBRebindDnd(this));
        },

        /** Update a config field on the selected blok. */
        updateConfig(key, value) {
            const node = this.selected;
            if (!node) return;
            node.config = node.config || {};
            node.config[key] = value;
            this.markDirty();
        },

        removeBlok(id) {
            for (const section of Object.keys(this.sections)) {
                this.sections[section] = this.removeFrom(this.sections[section], id);
            }
            if (this.selectedId === id) this.selectedId = null;
            this.markDirty();
        },

        removeFrom(nodes, id) {
            return nodes.filter((n) => n.id !== id).map((n) => {
                if (n.children) n.children = this.removeFrom(n.children, id);
                return n;
            });
        },

        markDirty() {
            this.dirty = true;
            clearTimeout(this._t);
            this._t = setTimeout(() => this.persist(), 1200);
        },

        async persist() {
            if (this.saving) return;
            this.saving = true;
            try {
                await fetch(this.endpoints.persistTree, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrf,
                    },
                    body: JSON.stringify({ sections: this.sections }),
                });
                this.dirty = false;
                this.lastSaved = new Date().toLocaleTimeString();
            } finally {
                this.saving = false;
            }
        },

        async saveRevision() {
            await this.persist();
            await fetch(this.endpoints.revision, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
            });
            alert('Snapshot saved. You can restore to this point later.');
        },

        setDevice(d) { this.device = d; },
        deviceWidth() {
            return { desktop: '100%', tablet: '768px', mobile: '390px' }[this.device];
        },
    };
};
