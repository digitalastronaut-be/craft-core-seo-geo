import "./admin.scss";

/**
 * StructuredDataField settings page: schema.org type picker, "Add Property" picker, and the
 * properties table they drive together.
 *
 * The properties table's rows are never managed by Craft's own editable-table JS
 * (`allowAdd`/`allowDelete`/`allowReorder` are all `false` server-side, see
 * `StructuredDataField::getSettingsHtml()`) - rows are added/removed entirely here, so there's
 * no dependency on `Craft.EditableTable`'s internal (undocumented) row-management API for a
 * table whose row count is driven by the registry rather than fixed per type.
 *
 * Targets fields by the `fieldClass` marker on their outer wrapper, not by `id` - the field
 * settings UI namespaces raw `id`s depending on where/how it's rendered, but it never rewrites
 * classes (confirmed directly in the rendered DOM while building this).
 *
 * Uses the page's own global jQuery (`window.jQuery`), not a bundled import - for the
 * delegated event binding below (Selectize's selection change only propagates through jQuery's
 * own event system, not as a real bubbling native DOM event - confirmed empirically), and
 * because Selectize attaches its instance to an element via that same jQuery's `.data()` store
 * (`_includes/forms/selectize.twig`: `$select.data('selectize')`), so reaching it later has to
 * go through the exact same jQuery instance that created it, not a separate bundled copy.
 */

const TYPE_FIELD_CLASS = "core-seo-geo-schema-type-field";
const ADD_PROPERTY_FIELD_CLASS = "core-seo-geo-add-property-field";
const PROPERTIES_TABLE_CLASS = "core-seo-geo-properties-table";
const REMOVE_BUTTON_CLASS = "core-seo-geo-remove-property";

let registryPromise = null;

/**
 * @returns {Promise<{properties: object, types: object}>}
 */
function getSchemaRegistry() {
    if (registryPromise === null) {
        registryPromise = fetch(window.CoreSeoGeoSchemaRegistryUrl).then((response) => response.json());
    }

    return registryPromise;
}

/**
 * The field-settings namespace prefix (e.g.
 * `types[digitalastronaut-craftcoreseogeo-fields-StructuredDataField]`) every input on this
 * settings page is posted under - the field settings UI keeps every field type's settings HTML
 * mounted at once (for the Field Type switcher), so a bare `properties[x][template]` name would
 * post to the wrong place entirely and never actually save. Derived from the type select's own
 * `name` (always present, unlike a properties row) rather than hardcoded, so this doesn't break
 * if Craft's namespacing convention or this class's own name ever changes.
 *
 * @returns {string}
 */
function fieldNamePrefix() {
    const typeSelect = document.querySelector(`.${TYPE_FIELD_CLASS} select`);
    return typeSelect ? typeSelect.name.replace(/\[type\]$/, "") : "";
}

/**
 * @param {Element} table
 * @returns {string[]} Property names, read from each row's "...[properties][<name>][template]"
 * input - rather than tracked separately, so it's always in sync with the real DOM/form state.
 */
function rowPropertyNames(table) {
    return Array.from(table.querySelectorAll("textarea"))
        .map((textarea) => textarea.name.match(/\[properties\]\[([^\]]+)\]/))
        .filter((match) => match !== null)
        .map((match) => match[1]);
}

/**
 * Mirrors `Cp::editableTableFieldHtml()`'s own cell markup closely enough to render/behave
 * identically to a server-rendered row - "heading" cells are plain text, "multiline" is a
 * `<textarea>`. The remove button matches `StructuredDataField::_removeButtonHtml()`.
 *
 * @param {string} property
 * @returns {HTMLTableRowElement}
 */
