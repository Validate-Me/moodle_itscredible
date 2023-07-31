<?php
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

// Ensure the user is logged in and is a teacher or admin
require_login();
require_capability('moodle/course:update', context_system::instance());
$PAGE->set_url('/local/certificate_selector/select_course_name.php');

// Check if the template value is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseid = $_POST['courseid'] ?? 0;
    $templateid = $_POST['template'] ?? '';
    $thumbnailurl = $_POST['thumbnailurl'] ?? '';

    if ($courseid > 0 && !empty($templateid) && !empty($thumbnailurl)) {
        // Check if there is an existing record for the course ID
        $existingRecord = $DB->get_record('course_template', ['courseid' => $courseid]);
        $course = $DB->get_record('course', ['id' => $courseid], 'fullname');
        if ($existingRecord) {
            // Update the existing record with the latest template ID and thumbnail URL
            $existingRecord->templateid = $templateid;
            $existingRecord->thumbnail_url = $thumbnailurl;
            $existingRecord->timecreated = time();
            $DB->update_record('course_template', $existingRecord);
        } else {
            // Insert a new record with the latest template ID and thumbnail URL
            $data = new stdClass();
            $data->courseid = $courseid;
            $data->templateid = $templateid;
            $data->thumbnail_url = $thumbnailurl;
            $data->timecreated = time();
            $DB->insert_record('course_template', $data);
        }
        $coursename = $course->fullname;
        // Log the selected template and course ID
        echo "<script>console.log('Selected Template ID: $templateid, Course ID: $courseid');</script>";

        // Display a success message
        echo "<script>alert('Template has been saved for the course $coursename');</script>";
    } elseif ($courseid > 0) {
        // If the template ID is not provided (empty string), it means we want to delete the template for the course.
        // Delete the template data from the database for the given course ID.
        $DB->delete_records('course_template', ['courseid' => $courseid]);

        // Display a success message
        $course = $DB->get_record('course', ['id' => $courseid], 'fullname');
        $coursename = $course->fullname;
        echo "<script>alert('Template has been deleted for the course $coursename');</script>";
    }
}

// Set up the page context
$PAGE->set_context(context_system::instance());

// Set up the page parameters
$PAGE->set_pagelayout('standard');

// Display the course selection form
echo $OUTPUT->header();
?>

<h2 style="font-size: 24px; display: flex; justify-content: center;"> Select a Template </h2>

<?php
// Get a list of all courses
$courses = $DB->get_records_select('course', 'category <> 0', [], 'fullname');
$templates = $DB->get_records_menu('course_template', [], '', 'courseid,thumbnail_url');

if (!empty($courses)) {
    echo '<div class="course-list">';
    foreach ($courses as $course) {
        $courseId = $course->id;
        $thumbnail_url = $templates[$courseId] ?? null;

        echo '<div class="course-item" data-courseid="' . $courseId . '">';
        echo '<h5 class="course-name" style="display: flex; align-items: center;"> ' . $course->fullname . '</h5>';

        if ($thumbnail_url === null) {
            echo '<span class="template-id" onclick="openTemplateDialog(' . $courseId . ');">
                <img src="https://svgshare.com/i/vY9.svg" alt="-" style="cursor: pointer; width: 50px"/>
            </span>';
        } else {
            echo '<span class="template-id" onclick="openTemplateDialog(' . $courseId . ');">
                <img src="' . $thumbnail_url . '" alt="+" style="cursor: pointer; height: 100%; width: 100%; object-fit: contain;" />
                <span class="delete-icon" onclick="deleteTemplate(' . $courseId . '); event.stopPropagation();">
                    <img src="https://cdn-icons-png.flaticon.com/512/3405/3405244.png" alt="Delete" style="width: 20px; height: 20px;cursor: pointer;" />
                </span>
            </span>';
        }

        echo '</div>';
    }
    echo '</div>';
} else {
    echo 'No courses available.';
}

echo $OUTPUT->footer();
?>

<div id="popupContainer" class="popup-dialog" style="display: none;">
    <div class="popup-header">
        <h3>Select a Template</h3>
        <span class="popup-close-icon" onclick="closePopup();">&times;</span>
    </div>
    <div id="templateList" class="template-list-container">
        <!-- Templates will be dynamically added here -->
    </div>
    <input type="hidden" id="selectedTemplateId" name="template" value="" />
    <input type="hidden" id="selectedThumbnailUrl" name="thumbnailurl" value="" />
    <button id="saveTemplateBtn" onclick="saveSelectedTemplate();" class="save-button">Select</button>
</div>

