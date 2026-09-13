/**
 * A small, self-contained autocomplete combobox: a text field that filters a
 * known list of suggestions as it is typed into, still accepting anything
 * else typed by hand — a value nobody has seen yet is not the same as a
 * mistake.
 *
 * @module     tool_flowboard/canvas/autocomplete
 * @copyright  2026 Didactika.org
 * @author     Hector Arrechea <hectorlazaroarrechea@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {number} No more than this many suggestions are shown at once. */
const MAX_SUGGESTIONS = 30;

/**
 * Builds one autocomplete field.
 *
 * @param {string} value The field's starting value.
 * @param {Array} options {value, label} pairs this field already knows of.
 * @param {string} placeholder
 * @returns {HTMLElement} A wrapper carrying both the input and its dropdown.
 *          `input`/`change` events dispatched on the field bubble up to it
 *          unchanged, and its own `.value` reads and writes straight through
 *          to the field — so it drops into `fieldRow()` exactly as a plain
 *          `<input>` would.
 */
export const build = (value, options, placeholder) => {
    const wrapper = document.createElement('div');

    wrapper.className = 'tool-flowboard-autocomplete';
    wrapper.innerHTML = `
        <input type="text" class="form-control form-control-sm" role="combobox"
               aria-expanded="false" aria-autocomplete="list" autocomplete="off">
        <ul class="tool-flowboard-autocomplete__list" role="listbox" hidden></ul>
    `;

    const input = wrapper.querySelector('input');
    const list = wrapper.querySelector('ul');
    let active = -1;

    input.value = value;
    input.placeholder = placeholder;

    const matches = (query) => {
        const needle = query.trim().toLowerCase();

        if (needle === '') {
            return options.slice(0, MAX_SUGGESTIONS);
        }

        return options
            .filter((option) => option.value.toLowerCase().includes(needle) || option.label.toLowerCase().includes(needle))
            .slice(0, MAX_SUGGESTIONS);
    };

    const close = () => {
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        active = -1;
    };

    const open = () => {
        const found = matches(input.value);

        list.innerHTML = '';

        if (found.length === 0) {
            close();

            return;
        }

        found.forEach((option, index) => {
            const li = document.createElement('li');

            li.setAttribute('role', 'option');
            li.dataset.index = String(index);
            li.dataset.value = option.value;
            li.innerHTML = option.label === option.value
                ? `<span class="tool-flowboard-autocomplete__value">${escapeHtml(option.value)}</span>`
                : `<span class="tool-flowboard-autocomplete__value">${escapeHtml(option.value)}</span>`
                    + `<span class="tool-flowboard-autocomplete__label">${escapeHtml(option.label)}</span>`;

            li.addEventListener('mousedown', (event) => {
                // mousedown, not click: fires before the input's own blur
                // closes the list, so the selection is not lost to it.
                event.preventDefault();
                choose(option.value);
            });

            list.appendChild(li);
        });

        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        active = -1;
    };

    const choose = (chosen) => {
        input.value = chosen;
        close();
        input.dispatchEvent(new Event('input'));
        input.dispatchEvent(new Event('change'));
    };

    const highlight = (index) => {
        list.querySelectorAll('li').forEach((li) => li.classList.remove('tool-flowboard-autocomplete__item--active'));

        const items = list.querySelectorAll('li');

        if (items[index]) {
            items[index].classList.add('tool-flowboard-autocomplete__item--active');
            items[index].scrollIntoView({block: 'nearest'});
        }
    };

    input.addEventListener('input', open);
    input.addEventListener('focus', open);

    input.addEventListener('keydown', (event) => {
        const items = list.querySelectorAll('li');

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            active = Math.min(active + 1, items.length - 1);
            highlight(active);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            active = Math.max(active - 1, 0);
            highlight(active);
        } else if (event.key === 'Enter' && active >= 0 && items[active]) {
            event.preventDefault();
            choose(items[active].dataset.value);
        } else if (event.key === 'Escape') {
            close();
        }
    });

    input.addEventListener('blur', () => window.setTimeout(close, 100));

    Object.defineProperty(wrapper, 'value', {
        get: () => input.value,
        set: (next) => {
            input.value = next;
        },
    });
    Object.defineProperty(wrapper, 'id', {
        get: () => input.id,
        set: (next) => {
            input.id = next;
        },
    });

    return wrapper;
};

/**
 * Text as HTML would show it, nothing more.
 *
 * @param {string} text
 * @returns {string}
 */
const escapeHtml = (text) => {
    const div = document.createElement('div');

    div.textContent = text;

    return div.innerHTML;
};
