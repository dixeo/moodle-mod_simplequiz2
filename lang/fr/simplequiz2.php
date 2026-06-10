<?php
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
 * Strings for component 'simplequiz'
 *
 * @package    mod_simplequiz2
 * @copyright  2022 Ministère de l'Éducation nationale français; Dixeo (contact@dixeo.com)
 * @author     Céline Hernandez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['addquestion'] = "Ajouter une question";
$string['aria_answer_text'] = 'Réponse : {$a->answer}';
$string['aria_audio'] = 'Audio: {$a->description}. Veuillez écoutez le son.';
$string['aria_image'] = 'Image: {$a->description}.';
$string['aria_math'] = 'Equation mathématique.';
$string['aria_next'] = '{$a->status}: aller à la question suivante.';
$string['aria_no_description'] = 'pas de description';
$string['aria_question_text'] = 'Question {$a->order}: {$a->description}';
$string['aria_restart'] = '{$a->status}: fin de l\'activité. Vous pouvez recommencer ou passer à l\'activité suivante.';
$string['aria_video'] = 'Vidéo: {$a->description}. Veuillez visionner la vidéo.';
$string['attemptsdeleted'] = 'Suppression des tentatives de QCM';
$string['cantconvertcodeerror'] = 'Une erreur s\'est produite lors de la conversion du module, veuillez contacter l\'équipe de support';
$string['check-answer'] = "Vérifier la réponse";
$string['completionminattempts'] = 'L\'étudiant doit réussir l\'activité ou la tenter une ou plusieurs fois : ';
$string['completionminattempts:attempts'] = 'L\'étudiant doit réussir l\'activité ou la tenter une ou plusieurs fois : {$a}';
$string['completionminattemptsdesc'] = 'L\'étudiant doit réussir l\'activité ou la tenter {$a} fois';
$string['completionminattemptsgroup'] = 'Nombre de tentatives minimum';
$string['convert_success'] = "Conversion effectuée avec succès";
$string['converttoquiz'] = 'Convertir en activité Test';
$string['deletealluserdata'] = 'Supprimer toutes les tentatives des QCMs';
$string['deletequestion'] = "Supprimer cette question";
$string['formanswertitle'] = 'Réponse {$a}';
$string['editquestion'] = 'Modifier la question';
$string['correctfeedback'] = 'Retour si réponse correcte';
$string['partiallycorrectfeedback'] = 'Retour si réponse partiellement correcte';
$string['incorrectfeedback'] = 'Retour si réponse incorrecte';
$string['discardquestion'] = 'Annuler les modifications';
$string['savequestion'] = 'Enregistrer les modifications';
$string['previewanswers'] = 'Réponses';
$string['previewfeedback'] = 'Retour';
$string['previewcorrect'] = 'Correct';
$string['previewpartial'] = 'Partiellement correct';
$string['previewincorrect'] = 'Incorrect';
$string['formquestiontitle'] = 'Question {$a}';
$string['iscorrectanswer'] = 'Réponse correcte ?';
$string['modulename'] = 'QCM';
$string['modulename_help'] = 'Cette activité vous permet de réaliser une série de questions à choix multiples.';
$string['modulenameplural'] = 'QCMs';
$string['nextquestion'] = "Question suivante";
$string['no-questions'] = "Activité en cours de conception";
$string['norightanswererror'] = 'Les questions doivent avoir au moins une bonne réponse.';
$string['notenoughanswerserror'] = 'Les questions doivent comporter au moins deux réponses.';
$string['pluginadministration'] = 'Administration QCM';
$string['pluginname'] = 'QCM';
$string['plugintitle'] = 'QCM';
$string['privacy:metadata:core_files'] = 'L\'activité QCM stocke les fichiers intégrés dans l\'introduction de l\'activité et le contenu des questions.';
$string['privacy:metadata:simplequiz2_attempt_data'] = 'Données de réponses par session pendant que l\'utilisateur progresse dans l\'activité.';
$string['privacy:metadata:simplequiz2_attempt_data:answers'] = 'Carte JSON des identifiants de questions vers les résultats de réponses pour cette session.';
$string['privacy:metadata:simplequiz2_attempt_data:cmid'] = 'L\'instance de module de cours à laquelle cette session appartient.';
$string['privacy:metadata:simplequiz2_attempt_data:timecreated'] = 'Date de création de cet enregistrement de session.';
$string['privacy:metadata:simplequiz2_attempt_data:userid'] = 'L\'utilisateur auquel cette session appartient.';
$string['privacy:metadata:simplequiz2_attempts'] = 'Résumé de chaque tentative de l\'utilisateur pour une instance d\'activité QCM donnée.';
$string['privacy:metadata:simplequiz2_attempts:cmid'] = 'L\'instance de module de cours à laquelle ce résumé appartient.';
$string['privacy:metadata:simplequiz2_attempts:cntattempt'] = 'Nombre de tentatives enregistrées pour cet utilisateur.';
$string['privacy:metadata:simplequiz2_attempts:completed'] = 'Si l\'utilisateur a terminé l\'activité avec succès (indicateur propre au plugin).';
$string['privacy:metadata:simplequiz2_attempts:timefirstattempt'] = 'Date de la première tentative.';
$string['privacy:metadata:simplequiz2_attempts:timelastattempt'] = 'Date de la tentative la plus récente.';
$string['privacy:metadata:simplequiz2_attempts:userid'] = 'L\'utilisateur auquel ce résumé appartient.';
$string['question'] = 'Question';
$string['questionfail'] = "Mauvaise réponse";
$string['questionpartial'] = 'Réponse partiellement correcte';
$string['questionsuccess'] = "Bonne réponse";
$string['questiontext'] = 'Texte de la question';
$string['restart'] = 'Refaire le quiz';
$string['result-bestscore'] = 'Meilleur score : {$a->score}%';
$string['result-help'] = 'Seul le meilleur score est conservé.';
$string['result-score'] = 'Score obtenu : {$a->score}%';
$string['session_expired_body'] = "<p>Vous allez être redirigé vers la page d'accueil.<br>Merci de vous reconnecter pour continuer.</p>";
$string['session_expired_title'] = 'Session expirée';
$string['show-results'] = 'Résultats';
$string['simplequiz2:addinstance'] = 'Ajouter une activité QCM';
$string['simplequiz2:view'] = 'Voir une activité QCM';
$string['embed_finish'] = 'Quitter le quiz';
$string['embed_result_complete'] = 'Cette tentative : {$a->score}/{$a->total} ({$a->percent} %)';
$string['embed_result_best'] = 'Meilleur score : {$a->score}/{$a->total} ({$a->percent} %)';
