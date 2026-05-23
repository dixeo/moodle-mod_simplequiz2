# SimpleQuiz

## Description

SimpleQuiz delivers fast, low-friction **multiple-choice practice** with instant feedback, attempt tracking, activity completion rules, and a one-click path to migrate your questions into Moodle’s full **Quiz** when you outgrow the lightweight format.

## About

SimpleQuiz (`mod_simplequiz2`) is an **activity module** for running **multiple-choice questions** with minimal setup. Authors define questions and answers in the activity form, including support for **multiple correct answers** so learners can receive **partial-credit style** outcomes. Learners answer **one question at a time**, **check** their response for immediate feedback, and move forward at their own pace. The interface includes **accessible labels** for images, video, audio, and math content where applicable.

**Results and attempts:** A closing **summary** highlights the session score and **best score** achieved, encouraging retries without losing the highest result. Administrators can **purge attempts** when you need to reset learner data.

**Activity completion** can require a **minimum number of attempts**, so SimpleQuiz fits common completion workflows.

**Graduate to Quiz:** Built-in **export to Quiz** converts compatible questions into Moodle’s native **Quiz** activity (multiple choice), so you can move from lightweight practice to full quiz analytics, question banks, and proctoring-ready workflows when your use case demands it. **Export requires the standard Quiz module** (`mod_quiz`) to be available.

## Requirements

Minimum **Moodle 4.3** (`$plugin->requires` in `version.php`, currently `2023100900`). The activity form uses the site **TinyMCE** editor (legacy Atto markup is still recognised in client-side validation). Empty question slots are rendered as plain textareas and TinyMCE is initialised in the browser when a slot is shown, which avoids broken editors on hidden fieldsets. Tested on Moodle 4.3–4.5 and 5.x.

## Installation

Install via **Site administration → Plugins → Install plugins** using a ZIP of the `simplequiz2` folder, or deploy the folder under `mod/simplequiz2` and complete the upgrade prompt.

## Source code

- **Repository:** https://github.com/dixeo/moodle-mod_simplequiz2  
- **Bug tracker:** https://github.com/dixeo/moodle-mod_simplequiz2/issues

## External services and subscriptions

None.

## Capabilities

| Capability | Purpose |
|------------|---------|
| `mod/simplequiz2:addinstance` | Create a SimpleQuiz activity in a course (editing teacher / manager by default). |
| `mod/simplequiz2:view` | Access the activity and submit attempts (student, teacher, editing teacher, manager by default). |

**Export to Quiz** checks **`mod/quiz:addinstance`** in the activity context (see `convert.php`). Attempt deletion uses **Course reset** (see below).

## Backup and restore

The module advertises **`FEATURE_BACKUP_MOODLE2`** and includes `backup/moodle2` classes. Course backups are **expected** to include SimpleQuiz settings and user attempt data. **Verify** backup and restore (and duplicate activity) on your Moodle version and database engine before production use.

## Privacy

The plugin implements the Moodle **Privacy API** (`classes/privacy/provider.php`): metadata for attempt tables and file areas, export of attempt summaries and per-session answers, and deletion aligned with data requests and context expiry. **Course reset** still removes attempts via the option to delete SimpleQuiz attempt data (see `simplequiz2_reset_userdata` in `lib.php`).

## Limitations

- **Question model:** Up to **25** questions and **5** answers per question (`SIMPLE_QUIZ2_MAX_QUESTION_NB`, `SIMPLE_QUIZ2_MAX_ANSWER_NB` in `lib.php`). Grading scale is oriented to a **100** point scale (`SIMPLE_QUIZ2_GRADE_MAX`).
- **Question types:** Multiple choice only; export to Quiz creates **multichoice** questions in `mod_quiz`.

## Plugin dependencies

- **None** for installing the activity.
- **Export to Quiz** requires the standard **Quiz** module (`mod_quiz`) to be installed and available.
