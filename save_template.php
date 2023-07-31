<?php
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

// Ensure the user is logged in and is a teacher or admin
require_login();
require_capability('moodle/course:update', context_system::instance());

// Check if the template value is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['template'])) {
    $courseid = $_GET['courseid'] ?? 0;
    $templateid = $_POST['template'];

    if ($courseid > 0 && !empty($templateid)) {
        $data = new stdClass();
        $data->courseid = $courseid;
        $data->templateid = $templateid;
        $data->timecreated = time();

        $DB->insert_record('course_template', $data);

        // Redirect to another page or display a success message
        echo "Template has been saved for the course.";
        exit;
    }
}

// Invalid request or missing parameters
echo "Invalid request.";
exit;
?>
