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
 * Strings for component 'local_tla', language 'de'.
 *
 * @package    local_tla
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Zeit Learning Analytics';
$string['tla:view'] = 'Das Teaching-and-Learning-Analytics-Kursdashboard ansehen';
$string['tla:viewall'] = 'Teaching and Learning Analytics über alle Kurse ansehen';
$string['privacy:metadata'] = 'Das Plugin Teaching and Learning Analytics speichert keine persönlichen Daten.';
$string['view'] = 'Analytics anzeigen';
$string['settings'] = 'Einstellungen';
$string['statistics'] = 'Statistik';
$string['forecast'] = 'Vorhersage';
$string['dashboard'] = 'Dashboard';
$string['courseid'] = 'Kurs-ID';
$string['noaccess'] = 'Sie haben keine Berechtigung, diese Seite aufzurufen.';
$string['deadline_yellow'] = 'Grenzwert für gelbe Deadline';
$string['deadline_red'] = 'Grenzwert für rote Deadline';
$string['min_observations'] = 'Mindestanzahl Beobachtungen für zuverlässige Prognosen';
$string['dashboardtitle'] = 'Teaching and Learning Analytics';
$string['totalevents'] = 'Ereignisse gesamt';
$string['peakactiveusers'] = 'Maximal aktive Nutzer';
$string['moduleevents'] = 'Modulereignisse';
$string['submittedassignments'] = 'Eingereichte Abgaben';
$string['assignmentsummary'] = 'Abgabeübersicht';
$string['ontime'] = 'Pünktlich';
$string['late'] = 'Verspätet';
$string['noduedate'] = 'Ohne Abgabefrist';
$string['eventsperday'] = 'Ereignisse pro Tag';
$string['events'] = 'Ereignisse';
$string['activeusersperday'] = 'Aktive Nutzer pro Tag';
$string['activeusers'] = 'Aktive Nutzer';
$string['moduleusage'] = 'Modulnutzung';
$string['component'] = 'Komponente';
$string['nodata'] = 'Für diesen Zeitraum sind keine Daten vorhanden.';
$string['lateindicator'] = 'Ampel: verspätete Abgaben';
$string['laterate'] = 'Anteil verspäteter Abgaben';
$string['norateavailable'] = 'Es ist noch keine Quote verfügbar.';
$string['trafficlightminimum'] = 'Mindestens {$a} eingereichte Abgaben sind für eine Bewertung erforderlich.';
$string['trafficlight_green'] = 'Grün';
$string['trafficlight_yellow'] = 'Gelb';
$string['trafficlight_red'] = 'Rot';
$string['trafficlight_unknown'] = 'Nicht genügend Daten';
$string['effectivedeadlinesconsidered'] = 'Effektive Fristen berücksichtigt (Overrides und Verlängerungen)';
$string['extensionsapplied'] = 'Angewendete Verlängerungen';
$string['useroverrides'] = 'Benutzer-Overrides';
$string['groupoverrides'] = 'Gruppen-Overrides';
$string['unresolveddeadlines'] = 'Nicht eindeutig bestimmbare Fristen';
$string['scoredistributions'] = 'Punkteverteilung';
$string['scorevalidgrades'] = 'Gültige Bewertungen';
$string['scoremedian'] = 'Median';
$string['scorequartiles'] = 'Q1–Q3';
$string['scorelow'] = 'Anteil unter 20 %';
$string['scoremiddle'] = 'Anteil 40–60 %';
$string['scorehigh'] = 'Anteil über 80 %';
$string['scoredistributionstruncated'] = 'Es werden {$a->shown} von {$a->total} Aktivitäten angezeigt';
$string['scoreinvalidgrades'] = 'Ausgeschlossene ungültige Bewertungen: {$a}';
$string['scorestatus_middle'] = 'Mittiger Schwerpunkt';
$string['scorestatus_ceiling'] = 'Deckeneffekt';
$string['scorestatus_floor'] = 'Bodeneffekt';
$string['scorestatus_ushape'] = 'U-Verteilung';
$string['scorestatus_mixed'] = 'Uneindeutig';
$string['scorestatus_unknown'] = 'Nicht genügend Daten';
$string['scoreexpl_ushape'] = 'Viele Bewertungen liegen an den beiden Extremen, während der mittlere Punktebereich wenig vertreten ist. Das kann auf zwei getrennte Leistungsgruppen, missverständliche Aufgaben oder eine sehr binäre Bewertung hindeuten.';
$string['scoreexpl_ceiling'] = 'Bewertungen häufen sich nahe dem Maximum. Ordne diese Feststellung anhand des Beurteilungsdesigns ein; sie belegt für sich weder ein Problem noch eigenständige Kompetenz.';
$string['scoreexpl_floor'] = 'Ein großer Anteil der Bewertungen liegt im sehr niedrigen Punktebereich. Die Aktivität könnte zu schwer, unklar formuliert oder unpassend bewertet sein.';
$string['scoreexpl_middle'] = 'Viele Bewertungen liegen im mittleren Punktebereich. Die Aktivität zeigt nach den gewählten Regeln keinen deutlichen Boden-, Decken- oder U-Effekt.';
$string['scoreexpl_mixed'] = 'Die Verteilung zeigt kein eindeutig klassifizierbares Muster. Prüfe Histogramm, Median und Quartile im Kontext der Aktivität.';
$string['scoreexpl_unknown'] = 'Für eine belastbare Einschätzung liegen noch nicht genügend Bewertungen vor.';
$string['learningprogress'] = 'Lernfortschritt';
$string['courseprogresstitle'] = 'Kursentwicklung';
$string['progresstruncated'] = 'Es werden {$a->shown} von {$a->total} Quizzes angezeigt';
$string['progressactivities'] = 'Aktivitäten';
$string['progressparticipants'] = 'Teilnehmende';
$string['progressmeanfirst'] = 'Ø erster';
$string['progressmeanlast'] = 'Ø letzter';
$string['progressmeanbest'] = 'Ø bester';
$string['progressmedianchange'] = 'Mediane Veränderung';
$string['progressimproved'] = 'Verbessert';
$string['progressstable'] = 'Stabil';
$string['progressdeclined'] = 'Verschlechtert';
$string['progressmeanattempts'] = 'Ø Versuche';
$string['progressstatus_improving'] = 'Verbesserung';
$string['progressstatus_stable'] = 'Stabil';
$string['progressstatus_declining'] = 'Rückgang';
$string['progressstatus_mixed'] = 'Gemischt';
$string['progressstatus_unknown'] = 'Nicht genügend Daten';
$string['progressexpl_improving'] = 'Bei einem großen Anteil steigt das Ergebnis zwischen dem ersten und dem letzten abgeschlossenen Versuch. Das kann auf Lernen durch Wiederholung, Feedback oder zusätzliche Beschäftigung mit dem Stoff hindeuten.';
$string['progressexpl_stable'] = 'Die Ergebnisse verändern sich zwischen den Versuchen nur wenig. Das kann bedeuten, dass viele bereits im ersten Versuch ihr Leistungsniveau erreichen oder dass weitere Versuche wenig zusätzlichen Lerngewinn zeigen.';
$string['progressexpl_declining'] = 'Bei vielen Studierenden ist das Ergebnis im letzten abgeschlossenen Versuch niedriger als im ersten. Prüfe Versuchseinstellungen, Fragenvariation, Zeitdruck und die Vergleichbarkeit der Versuche.';
$string['progressexpl_mixed'] = 'Die Entwicklung ist uneinheitlich: Ein Teil verbessert sich, während andere stabil bleiben oder niedrigere Ergebnisse erzielen.';
$string['progressexpl_unknown'] = 'Für eine belastbare Einschätzung liegen noch nicht genügend Studierende mit mehreren abgeschlossenen Versuchen vor.';
$string['courseprogressexpl'] = 'Die Darstellung vergleicht normalisierte Ergebnisse zwischen der ersten und letzten vorhandenen bewerteten Aktivität. Unterschiede in Schwierigkeit und Lernziel der Aktivitäten können das Ergebnis beeinflussen.';
$string['courseprogresswarning'] = 'Eine rechnerische Verbesserung zwischen der ersten und letzten bewerteten Aktivität ist ein möglicher Lernhinweis, aber kein Nachweis für Lehrqualität. Die Aktivitäten können sich in Schwierigkeit, Lernziel und Bewertungsform unterscheiden.';
$string['emptynomatchingactivities'] = 'Keine passenden bewerteten Aktivitäten gefunden.';
$string['emptynovalidgrades'] = 'Noch keine gültigen Bewertungen vorhanden.';
$string['emptynotenoughobservations'] = 'Für diese Analyse liegen nicht genügend Beobachtungen vor.';
$string['emptynoquizattempts'] = 'In diesem Kurs gibt es keine Quizzes mit mehreren abgeschlossenen Versuchen.';
$string['doseresponse'] = 'Lern-Dosis-Wirkungs-Beziehung (bayesianisch)';
$string['doseresponseintro'] = 'Geschätzter Zusammenhang zwischen dem Umfang des Übens (Anzahl abgeschlossener Quizversuche) und dem erreichten Leistungsniveau, modelliert als sättigende Emax-Kurve und über eine gitterbasierte bayesianische Posteriorverteilung geschätzt.';
$string['doseresponse_help'] = 'Dies schätzt, wie der Umfang des Übens mit der erreichten Leistung zusammenhängt, mithilfe einer bayesianischen Emax-Kurve (Sättigungskurve). Die x-Achse ist die Anzahl abgeschlossener Quizversuche, die y-Achse die erreichte normalisierte Punktzahl.

