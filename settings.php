<?php
defined('MOODLE_INTERNAL') || die();
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_tla', get_string('pluginname', 'local_tla'));
    $ADMIN->add('localplugins', $settings);
    // Deadline-Ampel (Anteil Abgaben in letzten 24h).
    $settings->add(new admin_setting_configtext('local_tla/deadline_yellow',
        get_string('deadline_yellow', 'local_tla'), '', '0.25', PARAM_FLOAT));
    $settings->add(new admin_setting_configtext('local_tla/deadline_red',
        get_string('deadline_red', 'local_tla'), '', '0.50', PARAM_FLOAT));
    // Mindest-Stichprobengroesse fuer belastbare Ampeln/Prognosen.
    $settings->add(new admin_setting_configtext('local_tla/min_observations',
        get_string('min_observations', 'local_tla'), '', '8', PARAM_INT));
}
