/**
 * WebBlok Form Wizard — renders the Inspector for the selected blok from its
 * schema JSON. Supports the 19 field types and conditional visibility (showIf).
 * Localized fields write to locale_config[locale]; the rest write to config.
 */
window.webblokFormWizard = function () {
    return {
        step: 0,

        steps(def) {
            if (!def || !def.schema || !def.schema.steps) return [];
            return def.schema.steps;
        },

        visible(field, config) {
            if (!field.showIf) return true;
            return (config[field.showIf.field] ?? null) === field.showIf.equals;
        },

        // The 19 supported field types — used to pick the input partial.
        types: [
            'text', 'textarea', 'richtext', 'number', 'toggle', 'select',
            'multiselect', 'radio', 'checkbox-group', 'color', 'image',
            'gallery', 'icon', 'link', 'date', 'range', 'repeater', 'code', 'hidden',
        ],

        addRepeaterRow(node, field) {
            node.config[field.key] = node.config[field.key] || [];
            const row = {};
            (field.fields || []).forEach((f) => { row[f.key] = ''; });
            node.config[field.key].push(row);
        },

        removeRepeaterRow(node, field, idx) {
            (node.config[field.key] || []).splice(idx, 1);
        },
    };
};
