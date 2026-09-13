/**
 * The one thing every part of the canvas can check without asking the
 * server: whether a node's own config has left something required blank.
 * The deeper checks — a real event, a pattern PHP can compile — are the
 * server's own job at publish time; this is what lets a gap be seen the
 * moment it is left, not after "Publish" is pressed.
 *
 * @module     tool_flowboard/canvas/validation
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Whether one field is required right now — `requiredunless` lifts the
 * requirement when a sibling field already holds one of the named values,
 * the way a comparison that needs no value (`is empty`, `is not empty`)
 * frees its own value field.
 *
 * @param {Object} field
 * @param {Object} config
 * @returns {boolean}
 */
const isRequiredNow = (field, config) => {
    if (!field.required) {
        return false;
    }

    if (!field.requiredunless) {
        return true;
    }

    return !Object.entries(field.requiredunless).some(
        ([siblingKey, values]) => values.includes(config[siblingKey])
    );
};

/**
 * Every field of a node that is required right now and left empty.
 *
 * @param {Object} node
 * @param {Object} meta The node's own type metadata (from getNodeTypes()).
 * @returns {string[]} The config keys missing something.
 */
export const missingFields = (node, meta) => {
    if (meta === undefined) {
        return [];
    }

    return (meta.config || [])
        .filter((field) => isRequiredNow(field, node.config) && !String(node.config[field.key] || '').trim())
        .map((field) => field.key);
};

/**
 * Whether a node's own config has nothing required left blank.
 *
 * @param {Object} node
 * @param {Object} meta
 * @returns {boolean}
 */
export const isComplete = (node, meta) => missingFields(node, meta).length === 0;
