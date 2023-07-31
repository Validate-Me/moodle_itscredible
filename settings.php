
<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_certificate_selector',
        get_string('certificate_selector', 'local_certificate_selector')
    );

    // Add settings fields to the $settings object here

    if (has_capability('moodle/course:update', context_system::instance())) {
        // Settings for teachers/admins
        $settings->add(new admin_setting_heading('local_certificate_selector/teachers', 'Teacher/Admin Settings', ''));

        // Add teacher/admin-specific settings here

        // Link to the manage templates page for teachers/admins
        $manage_templates_url = new moodle_url('/local/certificate_selector/select_course_name.php');
        $settings->add(new admin_setting_heading('local_certificate_selector/teachers_link', 'Manage Templates', html_writer::link($manage_templates_url, 'Go to Manage Templates')));
    }

    // if (has_capability('moodle/student:view', context_system::instance())) {
    //     // Settings for students
    //     $settings->add(new admin_setting_heading('local_certificate_selector/students', 'Student Settings', ''));

    //     // Add student-specific settings here

    //     // Link to the get certificate page for students
    //     $get_certificate_url =  new moodle_url('/local/certificate_selector/select_course_name.php');

    //     $settings->add(new admin_setting_heading('local_certificate_selector/students_link', 'Get Certificate', html_writer::link($get_certificate_url, 'Go to Get Certificate')));
    // }

    // Add more general plugin settings if needed

    $ADMIN->add('localplugins', $settings);
}