function buildRow(property) {
    const tr = document.createElement("tr");

    const fieldTd = document.createElement("td");
    fieldTd.className = "heading-cell";
    fieldTd.textContent = property;
    tr.appendChild(fieldTd);

    const templateTd = document.createElement("td");
    templateTd.className = "multiline-cell textual";
    const textarea = document.createElement("textarea");
    textarea.name = `${fieldNamePrefix()}[properties][${property}][template]`;
    textarea.rows = 1;
    templateTd.appendChild(textarea);
    tr.appendChild(templateTd);

    const previewTd = document.createElement("td");
    previewTd.className = "heading-cell";
    const placeholder = document.createElement("span");
    placeholder.className = "light";
    placeholder.textContent = Craft.t("core-seo-geo", "Save to calculate");
    previewTd.appendChild(placeholder);
    tr.appendChild(previewTd);

    const removeTd = document.createElement("td");
    removeTd.className = "heading-cell thin action";
    const button = document.createElement("button");
    button.type = "button";
    button.className = `delete icon ${REMOVE_BUTTON_CLASS}`;
    button.title = Craft.t("core-seo-geo", "Remove");
    button.setAttribute("aria-label", Craft.t("core-seo-geo", "Remove {property}", {property}));
    removeTd.appendChild(button);
    tr.appendChild(removeTd);

    return tr;
}

/**
 * @returns {Element|null}
 */
function propertiesTable() {
    return document.querySelector(`.${PROPERTIES_TABLE_CLASS} table`);
}

/**
 * @returns {{selectize: object, field: Element}|null}
 */
function addPropertySelectize() {
    const field = document.querySelector(`.${ADD_PROPERTY_FIELD_CLASS}`);
    if (!field) return null;

    const selectize = window.jQuery(field.querySelector("select")).data("selectize");
    return selectize ? {selectize, field} : null;
}

/**
 * Repopulates the "Add Property" picker for `typeName`, excluding whichever properties already
 * have a row in `table`.
 *
 * @param {string} typeName
 * @param {Element} table
 */
function refreshAddPropertyOptions(typeName, table) {
    const addProperty = addPropertySelectize();
    if (!addProperty) return;

    addProperty.selectize.clearOptions();
    addProperty.selectize.setValue("", true);

    if (typeName === "") return;

    getSchemaRegistry().then((registry) => {
        const type = registry.types[typeName];
        if (!type) return;

        const alreadyAdded = new Set(rowPropertyNames(table));

        type.properties
            .filter((name) => !alreadyAdded.has(name))
            .forEach((name) => addProperty.selectize.addOption({value: name, text: name}));

        addProperty.selectize.refreshOptions(false);
    });
}

// Type changed -> repopulate the "Add Property" picker for the new type.
window.jQuery(document).on("change", `.${TYPE_FIELD_CLASS} select`, (event) => {
    const table = propertiesTable();
    if (table) refreshAddPropertyOptions(event.target.value, table);
});

// A property was picked to add -> append a new row, remove that option from the picker (can't
// add the same property twice), and reset the picker back to blank.
window.jQuery(document).on("change", `.${ADD_PROPERTY_FIELD_CLASS} select`, (event) => {
    const property = event.target.value;
    if (property === "") return;

    const table = propertiesTable();
    if (!table) return;

    table.querySelector("tbody").appendChild(buildRow(property));
    table.classList.remove("hidden");

    const addProperty = addPropertySelectize();
    if (!addProperty) return;

    addProperty.selectize.removeOption(property);
    addProperty.selectize.setValue("", true);
    addProperty.selectize.refreshOptions(false);
});

// A row's remove button was clicked -> delete the row, then offer that property again in the
// "Add Property" picker, provided it's still valid for the currently-selected type (it might
// not be, if the type was switched after the row was added - that row is left alone either way,
// per the same "don't silently drop existing rows" choice `beforeSave()` doesn't make either,
// only re-validating on an actual save).
window.jQuery(document).on("click", `.${REMOVE_BUTTON_CLASS}`, (event) => {
    const row = event.target.closest("tr");
    const textarea = row?.querySelector("textarea");
    const match = textarea?.name.match(/\[properties\]\[([^\]]+)\]/);
    const property = match ? match[1] : null;

    row?.remove();

    if (property === null) return;

    const typeName = document.querySelector(`.${TYPE_FIELD_CLASS} select`)?.value ?? "";
    if (typeName === "") return;

    getSchemaRegistry().then((registry) => {
        const type = registry.types[typeName];
        if (!type || !type.properties.includes(property)) return;

        const addProperty = addPropertySelectize();
        if (!addProperty) return;

        addProperty.selectize.addOption({value: property, text: property});
        addProperty.selectize.refreshOptions(false);
    });
});
