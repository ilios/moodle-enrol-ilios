// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
//

/**
 * Attaches event handlers for dynamic data loading to dropdown-selects in the new instance form.
 *
 * @module enrol_ilios/main
 * @copyright The Regents of the University of California
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Url from 'core/url';
import Notification from 'core/notification';

/**
 * Load initial state.
 *
 * @param {string} courseId The enrollment target course ID.
 */
export const init = (courseId) => {

    /**
     * Path to the callback script.
     * @type {string}
     */
    const CALLBACK_SCRIPT = '/enrol/ilios/ajax.php';

    /**
     * The name of the usertype dropdown selector in this form.
     * @type {string}
     */
    const USERTYPE_SELECTOR = 'selectusertype';

    /**
     * The names of the dropdown selectors in this form.
     * They are listed in hierarchical order, which is relevant for cascading state management.
     * @type {String[]}
     */
    const SELECTORS = [
        'selectschool',
        'selectprogram',
        'selectcohort',
        'selectlearnergroup',
        'selectsubgroup',
    ];

    /**
     * Returns the HTML Dropdown element for a given selector name.
     * @param {String} selector The selector name.
     * @returns {HTMLSelectElement} The HTML select element.
     */
    const getElementForSelector = function(selector) {
        return document.getElementById(`id_${selector}`);
    };

    /**
     * Returns the name of the callback action for a given selector.
     * @param {String} selector The selector name.
     * @returns {String} The name of the callback action.
     */
    const getActionForSelector = function(selector) {
        // Construct and return the action name based on the dropdown element's name for the given selector.
        const name = getElementForSelector(selector).name;
        return `get${name}options`;
    };

    /**
     * Returns a list of sub-selectors for a given selector.
     * @param {String} selector The selector name.
     * @returns {String[]} The list of sub-selector names.
     */
    const getSubSelectors = function(selector) {
        // Since the selectors array is hierarchical in nature, we can just grab and return a sub-list
        // containing all selector names following the given one.
        return SELECTORS.slice(SELECTORS.indexOf(selector) + 1);
    };

    /**
     * Resets and locks the dropdown for a given selector.
     * @param {String} selector The selector name.
     */
    const resetSelector = function(selector) {
        // Get the dropdown element for the given selector.
        const element = getElementForSelector(selector);
        // Reset the dropdown value to the first option.
        element.selectedIndex = 0;
        // Lock the dropdown.
        element.disabled = true;
        // Remove all options from the dropdown element.
        const options = element.querySelectorAll('option:not(:first-child)');
        options.forEach((option) => option.remove());
    };

    /**
     * Resets and locks the dropdowns for a given list of selectors.
     * @param {String[]} selectors A list of selector names.
     */
    const resetSelectors = function(selectors) {
        selectors.forEach((selector) => resetSelector(selector));
    };

    /**
     * Appends the given options to the given selector's dropdown element as HTML elements.
     * @param {String} selector The selector name.
     * @param {Object} options A map of key/value pairs.
     */
    const populateSelector = function(selector, options) {
        const selectElement = getElementForSelector(selector);
        // Convert object to array so we can sort values.
        const temp = [];
        for (let key in options) {
            const value = options[key];
            temp.push({key, value});
        }
        // Sort and append the options to the dropdown.
        temp.sort((a, b) => {
            // Compare values first.
            let rhett = a.value.localeCompare(b.value);
            if (rhett) {
                return rhett;
            }
            // Fallback to key comparison if values are the same.
            if (a.key > b.key) {
                return 1;
            } else if (a.key < b.key) {
                return -1;
            }
            return 0;
        }).forEach((option) => {
            const optionEl = new Option(option.value, option.key);
            selectElement.appendChild(optionEl);
        });
    };

    /**
     * Retrieves the value of the user type dropdown.
     * @returns {String} The selected user type.
     */
    const getUserType = function() {
        return getElementForSelector(USERTYPE_SELECTOR).value;
    };

    /**
     * Creates a full URL to the callback script with given parameters.
     * @param {String} id The course ID.
     * @param {String} action The name of the server-side callback action.
     * @param {String} filterid The given value to filter on.
     * @param {String} usertype The given user type value.
     * @returns {String} The generated callback URL.
     */

    const buildCallbackUrl = function(id, action, filterid, usertype) {
        return Url.relativeUrl(CALLBACK_SCRIPT, {id, action, filterid, usertype}, true);
    };

    /**
     * Registers the on-change handler on the dropdown corresponding to the given selector.
     * @param {String} selector The selector name.
     */
    const registerSelectorChangeHandler = function(selector) {
        const subSelectors = getSubSelectors(selector);
        // If there are no sub-selectors for the given selector, then we're already done here.
        if (!subSelectors.length) {
             return;
        }
        const nextSelector = subSelectors[0];
        const element = getElementForSelector(selector);
        const action = getActionForSelector(nextSelector);
        element.addEventListener('change', async(e) => {
            // Reset and lock all sub-selectors of the given selector.
            resetSelectors(subSelectors);
            const value = e.currentTarget.value;

            // The values in the dropdowns are themselves encoded "id:name" pairs.
            // Only the default/blank first option does not adhere to this pattern.
            // We only make a XHR callback if the user selected a non-blank option from the given dropdown.
            if (-1 === value.indexOf(':')) {
                return;
            }
            const filter = value.split(':')[0];
            // Get the currently selected user type.
            const userType = getUserType();
            // Build the callback URL.
            const url = buildCallbackUrl(courseId, action, filter, userType);
            // Fetch data from the callback script and populate the dropdown for the next selector with it.
            try {
                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error(`Request failed with response status: ${response.status}`);
                }
                const json = await response.json();
                populateSelector(nextSelector, json.response);
                // Finally, re-enable the next selector.
                getElementForSelector(nextSelector).disabled = false;
            } catch (error) {
                await Notification.exception(error);
            }
        });
    };

    // Wire up the event handlers to the selector elements.
    SELECTORS.forEach((selector) => {
        registerSelectorChangeHandler(selector);
    });
};