- **Maximum (Emax)**: die Leistung, der sich die Kurve bei viel Übung annähert. Liegt sie deutlich unter 100 %, begrenzt etwas anderes als Übung das Ergebnis (Schwierigkeit, Voraussetzungen, Vorwissen).
- **EC50**: die Anzahl Versuche, die die Hälfte des Maximums erreicht — etwa dort, wo zusätzliche Übung wenig zusätzlichen Nutzen bringt.
- **Schattiertes Band**: das 80%-Glaubwürdigkeitsintervall, also die Unsicherheit der Kurve.
- **Durch Üben erklärte Varianz**: wie viel der Leistungsunterschiede die Kurve erklärt. Ist sie sehr niedrig, lautet das Urteil „Keine klare Dosis-Wirkung“ — in diesem Kurs sagt der Übungsumfang die Leistung nicht vorher; achten Sie dann eher auf Qualität und Passung als auf „mehr üben“.

Da Ihre Quizze bei jedem Versuch neue Fragen ziehen, spiegelt die Versuchsanzahl echtes wiederholtes Üben wider und nicht das Wiederholen derselben Fragen.

Wichtig: Dies ist ein beobachteter Zusammenhang, kein Ursachennachweis. Wer mehr übt, kann sich von jenen unterscheiden, die weniger üben. Lesen Sie die Kurve als zu prüfende Hypothese (z. B. indem Sie die empfohlene Übung anpassen und die nächste Kohorte vergleichen), nicht als Beleg dafür, dass mehr Übung die Noten hebt.';
$string['doseresponseobservations'] = 'Beobachtungen';
$string['doseresponsequizzes'] = 'Quizzes';
$string['doseresponseemax'] = 'Maximale Leistung (Emax)';
$string['doseresponseec50'] = 'Versuche für die halbe Maximalleistung (EC50)';
$string['doseresponsesigma'] = 'Streuung um die Kurve (σ)';
$string['doseresponsecredible'] = '90%-Glaubwürdigkeitsintervall';
$string['doseresponseexplained'] = 'Durch Üben erklärte Varianz';
$string['doseresponseppc'] = 'Modellprüfung (Streuung der Ergebnisse)';
$string['doseresponseppcdetail'] = 'Beobachtete Streuung {$a->obs}, Modell-Streuung {$a->rep}';
$string['doseresponseaxisx'] = 'Abgeschlossene Versuche';
$string['doseresponseaxisy'] = 'Leistung (%)';
$string['doseresponsemedianline'] = 'Posterior-Median';
$string['doseresponselowerband'] = 'Unteres 10%';
$string['doseresponseupperband'] = 'Oberes 90%';
$string['doseresponseobserved'] = 'Beobachteter Durchschnitt';
$string['doseresponsedata'] = 'Diagrammdaten';
$string['doseresponseband'] = '80%-Band';
$string['doseresponsebandnote'] = 'Blau: Posterior-Median der mittleren Kurve mit schattiertem 80%-Glaubwürdigkeitsband (10.–90. Perzentil). Grau: durchschnittliche Leistung je Anzahl abgeschlossener Versuche (nur für Gruppen ab drei Studierenden angezeigt).';
$string['doseresponsewarning'] = 'Dies ist ein beobachteter Zusammenhang, kein Nachweis einer Ursache: Wer mehr übt, kann sich systematisch von jenen unterscheiden, die weniger üben. Die Kurve beschreibt die aktuelle Kohorte und ist keine Vorhersage für Einzelne.';
$string['doseresponseempty'] = 'Noch nicht genügend Quiz-Übungsdaten, um eine Dosis-Wirkungs-Kurve zu schätzen.';
$string['doseresponsefit_good'] = 'Verlässliche Schätzung';
$string['doseresponsefit_weak'] = 'Vorläufige Schätzung';
$string['doseresponsefit_flat'] = 'Keine klare Dosis-Wirkung';
$string['doseresponsefit_poor'] = 'Modell passt nicht';
$string['doseresponsefit_unknown'] = 'Nicht genügend Daten';
$string['doseresponsefitexpl_good'] = 'Die Daten stützen eine klare sättigende Kurve: Die Parameter sind mit angemessener Genauigkeit geschätzt und das Modell gibt die Streuung der Ergebnisse wieder.';
$string['doseresponsefitexpl_weak'] = 'Eine sättigende Kurve wird geschätzt, jedoch mit großer Unsicherheit. Die genauen Werte sind mit Vorsicht zu interpretieren; mehr Übungsdaten würden sie schärfen.';
$string['doseresponsefitexpl_flat'] = 'Die Leistung ändert sich kaum mit dem Umfang des Übens: Die Aktivität sagt das Ergebnis in diesem Kurs nicht klar vorher, die geschätzte Kurve ist also praktisch flach.';
$string['doseresponsefitexpl_poor'] = 'Die Streuung der Ergebnisse ist hier nicht mit dem Emax-Modell vereinbar, die geschätzte Kurve ist daher unzuverlässig. Mit Vorsicht interpretieren.';
$string['doseresponsefitexpl_unknown'] = 'Es gibt noch nicht genügend Studierende mit Quiz-Übung für eine verlässliche Schätzung.';

