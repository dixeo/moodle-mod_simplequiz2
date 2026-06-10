// This file is part of Moodle - http://moodle.org/
//
// @module     mod_simplequiz2/activity_player
// @copyright  2026 Dixeo (contact@dixeo.com)
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

define([
    'core/config',
    'core/str',
    'core/modal_save_cancel',
    'core/modal_events',
    'core/notification',
    'mod_simplequiz2/player_core',
], function(Config, str, ModalSaveCancel, ModalEvents, Notification, playerCore) {

    const activityPlayer = {
        instanceId: 0,
        courseId: 0,
        courseModuleId: null,
        attemptId: 0,
        apiUrl: '',
        stringCache: {},

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
            const root = document;

            this.instanceId = instanceId;
            this.courseId = courseId;
            this.courseModuleId = courseModuleId;
            this.attemptId = attemptId;
            this.apiUrl = Config.wwwroot + '/mod/simplequiz2/ajax/ajax.php';

            const extraStrings = [
                'questionsuccess', 'questionfail', 'questionpartial',
                'aria_restart', 'session_expired_title', 'session_expired_body',
            ];
            const modStrings = extraStrings.map(function(key) {
                return {key: key, component: 'mod_simplequiz2'};
            });
            const loaded = await str.get_strings(modStrings);
            extraStrings.forEach(function(key, index) {
                that.stringCache[key] = loaded[index];
            });
            Object.assign(this.stringCache, await playerCore.loadStrings());

            playerCore.bindAnswerSelection(root);

            root.querySelectorAll('.question-container button.check-answer').forEach(function(checkAnswerButton) {
                checkAnswerButton.addEventListener('click', function() {
                    that.checkAnswers(checkAnswerButton.dataset.questionid);
                });
            });

            root.querySelectorAll('.question-container button.next-question').forEach(function(nextQuestionButton) {
                nextQuestionButton.addEventListener('click', function() {
                    playerCore.displayNextQuestion(root, nextQuestionButton.dataset.questionid, function() {
                        location.reload();
                    });
                });
            });

            const showResultsButton = document.querySelector('#simplequiz_container button.show-results');
            if (showResultsButton) {
                showResultsButton.addEventListener('click', function() {
                    that.updateResultspage();
                });
            }

            const restartButton = document.querySelector('#simplequiz_container button.restart');
            if (restartButton) {
                restartButton.addEventListener('click', function() {
                    location.reload();
                });
            }

            await playerCore.setAriaLabel(root, 0);
            that.focusActivityLandmarks();
        },

        checkAnswers: async function(questionId) {
            const root = document;
            const selector = '.question-container[data-questionid="' + questionId + '"] .answer-container.selected';
            const selectedAnswers = root.querySelectorAll(selector);

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

            const answers = root.querySelectorAll(
                '.question-container[data-questionid="' + questionId + '"] .answer-container'
            );
            for (let i = 0; i < answers.length; i++) {
                answers[i].disabled = true;
            }

            const feedbackEl = root.querySelector('.question-feedback[data-questionid="' + questionId + '"]');
            const statusEl = root.querySelector('.question-status[data-questionid="' + questionId + '"]');
            const statusModifierClasses = ['question-status-success', 'question-status-partial', 'question-status-fail'];
            statusEl.classList.remove(...statusModifierClasses);

            let status = '';
            if (data.iscorrect === true) {
                status = this.stringCache['questionsuccess'] || '';
                statusEl.classList.add('question-status-success');
            } else if (data.iscorrect === false && hasCorrectAnswer === true) {
                status = this.stringCache['questionpartial'] || '';
                statusEl.classList.add('question-status-partial');
            } else {
                status = this.stringCache['questionfail'] || '';
                statusEl.classList.add('question-status-fail');
            }
            statusEl.innerHTML = status;

            const customFeedback = (data.feedback || '').trim();
            if (feedbackEl) {
                if (customFeedback !== '') {
                    feedbackEl.innerHTML = customFeedback;
                    feedbackEl.hidden = false;
                } else {
                    feedbackEl.innerHTML = '';
                    feedbackEl.hidden = true;
                }
            }

            const nextquestion = root.querySelector(
                '.question-container[data-questionid="' + (parseInt(questionId, 10) + 1) + '"]'
            );
            if (!nextquestion) {
                const showResults = root.querySelector('#simplequiz_container button.show-results');
                showResults.style.display = 'block';
                const restartLabel = await str.get_string('aria_restart', 'mod_simplequiz2', {status: status});
                showResults.setAttribute('aria-label', restartLabel);
                showResults.focus();
            } else {
                const nextBtn = root.querySelector('button.next-question[data-questionid="' + questionId + '"]');
                nextBtn.style.display = 'block';
                nextBtn.setAttribute('aria-label', await str.get_string('aria_next', 'mod_simplequiz2', {status: status}));
                nextBtn.focus();
            }

            root.querySelector('button.check-answer[data-questionid="' + questionId + '"]').style.display = 'none';
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
                document.querySelectorAll('#simplequiz-result .fireworks').forEach(function(elem) {
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

        communicate: async function(url, payload) {
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

    return activityPlayer;
});