<script>
    // Function to show/hide delete icon on hover
    function toggleDeleteIcon(courseId, show) {
        var deleteIcon = document.querySelector('.course-item[data-courseid="' + courseId + '"] .delete-icon');
        if (deleteIcon) {
            deleteIcon.style.display = show ? 'block' : 'none';
        }
    }

    // Add event listeners to show/hide delete icon on hover
    var courseItems = document.querySelectorAll('.course-item');
courseItems.forEach(function (courseItem) {
    courseItem.lastChild.addEventListener('mouseenter', function () {
        var courseId = courseItem.getAttribute('data-courseid');
        toggleDeleteIcon(courseId, true);
    });
    courseItem.lastChild.addEventListener('mouseleave', function () {
        var courseId = courseItem.getAttribute('data-courseid');
        toggleDeleteIcon(courseId, false);
    });
});


    // Function to open the template popup
    function openTemplateDialog(courseId) {
        // Show the popup container
        document.getElementById('popupContainer').style.display = 'block';
        // Set the selected course ID
        selectedCourseId = courseId;

        // Call a function to get the access token
        var accessToken = getAccessToken();
        if (accessToken) {
            // Call the API to get the JSON response
            var apiUrl = 'https://portal.itscredible.com/api/v1/templates';

            // Create a new XMLHttpRequest object
            var xhr = new XMLHttpRequest();

            // Configure the GET request
            xhr.open('GET', apiUrl);
            xhr.setRequestHeader('Authorization', 'itscredible ' + accessToken);

            // Define the onload callback function
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var templates = JSON.parse(xhr.responseText);

                    // Display the templates in the popup
                    var templateList = document.getElementById('templateList');
                    templateList.innerHTML = ''; // Clear any existing templates

                    if (templates && templates.response && templates.response.certificateTemplates) {
                        templates.response.certificateTemplates.forEach(function(template) {
                            var templateId = template._id;
                            var imageUrl = template.thumb.large;

                            var templateDiv = document.createElement('div');
                            templateDiv.className = 'template-wrapper';
                            templateDiv.onclick = function() {
                                selectTemplate(templateId, imageUrl);
                            };

                            templateDiv.setAttribute('data-templateid', templateId); 
                            var templateImg = document.createElement('img');
                            templateImg.src = imageUrl;
                            templateImg.alt = template.title;
                            templateImg.style.width = '200px';
                            templateImg.style.height = '160px';

                            templateDiv.appendChild(templateImg);
                            templateList.appendChild(templateDiv);
                        });
                    }
                } else {
                    console.error('Failed to fetch templates from the server. Status code: ' + xhr.status);
                }
            };

            // Send the request
            xhr.send();
        } else {
            console.error('Failed to get access token.');
        }
    }

    // Function to close the template popup
    function closePopup() {
        document.getElementById('popupContainer').style.display = 'none';
    }

    // Function to save the selected template
// Function to save the selected template
function saveSelectedTemplate() {
    var selectedTemplateId = document.getElementById('selectedTemplateId').value;

    // Hide the popup container after saving
    document.getElementById('popupContainer').style.display = 'none';

    // Check if a template is selected
    if (selectedTemplateId) {
        // Get the selected thumbnail URL
        var selectedThumbnailUrl = '';
        var selectedTemplate = document.querySelector('.template-wrapper.selected img');
        if (selectedTemplate) {
            selectedThumbnailUrl = selectedTemplate.getAttribute('src');
        }

        // Update the database with the selected courseId, templateId, and thumbnailUrl
        var formData = new FormData();
        formData.append('courseid', selectedCourseId);
        formData.append('template', selectedTemplateId);
        formData.append('thumbnailurl', selectedThumbnailUrl);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);
        xhr.onload = function () {
            if (xhr.status === 200) {
                console.log('Data saved successfully.');

                // Update the thumbnail for the corresponding course
                var courseItem = document.querySelector('.course-item[data-courseid="' + selectedCourseId + '"]');
                if (courseItem) {
                    var thumbnailContainer = courseItem.querySelector('.template-id');
                    if (thumbnailContainer) {
                        thumbnailContainer.innerHTML = '<img src="' + selectedThumbnailUrl + '" alt="+" style="cursor: pointer; height: 100%; width: 100%; object-fit: contain;"/>';
                    }
                }

                // Re-register event listeners for course items
                var courseItems = document.querySelectorAll('.course-item');
                courseItems.forEach(function (courseItem) {
                    courseItem.lastChild.addEventListener('mouseenter', function () {
                        var courseId = courseItem.getAttribute('data-courseid');
                        toggleDeleteIcon(courseId, true);
                    });
                    courseItem.lastChild.addEventListener('mouseleave', function () {
                        var courseId = courseItem.getAttribute('data-courseid');
                        toggleDeleteIcon(courseId, false);
                    });
                });
            } else {
                console.error('Failed to save data.');
            }
        };
        xhr.send(formData);
    } else {
        console.log('No template selected.');
    }
}


    // Function to get the access token
    function getAccessToken() {
        var apiUrl = 'https://auth.itscredible.com/oauth2/token';
        var clientId = '7n29ei1688c27j285sb7ivpep';
        var clientSecret = 'mjem3n0nr9ddm7454j4rqm0n14mf5g2u6caaesgphucolr95g83';

        // Prepare the request data
        var data = {
            'grant_type': 'client_credentials',
            'client_id': clientId,
            'client_secret': clientSecret
        };

        // Initialize cURL
        var xhr = new XMLHttpRequest();
        xhr.open('POST', apiUrl, false);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        // Send the request
        xhr.send(encodeFormData(data));

        // Check for cURL errors
        if (xhr.status !== 200) {
            console.error('Failed to get access token.');
            return null;
        }

        var response = JSON.parse(xhr.responseText);
        if (response.access_token) {
            return response.access_token;
        } else {
            console.error('Failed to get access token.');
            return null;
        }
    }

    // Function to encode form data
    function encodeFormData(data) {
        var pairs = [];
        for (var name in data) {
            var value = data[name].toString();
            var pair = encodeURIComponent(name) + '=' + encodeURIComponent(value);
            pairs.push(pair);
        }
        return pairs.join('&');
    }
    function selectTemplate(templateId, imageUrl) {
        // Remove the "selected" class from all templates
        var templates = document.querySelectorAll('.template-wrapper');
        templates.forEach(function (template) {
            template.classList.remove('selected');
        });

        // Add the "selected" class to the clicked template
        var selectedTemplate = document.querySelector('.template-wrapper[data-templateid="' + templateId + '"]');
        if (selectedTemplate) {
            selectedTemplate.classList.add('selected');
            // Set the selected template ID and thumbnail URL
            document.getElementById('selectedTemplateId').value = templateId;
            document.getElementById('selectedThumbnailUrl').value = imageUrl;
        }
    }


    // Function to delete the selected template
