/**
 * The side panel: what the selected node is configured to do, built from
 * nothing but that node type's own schema — the inspector has no idea what a
 * "condition" or a "forum" is, only fields and their shapes.
 *
 * @module     tool_flowboard/canvas/inspector
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as State from './state';
import {str} from './strings';
import * as Autocomplete from './autocomplete';
import {missingFields} from './validation';

/**
 * Draws the panel for whatever is selected right now: a node's own fields, a
 * sticky note's reminder that it is edited on the canvas itself, or nothing
 * at all.
 *
 * @param {HTMLElement} root
 * @param {Function} redraw
 */
export const render = (root, redraw) => {
    const selection = State.getSelection();

    root.innerHTML = '';

    if (selection === null) {
        root.innerHTML = '<p class="tool-flowboard-inspector__empty">Select a node to configure it.</p>';

        return;
    }

    if (selection.kind === 'comment') {
        root.innerHTML = '<p class="tool-flowboard-inspector__empty">Edit the note directly on the canvas.</p>';

        return;
    }

    const node = State.nodeByKey(selection.key);
    const meta = State.getNodeTypes()[node.type];

    if (node === null || meta === undefined) {
        return;
    }

    const heading = document.createElement('h4');

    heading.className = 'tool-flowboard-inspector__heading';
    heading.textContent = meta.label;
    root.appendChild(heading);

    root.appendChild(buildFieldsForm(node, meta, redraw));

    const remove = document.createElement('button');

    remove.type = 'button';
    remove.className = 'btn btn-outline-danger btn-sm tool-flowboard-inspector__remove';
    remove.textContent = 'Remove node';
    remove.addEventListener('click', () => {
        State.removeNode(node.key);
        redraw();
    });
    root.appendChild(remove);
};

/**
 * Every one of a node type's own config fields, as labelled controls — what
 * both the canvas's own panel and the D12 list view build a node's own
 * configuration from, so neither ever drifts from what the other shows.
 *
 * @param {Object} node
 * @param {Object} meta
 * @param {Function} redraw
 * @param {string} namespace Which panel is drawing these fields — the canvas
 *        inspector and the D12 list view both build the very same node's
 *        fields at once (one of them merely hidden, not gone), so each needs
 *        its own ids or the two would collide as duplicates in the same page.
 * @returns {HTMLElement}
 */
export const buildFieldsForm = (node, meta, redraw, namespace = 'inspector') => {
    const form = document.createElement('div');

    form.className = 'tool-flowboard-inspector__form';
    (meta.config || []).forEach((field) => form.appendChild(fieldRow(node, field, redraw, namespace)));

    return form;
};

/**
 * One config field, as a labelled control plus, where the field allows it, a
 * "map from…" picker for referencing an earlier field instead of typing one.
 *
 * @param {Object} node
 * @param {Object} field
 * @param {Function} redraw
 * @param {string} namespace
 * @returns {HTMLElement}
 */
const fieldRow = (node, field, redraw, namespace) => {
    const row = document.createElement('div');

    row.className = 'tool-flowboard-inspector__field';

    const label = document.createElement('label');

    label.textContent = str(field.label) + (field.required ? ' *' : '');
    label.setAttribute('for', `flowboard-field-${namespace}-${node.key}-${field.key}`);
    row.appendChild(label);

    const control = buildControl(node, field);
    const error = document.createElement('div');

    error.className = 'tool-flowboard-inspector__error';

    const refreshError = () => {
        const missing = missingFields({...node, config: {...node.config, [field.key]: control.value}}, {
            config: [field],
        });

        error.textContent = missing.length > 0 ? str('flow:error_required') : '';
    };

    control.id = `flowboard-field-${namespace}-${node.key}-${field.key}`;
    control.addEventListener('input', () => {
        State.setNodeConfig(node.key, {...node.config, [field.key]: control.value});
        refreshError();
    });
    control.addEventListener('change', () => {
        State.setNodeConfig(node.key, {...node.config, [field.key]: control.value});
        refreshError();
        redraw();
    });
    row.appendChild(control);

    if (field.referenceable) {
        row.appendChild(referencePicker(node, control));
    }

    refreshError();
    row.appendChild(error);

    return row;
};

