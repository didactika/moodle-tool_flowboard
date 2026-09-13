/**
 * Every lang string the node types themselves named — a field's label, an
 * option's own text — resolved once, so the inspector can read them back
 * synchronously while it draws.
 *
 * @module     tool_flowboard/canvas/strings
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getStrings} from 'core/str';

const resolved = new Map();

/** @type {string[]} Needed by the inspector itself, whatever the node types turn out to be. */
const ALWAYS_NEEDED = ['flow:error_required'];

/**
 * Every `label`/`options` value a set of node type schemas names, without
 * repeats.
 *
 * @param {Object} nodeTypes
 * @returns {string[]}
 */
const keysIn = (nodeTypes) => {
    const keys = new Set(ALWAYS_NEEDED);

    Object.values(nodeTypes).forEach((meta) => {
        (meta.config || []).forEach((field) => {
            keys.add(field.label);
            Object.values(field.options || {}).forEach((optionLabel) => keys.add(optionLabel));
        });
    });

    return Array.from(keys);
};

/**
 * Resolves every string the node types will need, once, before the canvas
 * draws its first inspector panel.
 *
 * @param {Object} nodeTypes
 * @returns {Promise<void>}
 */
export const preload = async(nodeTypes) => {
    const keys = keysIn(nodeTypes);

    if (keys.length === 0) {
        return;
    }

    const values = await getStrings(keys.map((key) => ({key, component: 'tool_flowboard'})));

    keys.forEach((key, index) => resolved.set(key, values[index]));
};

/**
 * A string already resolved by {@see preload}, or its own key when it was
 * not one of them — which is what a bare, non-lang-string label (this
 * plugin has none today, but a node from elsewhere might) falls back to.
 *
 * @param {string} key
 * @returns {string}
 */
export const str = (key) => resolved.get(key) || key;
