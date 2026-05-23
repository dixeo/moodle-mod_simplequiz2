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
 * JS code for the simplequiz2 plugin student interface.
 *
 * @module      mod_simplequiz2/view
 * @copyright   2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'core/config',
    'core/str',
    'core/modal_save_cancel',
    'core/modal_events',
    'core/notification',
], function(Config, str, ModalSaveCancel, ModalEvents, Notification) {

    const modSimplequizView = {

        instanceId: 0,
        courseId: 0,
        courseModuleId: null,
        attemptId: 0,
        apiUrl: '',
        stringCache: {},

        langStrings: [
            'aria_question_text',
            'aria_answer_text',
            'questionsuccess',
            'questionfail',
            'questionpartial',
            'aria_restart',
            'aria_next',
            'aria_video',
            'aria_image',
            'aria_math',
            'aria_audio',
            'aria_no_description',
            'session_expired_title',
            'session_expired_body',
        ],

        /**
         * Cached plugin string or parametrized string via core/str.
         *
         * @param {string} key String identifier
         * @param {Object} [params] Placeholder values
         * @return {Promise<string>}
         */
        getStr: async function(key, params) {
            if (params) {
                return str.get_string(key, 'mod_simplequiz2', params);
            }
            return this.stringCache[key] || '';
        },

        getActivityTitleElement: function() {
            const selectors = [
                'section#region-main > h2',
                'section#region-main h2',
                '.activity-header h1',
                '#page-header h1',
                '.page-header-headings h1',
                '[data-region="main-content"] h2',
            ];
            for (let i = 0; i < selectors.length; i++) {
                const element = document.querySelector(selectors[i]);
                if (element) {
                    return element;
                }
            }
            return null;
        },

        focusActivityLandmarks: function() {
            const title = this.getActivityTitleElement();
            if (title) {
                title.setAttribute('tabindex', '0');
                title.focus();
            }
            const intro = document.querySelector('.activity-description#intro');
            if (intro) {
                intro.setAttribute('tabindex', '0');
            }
        },

        init: async function(instanceId, courseId, courseModuleId, attemptId) {
            const that = this;

            this.instanceId = instanceId;
            this.courseId = courseId;
            this.courseModuleId = courseModuleId;
            this.attemptId = attemptId;
            this.apiUrl = Config.wwwroot + '/mod/simplequiz2/ajax/ajax.php';

            const modStrings = this.langStrings.map(l => ({
                key: l,
                component: 'mod_simplequiz2',
            }));
            const loaded = await str.get_strings(modStrings);
            this.langStrings.forEach((key, index) => {
                this.stringCache[key] = loaded[index];
            });

            document.querySelectorAll('.question-container .answer-container').forEach((answerButton) => {
                answerButton.addEventListener('click', () => {
                    answerButton.classList.toggle('selected');
                });
            });

            document.querySelectorAll('.question-container button.check-answer').forEach((checkAnswerButton) => {
                checkAnswerButton.addEventListener('click', () => {
                    that.checkAnswers(checkAnswerButton.dataset.questionid);
                });
            });

            document.querySelectorAll('.question-container button.next-question').forEach((nextQuestionButton) => {
                nextQuestionButton.addEventListener('click', () => {
                    that.displayNextQuestion(nextQuestionButton.dataset.questionid);
                });
            });

            const showResultsButton = document.querySelector('#simplequiz_container button.show-results');
            if (showResultsButton) {
                showResultsButton.addEventListener('click', () => {
                    that.updateResultspage();
                });
            }

            const restartButton = document.querySelector('#simplequiz_container button.restart');
            if (restartButton) {
                restartButton.addEventListener('click', () => {
                    location.reload();
                });
            }

            await that.setAriaLabel(0);
            that.focusActivityLandmarks();
        },

        setAriaLabel: async function(questionId) {
            const questionContainer = document.querySelector(`.question-container[data-questionid="${questionId}"]`);
            const questionText = questionContainer.querySelector('.question-text');
            questionText.setAttribute('aria-label', questionText.innerText);

            const labels = await Promise.all(
                [...questionContainer.querySelectorAll('.answer-container')].map(async(elem) => {
                    const answerInfo = await this.getAccessibilityInformation(elem.querySelector('.answer-text'));
                    const label = await this.getStr('aria_answer_text', {answer: answerInfo});
                    elem.setAttribute('aria-label', label);
                    return label;
                })
            );
            return labels;
        },

        checkAnswers: async function(questionId) {
            const selector = '.question-container[data-questionid="' + questionId + '"] .answer-container.selected';
            const selectedAnswers = document.querySelectorAll(selector);

            const userChoices = [];
            for (let i = 0; i < selectedAnswers.length; i++) {
                userChoices.push(selectedAnswers[i].dataset.answerid);
            }

            const data = await this.communicate(this.apiUrl + '?action=check_question&id=' + this.instanceId, {
                questionid: questionId,
                attemptid: this.attemptId,
                answers: userChoices.join(','),
            });

            let hasCorrectAnswer = false;

            for (let i = 0; i < selectedAnswers.length; i++) {
                const answerId = selectedAnswers[i].dataset.answerid;
                selectedAnswers[i].classList.remove('selected');
                if (data.results[answerId] === true) {
                    selectedAnswers[i].classList.add('question-success');
                    hasCorrectAnswer = true;
                } else {
                    selectedAnswers[i].classList.add('question-fail');
                }
            }

            const answers = document.querySelectorAll(
                '.question-container[data-questionid="' + questionId + '"] .answer-container'
            );
            for (let i = 0; i < answers.length; i++) {
                answers[i].disabled = true;
            }

            const statusEl = document.querySelector('.question-status[data-questionid="' + questionId + '"]');
            const statusModifierClasses = ['question-status-success', 'question-status-partial', 'question-status-fail'];
            statusEl.classList.remove(...statusModifierClasses);
            let status = '';
            if (data.iscorrect === true) {
                status = await this.getStr('questionsuccess');
                statusEl.classList.add('question-status-success');
            } else if (data.iscorrect === false && hasCorrectAnswer === true) {
                status = await this.getStr('questionpartial');
                statusEl.classList.add('question-status-partial');
            } else {
                status = await this.getStr('questionfail');
                statusEl.classList.add('question-status-fail');
            }
            statusEl.innerHTML = status;

            const nextquestion = document.querySelector(
                '.question-container[data-questionid="' + (parseInt(questionId) + 1) + '"]'
            );
            if (!nextquestion) {
                const showResults = document.querySelector('#simplequiz_container button.show-results');
                showResults.style.display = 'block';
                const restartLabel = await this.getStr('aria_restart', {status});
                showResults.setAttribute('aria-label', restartLabel);
                showResults.focus();
            } else {
                const nextBtn = document.querySelector('button.next-question[data-questionid="' + questionId + '"]');
                nextBtn.style.display = 'block';
                nextBtn.setAttribute('aria-label', await this.getStr('aria_next', {status}));
                nextBtn.focus();
            }

            document.querySelector('button.check-answer[data-questionid="' + questionId + '"]').style.display = 'none';
        },

        displayNextQuestion: async function(questionId) {
            const nextQuestion = document.querySelector(
                '.question-container[data-questionid="' + (parseInt(questionId) + 1) + '"]'
            );

            if (!nextQuestion) {
                location.reload();
                return;
            }

            document.querySelector('.question-container[data-questionid="' + questionId + '"]').style.display = 'none';
            nextQuestion.style.display = 'block';

            await this.setAriaLabel(parseInt(questionId) + 1);
            nextQuestion.querySelector('.question-text').focus();
        },

        updateResultspage: async function() {
            const data = await this.communicate(this.apiUrl + '?action=get_attempt_results&id=' + this.instanceId, {
                attemptid: this.attemptId,
            });

            const [attemptLabel, bestLabel] = await str.get_strings([
                {key: 'result-score', component: 'mod_simplequiz2', param: {score: Math.trunc(data.attemptgrade)}},
                {key: 'result-bestscore', component: 'mod_simplequiz2', param: {score: Math.trunc(data.bestscore)}},
            ]);

            const currentScore = document.querySelector('#simplequiz-result .current-score');
            if (currentScore) {
                currentScore.innerHTML = attemptLabel;
            }

            const bestScore = document.querySelector('#simplequiz-result .best-score');
            if (bestScore) {
                bestScore.innerHTML = bestLabel;
            }

            if (data.attemptgrade < 100) {
                document.querySelectorAll('#simplequiz-result .fireworks').forEach((elem) => {
                    elem.style.visibility = 'hidden';
                });
            }

            const questionsContainer = document.querySelector('#simplequiz-questions');
            const resultContainer = document.querySelector('#simplequiz-result');
            if (questionsContainer) {
                questionsContainer.style.display = 'none';
            }
            if (resultContainer) {
                resultContainer.style.display = 'flex';
            }
            if (currentScore) {
                currentScore.focus();
            }
        },

        getAccessibilityInformation: async function(container) {
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

        findMetaData: function(elem) {
            if (elem.title !== '') {
                return elem.title;
            }
            if (elem.getAttribute('alt') !== '' && elem.getAttribute('alt') !== null) {
                return elem.getAttribute('alt');
            }
            return this.stringCache['aria_no_description'] || '';
        },

        communicate: async function(url, payload = null) {
            const formData = new FormData();
            formData.append('sesskey', Config.sesskey);
            formData.append('courseid', this.courseId);
            formData.append('coursemoduleid', this.courseModuleId);

            if (payload !== null) {
                for (const [key, value] of Object.entries(payload)) {
                    formData.append(key, value);
                }
            }

            const response = await fetch(url, {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();
            this.handleError(response, data);
            return data;
        },

        handleError: function(response, data) {
            const redirectHome = function() {
                location.href = Config.wwwroot;
            };

            if (response.status == 401 || data.errorcode == 'requireloginerror') {
                ModalSaveCancel.create({
                    title: this.stringCache['session_expired_title'],
                    body: this.stringCache['session_expired_body'],
                    show: true,
                    removeOnClose: true,
                }).then(function(modal) {
                    modal.getRoot().on(ModalEvents.save, redirectHome);
                    modal.getRoot().on(ModalEvents.hidden, redirectHome);
                    setTimeout(redirectHome, 5000);
                    return modal;
                }).catch(function() {
                    redirectHome();
                });
            } else if (response.ok === false) {
                Notification.alert(
                    'Error ' + response.status,
                    'Check browser console.',
                    'Error'
                );
            }
        },
    };

    return modSimplequizView;
});
