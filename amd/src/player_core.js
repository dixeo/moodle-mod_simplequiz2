// This file is part of Moodle - http://moodle.org/
//
// @module     mod_simplequiz2/player_core
// @copyright  2026 Dixeo (contact@dixeo.com)
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

define(['core/str'], function(str) {

    const LANG_STRINGS = [
        'aria_question_text',
        'aria_answer_text',
        'aria_next',
        'aria_video',
        'aria_image',
        'aria_math',
        'aria_audio',
        'aria_no_description',
    ];

    /**
     * Shared player utilities for activity and embed modes.
     */
    const playerCore = {
        stringCache: {},

        /**
         * Preload accessibility strings.
         *
         * @return {Promise<Object>}
         */
        loadStrings: async function() {
            const modStrings = LANG_STRINGS.map(function(key) {
                return {key: key, component: 'mod_simplequiz2'};
            });
            const loaded = await str.get_strings(modStrings);
            LANG_STRINGS.forEach(function(key, index) {
                playerCore.stringCache[key] = loaded[index];
            });
            return playerCore.stringCache;
        },

        /**
         * @param {string} key
         * @param {Object} [params]
         * @return {Promise<string>|string}
         */
        getStr: function(key, params) {
            if (params) {
                return str.get_string(key, 'mod_simplequiz2', params);
            }
            return this.stringCache[key] || '';
        },

        /**
         * Toggle selected state on answer buttons.
         *
         * @param {ParentNode} root
         */
        bindAnswerSelection: function(root) {
            root.querySelectorAll('.question-container .answer-container').forEach(function(answerButton) {
                answerButton.addEventListener('click', function() {
                    answerButton.classList.toggle('selected');
                });
            });
        },

        /**
         * @param {ParentNode} root
         * @param {number} questionId
         * @return {Promise<string[]>}
         */
        setAriaLabel: async function(root, questionId) {
            const questionContainer = root.querySelector('.question-container[data-questionid="' + questionId + '"]');
            if (!questionContainer) {
                return [];
            }
            const questionText = questionContainer.querySelector('.question-text');
            if (questionText) {
                questionText.setAttribute('aria-label', questionText.innerText);
            }

            const labels = await Promise.all(
                [...questionContainer.querySelectorAll('.answer-container')].map(async function(elem) {
                    const answerInfo = await playerCore.getAccessibilityInformation(elem.querySelector('.answer-text'));
                    const label = await playerCore.getStr('aria_answer_text', {answer: answerInfo});
                    elem.setAttribute('aria-label', label);
                    return label;
                })
            );
            return labels;
        },

        /**
         * Show the next question in sequence.
         *
         * @param {ParentNode} root
         * @param {number} questionId
         * @param {Function} [onMissingNext] Called when there is no next question.
         */
        displayNextQuestion: async function(root, questionId, onMissingNext) {
            const nextQuestion = root.querySelector(
                '.question-container[data-questionid="' + (parseInt(questionId, 10) + 1) + '"]'
            );

            if (!nextQuestion) {
                if (typeof onMissingNext === 'function') {
                    onMissingNext();
                }
                return;
            }

            const current = root.querySelector('.question-container[data-questionid="' + questionId + '"]');
            if (current) {
                current.style.display = 'none';
            }
            nextQuestion.style.display = 'block';

            await this.setAriaLabel(root, parseInt(questionId, 10) + 1);
            const qtext = nextQuestion.querySelector('.question-text');
            if (qtext) {
                qtext.focus();
            }
        },

        /**
         * @param {Element|null} container
         * @return {Promise<string>}
         */
        getAccessibilityInformation: async function(container) {
            if (!container) {
                return '';
            }
            if (container.querySelector('video') !== null) {
                return this.getStr('aria_video', {
                    description: this.findMetaData(container.querySelector('video')),
                });
            }
            if (container.querySelector('audio') !== null) {
                return this.getStr('aria_audio', {
                    description: this.findMetaData(container.querySelector('audio')),
                });
            }
            if (container.querySelector('img') !== null) {
                return this.getStr('aria_image', {
                    description: this.findMetaData(container.querySelector('img')),
                });
            }
            if (container.querySelector('.filter_mathjaxloader_equation') !== null) {
                return this.getStr('aria_math');
            }

            return container.innerText;
        },

        /**
         * @param {Element} elem
         * @return {string}
         */
        findMetaData: function(elem) {
            if (!elem) {
                return this.stringCache['aria_no_description'] || '';
            }
            if (elem.title !== '') {
                return elem.title;
            }
            if (elem.getAttribute('alt') !== '' && elem.getAttribute('alt') !== null) {
                return elem.getAttribute('alt');
            }
            return this.stringCache['aria_no_description'] || '';
        },
    };

    return playerCore;
});
