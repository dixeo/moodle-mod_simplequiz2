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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * DOM helpers for Moodle HTML editors (TinyMCE default; legacy Atto still supported on M4).
 *
 * @module      mod_simplequiz2/editor_helpers
 * @copyright   2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['editor_tiny/editor', 'editor_tiny/options'], function(Tiny, Options) {

    /** @type {Object|null} TinyMCE base options from PHP (includes plugins). */
    let tinyBaseOptions = null;

    /**
     * @param {Object} options Base TinyMCE options from mod_form.php.
     */
    const setTinyBaseOptions = (options) => {
        tinyBaseOptions = options;
    };

    /**
     * @param {HTMLTextAreaElement} textarea
     * @return {Object|null}
     */
    const getReferenceEditor = (textarea) => {
        const refTextarea = document.querySelector(
            'fieldset[id^="id_question_header_"]:not([hidden]) textarea[id$="_text"]'
        );
        if (!refTextarea || refTextarea === textarea) {
            return null;
        }
        return Tiny.getInstanceForElement(refTextarea);
    };

    /**
     * Read draft item id from the companion hidden input rendered with deferred editor fields.
     *
     * @param {HTMLTextAreaElement} textarea
     * @return {number}
     */
    const getDraftItemIdFromForm = (textarea) => {
        const name = textarea.getAttribute('name');
        if (!name) {
            return 0;
        }
        const itemidName = name.replace(/\[text\]$/, '[itemid]');
        const input = document.querySelector(`input[name="${itemidName}"]`);
        return input ? parseInt(input.value, 10) || 0 : 0;
    };

    /**
     * Build full setup options for TinyMCE (partial options break plugin loading).
     *
     * @param {HTMLTextAreaElement} textarea
     * @return {Object|null}
     */
    const buildSetupOptions = (textarea) => {
        if (!tinyBaseOptions || !tinyBaseOptions.plugins) {
            return null;
        }
        const inst = Tiny.getInstanceForElement(textarea);
        const refInst = getReferenceEditor(textarea);
        return {
            css: tinyBaseOptions.css,
            context: inst ? Options.getContextId(inst) : tinyBaseOptions.context,
            draftitemid: inst ? Options.getDraftItemId(inst) : getDraftItemIdFromForm(textarea),
            filepicker: inst ? Options.getFilepickers(inst) : {},
            currentLanguage: refInst ? Options.getCurrentLanguage(refInst) : (tinyBaseOptions.currentLanguage || 'en'),
            language: refInst ? Options.getMoodleLang(refInst) : {},
            placeholderSelectors: [],
            plugins: tinyBaseOptions.plugins,
            branding: tinyBaseOptions.branding !== undefined ? tinyBaseOptions.branding : true,
            extended_valid_elements: tinyBaseOptions.extended_valid_elements || 'script[*],p[*],i[*]',
        };
    };

    /**
     * Match Moodle adjustEditorSize but enforce a usable minimum when the editor was inited hidden.
     *
     * @param {Object} editor TinyMCE instance
     * @param {HTMLTextAreaElement} textarea
     */
    const applyEditorSizeFix = (editor, textarea) => {
        const wrap = editor.getContainer()?.querySelector('.tox-sidebar-wrap');
        if (!wrap) {
            return;
        }
        const lineHeight = parseFloat(window.getComputedStyle(textarea).lineHeight) || 22;
        const rows = parseInt(textarea.getAttribute('rows'), 10) || 1;
        const fromRows = rows * lineHeight;
        const fromTarget = textarea.clientHeight || 0;
        const minEditing = textarea.id.includes('_answers_') ? 66 : 88;
        const expected = Math.max(fromTarget, fromRows, minEditing);
        wrap.style.height = `${expected}px`;
        textarea.removeAttribute('readonly');
        editor.mode.set('design');
        textarea.dispatchEvent(new Event('form:editorUpdated'));
    };

    /**
     * Remove TinyMCE for one textarea (Moodle removes on next tick to avoid Firefox errors).
     *
     * @param {HTMLTextAreaElement|null} textarea
     */
    const removeEditorForTextarea = (textarea) => {
        if (!textarea) {
            return;
        }
        delete textarea.dataset.simplequiz2ValidationBound;
        const editor = Tiny.getInstanceForElement(textarea);
        if (!editor) {
            return;
        }
        editor.setContent('');
        editor.save();
        setTimeout(() => {
            if (!editor.removed) {
                editor.remove();
            }
        }, 0);
    };

    /**
     * Tear down TinyMCE for all editors in a question slot (e.g. on delete or before re-add).
     *
     * @param {string} questionId Question index
     */
    const removeEditorsForQuestion = (questionId) => {
        removeEditorForTextarea(getTextarea(questionTextElementId(questionId)));
        for (let answerId = 0; answerId < 5; answerId++) {
            removeEditorForTextarea(getTextarea(answerElementId(questionId, answerId)));
        }
    };

    /**
     * Initialise TinyMCE on a textarea that does not yet have an instance.
     *
     * @param {HTMLTextAreaElement} textarea
     * @return {Promise<boolean>}
     */
    const initEditorForTextarea = async(textarea) => {
        if (Tiny.getInstanceForElement(textarea)) {
            return true;
        }
        const options = buildSetupOptions(textarea);
        if (!options) {
            return false;
        }
        try {
            const editor = await Tiny.setupForTarget(textarea, options);
            applyEditorSizeFix(editor, textarea);
            return true;
        } catch (e) {
            return false;
        }
    };

    /**
     * Bind validation refresh on a textarea (and its TinyMCE instance when present).
     *
     * @param {HTMLTextAreaElement|null} textarea
     * @param {Function} handler
     */
    const bindEditorValidationListener = (textarea, handler) => {
        if (!textarea || textarea.dataset.simplequiz2ValidationBound === '1') {
            return;
        }
        textarea.dataset.simplequiz2ValidationBound = '1';
        textarea.addEventListener('change', handler);
        textarea.addEventListener('input', handler);
        textarea.addEventListener('form:editorUpdated', handler);
        const editor = Tiny.getInstanceForElement(textarea);
        if (editor) {
            editor.on('input', handler);
            editor.on('change', handler);
            editor.on('SetContent', handler);
            editor.on('keyup', handler);
            editor.on('NodeChange', handler);
        }
    };

    /**
     * Initialise TinyMCE editors for one question after its fieldset/collapse is visible.
     *
     * @param {string} questionId Question index
     * @param {Function|null} [onAnswerChange] Called when an answer field changes (for form validation).
     * @return {Promise<void>}
     */
    const initEditorsForQuestion = async(questionId, onAnswerChange = null) => {
        const textareas = [];
        const questionText = getTextarea(questionTextElementId(questionId));
        if (questionText) {
            textareas.push(questionText);
        }
        for (let answerId = 0; answerId < 5; answerId++) {
            const answerTextarea = getTextarea(answerElementId(questionId, answerId));
            if (answerTextarea) {
                textareas.push(answerTextarea);
            }
        }

        for (const textarea of textareas) {
            const elementId = textarea.id.replace(/^id_/, '');
            const isAnswer = elementId.indexOf('_answers_') !== -1;

            if (getAttoContent(elementId)) {
                if (onAnswerChange && isAnswer) {
                    const atto = getAttoContent(elementId);
                    atto.addEventListener('input', onAnswerChange);
                    atto.addEventListener('change', onAnswerChange);
                }
                continue;
            }
            const existing = Tiny.getInstanceForElement(textarea);
            if (existing) {
                applyEditorSizeFix(existing, textarea);
            } else {
                await initEditorForTextarea(textarea);
            }
            if (onAnswerChange && isAnswer) {
                bindEditorValidationListener(textarea, onAnswerChange);
            }
        }
    };

    /**
     * Run editor init after the question collapse panel is visible (Bootstrap collapse).
     *
     * @param {string} questionId Question index
     * @param {HTMLElement} fieldset Question fieldset
     * @param {Function|null} [onAnswerChange] Called when an answer field changes
     * @return {Promise<void>}
     */
    const scheduleInitEditorsForQuestion = (questionId, fieldset, onAnswerChange = null) => new Promise((resolve) => {
        const panel = fieldset.querySelector('.collapse');
        const run = () => initEditorsForQuestion(questionId, onAnswerChange).then(resolve);

        if (!panel || panel.classList.contains('show')) {
            run();
            return;
        }

        panel.addEventListener('shown.bs.collapse', () => run(), {once: true});
    });

    /**
     * Strip empty paragraph/break markup then trim.
     *
     * @param {string} html Raw HTML
     * @return {string}
     */
    const stripEmptyHtml = (html) => {
        if (!html) {
            return '';
        }
        return html
            .replace(/<\/?p[^>]*>/gi, '')
            .replace(/<\/?br[^>]*>/gi, '')
            .trim();
    };

    /**
     * Moodle quickform element id without the id_ prefix.
     *
     * @param {string} questionId Question index
     * @return {string}
     */
    const questionTextElementId = (questionId) => 'questions' + questionId + '_text';

    /**
     * @param {string} questionId Question index
     * @param {string|number} answerId Answer index
     * @return {string}
     */
    const answerElementId = (questionId, answerId) => 'questions' + questionId + '_answers_' + answerId;

    /**
     * @param {string} elementId Element id without id_ prefix
     * @return {HTMLTextAreaElement|null}
     */
    const getTextarea = (elementId) => document.getElementById('id_' + elementId);

    /**
     * Legacy Atto contenteditable region.
     *
     * @param {string} elementId Element id without id_ prefix
     * @return {HTMLElement|null}
     */
    const getAttoContent = (elementId) => document.querySelector(
        '#id_' + elementId + 'editable.editor_atto_content'
    );

    /**
     * Read HTML from TinyMCE (textarea) or Atto (contenteditable).
     *
     * @param {string} elementId Element id without id_ prefix
     * @return {string}
     */
    const getEditorHtml = (elementId) => {
        const atto = getAttoContent(elementId);
        if (atto) {
            return atto.innerHTML;
        }
        const textarea = getTextarea(elementId);
        if (!textarea) {
            return '';
        }
        const editor = Tiny.getInstanceForElement(textarea);
        if (editor) {
            return editor.getContent();
        }
        return textarea.value;
    };

    /**
     * Write HTML into Atto and/or the underlying textarea (TinyMCE syncs from textarea).
     *
     * @param {string} elementId Element id without id_ prefix
     * @param {string} html HTML content
     */
    const setEditorHtml = (elementId, html) => {
        const atto = getAttoContent(elementId);
        if (atto) {
            atto.innerHTML = html;
        }
        const textarea = getTextarea(elementId);
        if (textarea) {
            textarea.value = html;
            textarea.dispatchEvent(new Event('change', {bubbles: true}));
        }
        const tinyInst = textarea ? Tiny.getInstanceForElement(textarea) : null;
        if (tinyInst) {
            tinyInst.setContent(html || '');
            tinyInst.save();
        }
    };

    /**
     * @param {string} elementId Element id without id_ prefix
     * @return {boolean}
     */
    const hasEditorContent = (elementId) => stripEmptyHtml(getEditorHtml(elementId)) !== '';

    /**
     * Whether the question text editor is in the DOM (Tiny textarea or Atto region).
     *
     * @param {string} questionId Question index
     * @return {boolean}
     */
    const isQuestionTextEditorReady = (questionId) => {
        const elementId = questionTextElementId(questionId);
        return getAttoContent(elementId) !== null || getTextarea(elementId) !== null;
    };

    /**
     * Plain text preview for the collapsed question header.
     *
     * @param {string} questionId Question index
     * @return {string}
     */
    const getQuestionTextPlain = (questionId) => {
        const atto = getAttoContent(questionTextElementId(questionId));
        if (atto) {
            return atto.innerText;
        }
        const textarea = getTextarea(questionTextElementId(questionId));
        if (!textarea) {
            return '';
        }
        const tmp = document.createElement('div');
        tmp.innerHTML = textarea.value;
        return tmp.innerText || '';
    };

    /**
     * CSS selector for answer editor fields for one question.
     *
     * @param {string} questionId Question index
     * @return {string}
     */
    const answerFieldsSelector = (questionId) =>
        'textarea[id^="id_questions' + questionId + '_answers_"], ' +
        'div[id^="id_questions' + questionId + '_answers_"].editor_atto_content';

    /**
     * Bind change listeners on all answer fields for a question.
     *
     * @param {string} questionId Question index
     * @param {Function} handler Callback
     */
    const bindAnswerFieldListeners = (questionId, handler) => {
        document.querySelectorAll(answerFieldsSelector(questionId)).forEach((field) => {
            if (field.classList.contains('editor_atto_content')) {
                field.addEventListener('change', handler);
                field.addEventListener('input', handler);
            }
        });
        for (let answerId = 0; answerId < 5; answerId++) {
            bindEditorValidationListener(getTextarea(answerElementId(questionId, answerId)), handler);
        }
    };

    return {
        stripEmptyHtml,
        questionTextElementId,
        answerElementId,
        getEditorHtml,
        setEditorHtml,
        hasEditorContent,
        isQuestionTextEditorReady,
        getQuestionTextPlain,
        answerFieldsSelector,
        bindAnswerFieldListeners,
        initEditorsForQuestion,
        scheduleInitEditorsForQuestion,
        removeEditorsForQuestion,
        setTinyBaseOptions,
    };
});