// Assessment design: current configuration, never a fairness score.
$string['ad_title'] = 'Beurteilungsdesign';
$string['ad_intro'] = 'Aktuelle Grundeinstellungen von Tests und Aufgaben, auch ohne Abgaben. Angezeigt werden einzelne Befunde, keine Fairness-Note und keine Lehrqualitätsampel.';
$string['ad_snapshot'] = 'Einstellungen geprüft am {$a}. Unabhängig vom Zeitraumfilter der Aktivitätsprotokolle.';
$string['ad_limits_title'] = 'Aussagegrenzen und ungeprüfte Aspekte';
$string['ad_limits'] = 'Didaktischer Zweck, wirksamer Anteil an der Gesamtnote, externe Leistungsnachweise, tatsächlicher Feedbackinhalt und Eigenständigkeit werden hier nicht ermittelt. Aktuelle Einstellungen rekonstruieren keine früheren Regeln. Manuelle Notenüberschreibungen und der effektive individuelle Zugang werden nicht ausgewertet. Ein Prüfhinweis ist eine Frage an die Lehrenden, kein Unfairness-Urteil. TLA verändert keine Einstellungen.';
$string['ad_empty'] = 'Keine vorhandenen Tests oder Aufgaben gefunden. Andere Aktivitätstypen und Leistungsnachweise außerhalb von Moodle werden nicht ausgewertet.';
$string['ad_pagination'] = 'Aktivitäten {$a->first}–{$a->last} von {$a->total}';
$string['ad_previous'] = 'Vorherige Aktivitäten';
$string['ad_next'] = 'Weitere Aktivitäten';
$string['ad_navigation'] = 'Seiten des Beurteilungsdesign-Checks';
$string['ad_rulecount'] = '{$a} Einzelbefunde';
$string['ad_currentsettings'] = 'Gelesene Einstellungen';
$string['ad_activitylink'] = 'Aktivität öffnen';
$string['ad_reviewaction'] = 'Prüffrage';
$string['ad_none'] = 'Nicht festgelegt';
$string['ad_unlimited'] = 'Unbegrenzt';
$string['ad_hidden'] = 'In Moduleinstellungen verborgen';
$string['ad_status_observed'] = 'Festgestellt';
$string['ad_status_review'] = 'Prüfen';
$string['ad_status_unknown'] = 'Nicht bestimmbar / nicht geprüft';
$string['ad_status_notapplicable'] = 'Nicht anwendbar';
$string['ad_criterion_grading'] = 'Versuche und Bewertung';
$string['ad_criterion_feedback'] = 'Rückmeldung und Einsicht';
$string['ad_criterion_timing'] = 'Zeitfenster';
$string['ad_criterion_scope'] = 'Geltungsbereich';
$string['ad_criterion_questionrules'] = 'Regeln innerhalb einzelner Fragen';
$string['ad_rule_quiz_best'] = 'Mehrere vollständige Testversuche sind in der Grundeinstellung erlaubt; der beste Versuch zählt. Niedrigere Ergebnisse anderer Versuche senken ihn durch diese Verrechnungsregel nicht. Das trifft keine Aussage über Abzüge innerhalb von Fragen oder manuelle Notenänderungen.';
$string['ad_rule_quiz_average'] = 'Mehrere vollständige Testversuche sind erlaubt; ihr Durchschnitt zählt. Frühere niedrigere Ergebnisse bleiben auch nach einem späteren erfolgreichen Versuch für die Aktivitätsbewertung wirksam.';
$string['ad_rule_quiz_first'] = 'Mehrere vollständige Testversuche sind erlaubt, aber nur der erste zählt für die Aktivitätsbewertung. Spätere Verbesserungen ersetzen diese Note durch diese Regel nicht.';
$string['ad_rule_quiz_last'] = 'Mehrere vollständige Testversuche sind erlaubt; der letzte zählt. Frühere niedrigere Ergebnisse werden nicht eingerechnet; ein späterer schlechterer Versuch kann jedoch einen besseren ersetzen.';
$string['ad_rule_quiz_single'] = 'Die Grundeinstellung erlaubt einen vollständigen Testversuch. Daraus folgt nicht, ob Korrekturen innerhalb von Fragen, Overrides oder Folgeaktivitäten weitere Möglichkeiten bieten.';
$string['ad_rule_ungraded'] = 'Es ist keine Aktivitätsnote konfiguriert. Diese Feststellung bewertet weder Abschlussbedingungen noch indirekte Auswirkungen auf andere Aktivitäten.';
$string['ad_rule_grading_unknown'] = 'Die vorhandenen Einstellungen erlauben keine belastbare Einordnung dieser Versuchs- oder Bewertungsregel.';
$string['ad_rule_feedback_open'] = 'Mindestens eine Standard-Ergebnis- oder Feedbackkategorie ist zur Einsicht freigegeben, solange der Test offen ist. Das können auch nur Punkte oder Richtig/Falsch-Angaben sein; inhaltliche Rückmeldung und ein tatsächlich zugänglicher weiterer Versuch sind damit nicht garantiert.';
$string['ad_rule_feedback_immediate'] = 'Die Standard-Ergebnis- oder Feedbackeinsicht nach dem Versuch ist nur unmittelbar nach der Abgabe freigegeben, nicht später während der offenen Testphase. Dieses Standardzeitfenster dauert zwei Minuten; Inhalt und tatsächliche Nutzung sind ungeprüft.';
$string['ad_rule_feedback_after_close'] = 'Die Standard-Ergebnis- oder Feedbackeinsicht nach dem Versuch ist nur nach dem eingestellten Testschluss freigegeben. Damit ist kein Standard-Einsichtsfenster zwischen vollständigen Versuchen vorgesehen. Rückmeldungen innerhalb von Fragen oder außerhalb von Moodle sind nicht geprüft.';
$string['ad_rule_feedback_no_close'] = 'Die Ergebnis- oder Feedbackeinsicht nach dem Versuch ist nur für die Phase nach Testschluss freigegeben, aber es ist kein allgemeines Abschlussdatum gesetzt. Diese Phase wird im Grundzeitplan nicht erreicht. Overrides können abweichen.';
$string['ad_rule_feedback_no_postattempt'] = 'Keine Standard-Ergebnis- oder Feedbackkategorie ist für die Einsicht nach dem Versuch freigegeben. Rückmeldungen innerhalb von Fragen, fragetypspezifisches Feedback und Kommunikation außerhalb von Moodle wurden nicht geprüft.';
$string['ad_rule_feedback_unknown'] = 'Die Standard-Einsichtseinstellungen sind in den gelesenen Daten unvollständig. Der Feedbackzeitpunkt lässt sich nicht ableiten.';
$string['ad_rule_feedback_no_retry'] = 'Feedback vor einem weiteren vollständigen Versuch wird hier nicht beurteilt, wenn die Grundeinstellung keine Wiederholung erkennen lässt. Der Wert von Rückmeldungen nach einem letzten Versuch wird damit nicht bewertet.';
$string['ad_rule_quiz_retry_window'] = 'Das eingestellte Öffnungs-/Abschlussintervall und die Wartezeit vor der ersten Wiederholung lassen unter den Grundregeln kein positives Zeitfenster für einen zweiten vollständigen Versuch; Bearbeitungszeit ist dabei noch nicht eingerechnet.';
$string['ad_rule_quiz_continuation'] = 'Neue Versuche bauen auf dem vorherigen Versuch auf. Das ist eine Fortsetzungsmöglichkeit, nicht automatisch eine unabhängige neue Leistungsmessung.';
$string['ad_rule_question_rules'] = 'Fragetypspezifische Abzüge, Hinweise, interaktive Wiederholungen und CodeRunner-Regeln werden nicht ausgewertet. Allein aus dem bevorzugten Testverhalten folgt nicht, ob alle Korrekturen ohne Punkteabzug möglich sind.';
$string['ad_rule_assign_single'] = 'Die Grundeinstellung sieht keinen weiteren wiedergeöffneten Aufgabenversuch vor. Das schließt die Bearbeitung eines Entwurfs/einer aktuellen Abgabe oder eine gesonderte Folgeaktivität nicht aus.';
$string['ad_rule_assign_manual'] = 'Weitere Aufgabenversuche erfordern eine manuelle Wiederöffnung. Die eingestellte Grenze erlaubt Wiederholungen, belegt aber nicht, dass alle Studierenden tatsächlich eine erhalten.';
$string['ad_rule_assign_automatic'] = 'Weitere Aufgabenversuche werden nach der Bewertung automatisch wiedergeöffnet, begrenzt durch Versuchszahl und Zugangsregeln. Das Bewertungsraster und die Behandlung früherer Fehler werden daraus nicht abgeleitet.';
$string['ad_rule_assign_untilpass'] = 'Die Wiederöffnung ist bis zum Erreichen der Bestehensgrenze automatisch vorgesehen, begrenzt durch die Versuchszahl. Bestehen ist nicht dasselbe wie volle Punktzahl; weitere Verbesserungen nach dem Bestehen sind damit nicht zugesichert.';
$string['ad_rule_assign_pass_missing'] = 'Automatische Wiederöffnung bis zum Bestehen ist ausgewählt, aber im Hauptbewertungselement wurde keine positive Bestehensgrenze gefunden. Die beabsichtigte Bestehens- und Wiederöffnungsregel muss geprüft werden.';
$string['ad_rule_assign_offline'] = 'Diese Aufgabe hat laut Moduleinstellung keinen aktivierten Abgabeworkflow. Ein Leistungsnachweis oder eine Überarbeitung außerhalb der Moodle-Abgabe kann vorhanden sein; TLA kann dies hier nicht einordnen.';
$string['ad_rule_assign_feedback_workflow'] = 'Ein Bewertungsworkflow ist aktiviert. Die Freigabe von Rückmeldungen hängt von Workflowstatus und Bewertungshandlungen ab; die Einstellungen allein belegen kein Feedback vor einer Wiederholung.';
$string['ad_rule_assign_feedback_unknown'] = 'Aus Aufgabeneinstellungen allein ergibt sich nicht, wann hilfreiche Rückmeldung verfügbar ist. Tatsächliche Bewertungs-/Freigabezeitpunkte und Feedbackinhalte werden in diesem Check nicht ausgewertet.';
$string['ad_rule_assign_team'] = 'Gruppenabgaben sind aktiviert. Ein gemeinsames Produkt/eine gemeinsame Note belegt für sich nicht den individuellen Beitrag oder die Kompetenz jedes Mitglieds. Dieser Einstellungscheck erweitert die bisherige Einzelabgaben-Zeitanalyse nicht um Gruppenabgaben.';
$string['ad_rule_assign_gradepenalty'] = 'Die Moodle-Punkteabzugsfunktion der Aufgabe ist aktiviert. Ihre konkreten Abzugsregeln werden nicht ausgewertet. TLA beobachtet diese Einstellung; es berechnet oder vergibt keine Strafnoten.';
$string['ad_rule_assign_feedback_schedule'] = 'Der geplante Bewertungstermin liegt am oder nach dem letzten Abgabetermin, obwohl Wiederholungen erlaubt sind. Plantermine sind keine tatsächlichen Feedbackzeitpunkte; prüfe, ob eine Überarbeitung nach Rückmeldung praktisch möglich ist.';
$string['ad_rule_overrides'] = 'Overrides oder individuelle Verlängerungen/Sperren sind vorhanden. Nur ihr Vorhandensein wird erkannt; effektive Regeln pro Person/Gruppe werden in diesem Design-Check nicht rekonstruiert. Grundregeln dürfen nicht auf alle übertragen werden.';
$string['ad_rule_access'] = 'Das Modul ist verborgen oder hat eine Verfügbarkeitsbedingung. Das effektive Zugangsfenster und die Bedingungen einzelner Studierender werden nicht ausgewertet.';
$string['ad_rule_unsupported'] = 'Dieser Aktivitätstyp wird vom Einstellungscheck nicht unterstützt.';
$string['ad_action_purpose'] = 'Passt diese Regel zu einer Übung, einem abschließenden Leistungsnachweis oder einer Mischform? Maßgeblich sind die veröffentlichten Lern- und Bewertungsziele; Regeln nicht allein wegen eines Hinweises rückwirkend ändern.';
$string['ad_action_feedbackcontent'] = 'Enthält die freigegebene Einsicht tatsächlich hilfreiche Hinweise? Eine erlaubte Anzeigeoption kann leer bleiben und belegt weder Lesen noch Verstehen.';
$string['ad_action_feedbackwindow'] = 'Können Studierende bei einer Übung hilfreiches Feedback vor dem nächsten erlaubten Versuch einsehen? Berücksichtige auch Feedback innerhalb von Fragen, Zugangsbedingungen und Kommunikation außerhalb von Moodle.';
$string['ad_action_questionrules'] = 'Prüfe die tatsächlichen Fragetypen und deren Wiederholungs-/Abzugsregeln gesondert, einschließlich CodeRunner, sofern verwendet. Ganze Testversuche und Wiederholungen innerhalb einer Frage sind unterschiedliche Ebenen.';
$string['ad_action_timing'] = 'Prüfe allgemeine Öffnungs-/Abschlussdaten, Wiederholungswartezeiten und gültige Overrides. Es wurde weder Bearbeitungsgeschwindigkeit geschätzt noch individueller Zugang festgestellt.';
$string['ad_action_passgrade'] = 'Prüfe die beabsichtigte Bestehensgrenze und die maximalen Versuche in Aktivität und Notenbuch.';
$string['ad_action_assignmentfeedback'] = 'Prüfe, wann Feedback freigegeben wird und ob danach eine wiedergeöffnete Abgabe zugänglich bleibt. Der geplante Bewertungstermin belegt keine tatsächliche Freigabe.';
$string['ad_action_team'] = 'Kläre bei individuell zu bewertender Kompetenz, welche gesonderten individuellen Nachweise vorhanden sind; die gemeinsame Abgabe allein kann dies nicht belegen.';
$string['ad_action_gradepenalty'] = 'Prüfe die konfigurierte Moodle-Abzugsregel und ihren veröffentlichten Bewertungszweck. Sie besteht unabhängig von TLA, das selbst keine Abzüge vergibt.';
$string['ad_action_effective'] = 'Prüfe die relevanten Overrides, Verlängerungen und Verfügbarkeitsbedingungen, bevor eine Feststellung zu Grundregeln auf alle Studierenden übertragen wird.';
$string['ad_interpret_ceiling_best'] = 'Bewertungen häufen sich nahe dem Maximum. Aktuell sind mehrere Versuche mit Bestversuchsbewertung konfiguriert; ein Deckeneffekt ist mit dieser Regel vereinbar. Das belegt weder die Ursache dieser Noten noch damals gleiche Regeln oder eigenständige Kompetenz.';
$string['ad_interpret_ceiling_last'] = 'Bewertungen häufen sich nahe dem Maximum. Aktuell zählt der letzte von mehreren Versuchen; erfolgreiche Überarbeitung ist eine mögliche Erklärung. Frühere Regeln, tatsächliches Lernen und Eigenständigkeit sind damit nicht belegt.';
$string['ad_interpret_ceiling_retained'] = 'Bewertungen häufen sich nahe dem Maximum, während die aktuelle Versuchsregel frühere Ergebnisse beibehält. Prüfe Bewertungsregel und Aktivitätszweck gemeinsam. Die Punkteverteilung allein belegt weder übernommene Lösungen noch KI-Nutzung.';
$string['ad_interpret_ceiling_untilpass'] = 'Bewertungen häufen sich nahe dem Maximum. Die Aufgabe erlaubt aktuell Wiederöffnung bis zum Bestehen, nicht zwingend Verbesserung bis zur vollen Punktzahl. Ordne die Verteilung anhand des Bewertungsrasters und realer Überarbeitungsmöglichkeiten ein; sie ist kein Täuschungsbeleg.';
$string['ad_interpret_ceiling_contextunknown'] = 'Bewertungen häufen sich nahe dem Maximum. Ohne bestätigten Aktivitätszweck und maßgeblichen Bewertungs-/Überarbeitungskontext ist das eine beschreibende Feststellung, kein Mangel und kein Beleg für geteilte Lösungen, KI-Nutzung oder eigenständiges Beherrschen.';
$string['ad_recommend_review_first_and_final'] = 'Betrachte bei Tests die vorhandenen Erst-/Letzt-/Bestversuchsaggregate neben der Endnotenverteilung. Sie umfassen nur Teilnehmende mit mehreren abgeschlossenen Versuchen und belegen kein eigenständiges Lernen. Hohe Endnoten allein begründen keine Aufgabenrandomisierung.';
$string['ad_recommend_review_assessment_design'] = 'Kläre Aktivitätszweck und gültige Bewertungs-/Überarbeitungsregeln, bevor hohe Punkte als Problem gelten. Aus der Verteilung folgt weder Täuschung noch die Notwendigkeit individueller Aufgabenvarianten.';
$string['ad_interpret_ceiling_unrandomised'] = 'Bewertungen häufen sich nahe dem Maximum bei einem Quiz, dessen Einstellungen keinen Wiederholungsmechanismus (einzelner Versuch oder unbekannter Kontext) bieten, der das erklärt. Das beweist weder geteilte Lösungen noch KI-Nutzung, aber eine studierendenindividuelle Randomisierung würde bewirken, dass eine kopierte, nicht angepasste Antwort an der eigenen Variante scheitert.';
$string['ad_recommend_parameterize_quiz'] = 'Erwäge, dieses Quiz je Studierender zu randomisieren (CodeRunner „Twig all"), damit eine geteilte Lösung nicht zur Variante anderer passt. Ein Vorschlag für Variantenraum und Snippet wird bereitgestellt; prüfe die Passung zur Aufgabe vor der Anwendung.';
$string['ad_recommend_review_difficulty'] = 'Prüfe Anleitung, Voraussetzungen, Bewertung und Umgang mit fehlenden Leistungen anhand der Lernziele. Niedrige Noten allein benennen ihre Ursache nicht.';
$string['ad_recommend_review_two_groups'] = 'Prüfe Voraussetzungen, Bewertungsraster und mögliche Effekte binärer Bewertung, bevor die beiden Häufungen interpretiert werden. Die Verteilung kann keine Ursachen zuordnen oder Täuschung feststellen.';
$string['ad_setting_attempts'] = 'Vollständige Testversuche';
$string['ad_setting_grademethod'] = 'Testbewertungsmethode';
$string['ad_setting_preferredbehaviour'] = 'Bevorzugtes Frageverhalten (nicht pro Frage verifiziert)';
$string['ad_setting_timeopen'] = 'Allgemeines Öffnungsdatum';
$string['ad_setting_timeclose'] = 'Allgemeines Abschlussdatum';
$string['ad_setting_delay1'] = 'Wartezeit vor erster Wiederholung (Sekunden)';
$string['ad_setting_delay2'] = 'Wartezeit vor weiteren Wiederholungen (Sekunden)';
$string['ad_setting_attemptonlast'] = 'Jeder Versuch baut auf dem letzten auf';
$string['ad_setting_maxattempts'] = 'Maximale Aufgabenversuche';
$string['ad_setting_attemptreopenmethod'] = 'Wiederöffnungsmethode';
$string['ad_setting_gradepass'] = 'Bestehensgrenze (Hauptbewertungselement)';
$string['ad_setting_allowsubmissionsfromdate'] = 'Abgaben ab';
$string['ad_setting_duedate'] = 'Fälligkeit (nicht letzter Abgabetermin)';
$string['ad_setting_cutoffdate'] = 'Letzter Abgabetermin';
$string['ad_setting_gradingduedate'] = 'Geplanter Bewertungstermin';
$string['ad_setting_submissiondrafts'] = 'Abgabebestätigung erforderlich';
$string['ad_setting_markingworkflow'] = 'Bewertungsworkflow';
$string['ad_setting_gradepenalty'] = 'Moodle-Punkteabzüge aktiviert';
$string['ad_method_1'] = 'Bester Versuch';
$string['ad_method_2'] = 'Durchschnitt der Versuche';
$string['ad_method_3'] = 'Erster Versuch';
$string['ad_method_4'] = 'Letzter Versuch';
$string['ad_reopen_none'] = 'Keine Wiederöffnung (Altwert)';
$string['ad_reopen_manual'] = 'Manuell';
$string['ad_reopen_automatic'] = 'Automatisch';
$string['ad_reopen_untilpass'] = 'Automatisch bis zum Bestehen';
$string['ad_standalone'] = 'Einstellungscheck ohne Verlaufsanalyse öffnen';
$string['ad_back'] = 'Zum gesamten Dashboard';
$string['periodselection'] = 'Auswertungszeitraum';
$string['perioddays'] = '{$a} Tage';