// Function to delete the selected template
function deleteTemplate(courseId) {
    if (confirm('Are you sure you want to delete the template for this course?')) {
        // Delete the template data from the database
        var formData = new FormData();
        formData.append('courseid', courseId);
        formData.append('template', ''); // Set the template ID to an empty string to remove the template
        formData.append('thumbnailurl', ''); // Set the thumbnail URL to an empty string to remove the thumbnail

        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);
        xhr.onload = function () {
            if (xhr.status === 200) {
                console.log('Template deleted successfully.');

                // Remove the template thumbnail from the course list
                var courseItem = document.querySelector('.course-item[data-courseid="' + courseId + '"]');
                if (courseItem) {
                    var thumbnailContainer = courseItem.querySelector('.template-id');
                    if (thumbnailContainer) {
                        thumbnailContainer.innerHTML = '<img src="https://svgshare.com/i/vY9.svg" alt="-" style="cursor: pointer; width: 50px"/>';
                    }
                }
            } else {
                console.error('Failed to delete template.');
            }
        };
        xhr.send(formData);
    }
}


</script>

<style>
    .template-list-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        overflow-y: scroll;
        height: 80%;
    }

    .template-wrapper {
        margin: 10px;
        transition: box-shadow 0.3s ease;
        cursor:pointer;
        /* Add smooth transition */
    }

    .template-wrapper.selected {
        border: 3px solid blue;
    }

    .template-wrapper:hover {
        box-shadow: 0 0 5px rgba(0, 0, 0, 0.3);
    }

    .save-button {
        display: block;
        margin: 20px auto;
        /* Center the button horizontally */
        padding: 10px 20px;
        background-color: #007bff;
        color: #fff;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        cursor: pointer;
    }

    .save-button:hover {
        background-color: #0056b3;
    }

    .course-item {
        padding-top: 20px;
        display: flex;
        justify-content: space-between;
    }

    .course-name {
        display: flex;
        align-items: center;
    }

    .template-id {
        border: 1px dashed #d1d3d4;
        width: 200px;
        height: 120px;
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative;
    }

    .delete-icon {
        position: absolute;
        top: -10px;
        right: -10px;
        border: 1px solid #d1d3d4;
        border-radius: 12px;
        padding: 3px;
        display: none;
    }

    .popup-dialog {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        padding: 16px;
        background-color: #ffffff;
        border: 1px solid #cccccc;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        z-index: 9999;
        width: 400px;
        max-width: 100%;
        height: 85%;
        width: 80%;
    }

    .popup-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: #007bff;
        background-color: #fff;
    }

    .popup-close-icon {
        cursor: pointer;
        font-size: 30px;
    }

    .popup-close-icon:hover {
        color: #0056b3;
    }
</style>