/**
 * The control itself, for one field's own type.
 *
 * @param {Object} node
 * @param {Object} field
 * @returns {HTMLElement}
 */
const buildControl = (node, field) => {
    const value = node.config[field.key] || '';

    if (field.type === 'select') {
        const select = document.createElement('select');
        const entries = Object.entries(field.options || {});

        select.className = 'custom-select custom-select-sm';
        entries.forEach(([optionValue, optionLabel]) => {
            const option = document.createElement('option');

            option.value = optionValue;
            option.textContent = str(optionLabel);
            option.selected = optionValue === value;
            select.appendChild(option);
        });

        // A <select> always shows some option selected, even one nobody
        // chose — the browser defaults to the first. Leaving the node's own
        // config empty until a real "change" fires would let a required
        // field look filled in, and stay invalid, for as long as its
        // already-displayed default happens to be the answer someone wants.
        if (value === '' && entries.length > 0) {
            State.setNodeConfig(node.key, {...node.config, [field.key]: entries[0][0]});
        }

        return select;
    }

    if (field.type === 'event') {
        const options = Object.entries(State.getEvents()).map(([eventname, info]) => ({
            value: eventname,
            label: `${info.name} — ${eventname}`,
        }));

        return Autocomplete.build(value, options, 'Search by name or class…');
    }

    if (field.type === 'eventfield') {
        const eventname = triggerEventName();
        const known = eventname === null ? [] : State.getEventFields(eventname);
        const options = known.map((path) => ({value: path, label: path}));

        return Autocomplete.build(value, options, 'A field from the event, e.g. relateduserid');
    }

    const input = document.createElement('input');

    input.type = 'text';
    input.className = 'form-control form-control-sm';
    input.value = value;

    return input;
};

/**
 * The event the flow's own trigger is drawn against, if it has one yet.
 *
 * @returns {?string}
 */
const triggerEventName = () => {
    const trigger = State.getGraph().nodes.find((node) => {
        const meta = State.getNodeTypes()[node.type];

        return meta !== undefined && meta.istrigger;
    });

    return trigger ? (trigger.config.eventname || null) : null;
};

/**
 * The "map from…" affordance: every field an earlier node produced, or the
 * triggering event itself carries, one click away from filling the control.
 *
 * @param {Object} node
 * @param {HTMLInputElement} control
 * @returns {HTMLElement}
 */
const referencePicker = (node, control) => {
    const wrapper = document.createElement('div');
    const select = document.createElement('select');

    wrapper.className = 'tool-flowboard-inspector__reference';
    select.className = 'custom-select custom-select-sm';

    const placeholder = document.createElement('option');

    placeholder.textContent = '🔗 Map from…';
    placeholder.value = '';
    select.appendChild(placeholder);

    const eventname = triggerEventName();

    if (eventname !== null) {
        optionGroup(select, 'The triggering event', State.getEventFields(eventname).map((path) => ({
            value: `{{event:${path}}}`,
            label: path,
        })));
    }

    const produced = [];

    State.ancestorsOf(node.key).forEach((ancestor) => {
        const meta = State.getNodeTypes()[ancestor.type];

        (meta ? meta.produces : []).forEach((key) => produced.push({value: `{{context:${key}}}`, label: key}));
    });

    if (produced.length > 0) {
        optionGroup(select, 'An earlier node', produced);
    }

    select.addEventListener('change', () => {
        if (select.value === '') {
            return;
        }

        control.value = select.value;
        control.dispatchEvent(new Event('change'));
        select.value = '';
    });

    wrapper.appendChild(select);

    return wrapper;
};

/**
 * One labelled group of options inside a select.
 *
 * @param {HTMLSelectElement} select
 * @param {string} label
 * @param {Object[]} entries {value, label}.
 */
const optionGroup = (select, label, entries) => {
    if (entries.length === 0) {
        return;
    }

    const group = document.createElement('optgroup');

    group.label = label;
    entries.forEach(({value, label: entryLabel}) => {
        const option = document.createElement('option');

        option.value = value;
        option.textContent = entryLabel;
        group.appendChild(option);
    });
    select.appendChild(group);
};
