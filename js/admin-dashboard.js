$('#create-class-form').on('submit', function (e) {
    e.preventDefault();
    console.log("fjwbhewbbv")

    var formData = new FormData(this);
    var formObject = {};

    formData.forEach(function (value, key) {
        formObject[key] = value;
    });

    formObject.action = 'create_class';
    $.ajax({
        url: 'admin_dashboard.php',
        type: 'POST',
        data: formObject,
        success: function (response) {
            try {
                var data = JSON.parse(response);
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.message || 'Error creating class');
                }
            } catch (e) {
                alert('An error occurred. Please try again.');
            }
        },
        error: function () {
            alert('An error occurred. Please try again.');
        }
    });
});

$(document).ready(function () {
    // Toggle class details
    $(document).on('click', '.toggle-details-btn', function () {
        var courseCard = $(this).closest('.course-card');
        courseCard.find('.course-details').slideToggle();
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });

    // Add new student to class
    $(document).on('submit', '.add-student-form', function (e) {
        e.preventDefault();
        var formData = new FormData(this);
        var formObject = {};

        formData.forEach(function (value, key) {
            formObject[key] = value;
        });

        formObject.action = 'add_student_to_class';

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'POST',
            data: formObject,
            success: function (response) {
                try {
                    var data = JSON.parse(response);
                    if (data.status === 'success') {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert(data.message || 'Error adding student to class');
                    }
                } catch (e) {
                    alert('An error occurred. Please try again.');
                }
            },
            error: function () {
                alert('An error occurred. Please try again.');
            }
        });
    });

    // Create new class

    // Add new student
    $('#add-student-form').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);
        var formObject = {};

        formData.forEach(function (value, key) {
            formObject[key] = value;
        });

        formObject.action = 'add_student';

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'POST',
            data: formObject,
            success: function (response) {
                try {
                    var data = JSON.parse(response);
                    if (data.status === 'success') {
                        alert(data.message);
                        $('#add-student-form')[0].reset();
                        loadStudents();
                    } else {
                        alert(data.message || 'Error adding student');
                    }
                } catch (e) {
                    alert('An error occurred. Please try again.');
                }
            },
            error: function () {
                alert('An error occurred. Please try again.');
            }
        });
    });

    // Mark attendance
    $('#mark-attendance-form').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);
        var formObject = {};

        formData.forEach(function (value, key) {
            formObject[key] = value;
        });

        formObject.action = 'mark_attendance';

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'POST',
            data: formObject,
            success: function (response) {
                try {
                    var data = JSON.parse(response);
                    if (data.status === 'success') {
                        alert(data.message);
                        $('#mark-attendance-form')[0].reset();
                        loadAttendanceRecords();
                    } else {
                        alert(data.message || 'Error marking attendance');
                    }
                } catch (e) {
                    alert('An error occurred. Please try again.');
                }
            },
            error: function () {
                alert('An error occurred. Please try again.');
            }
        });
    });

    // Create announcement
    $('#create-announcement-form').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);
        var formObject = {};

        formData.forEach(function (value, key) {
            formObject[key] = value;
        });

        formObject.action = 'create_announcement';

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'POST',
            data: formObject,
            success: function (response) {
                try {
                    var data = JSON.parse(response);
                    if (data.status === 'success') {
                        alert(data.message);
                        $('#create-announcement-form')[0].reset();
                        location.reload();
                    } else {
                        alert(data.message || 'Error creating announcement');
                    }
                } catch (e) {
                    alert('An error occurred. Please try again.');
                }
            },
            error: function () {
                alert('An error occurred. Please try again.');
            }
        });
    });

    // Update profile
    $('#update-profile-form').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);
        var formObject = {};

        formData.forEach(function (value, key) {
            formObject[key] = value;
        });

        formObject.action = 'update_profile';

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'POST',
            data: formObject,
            success: function (response) {
                try {
                    var data = JSON.parse(response);
                    if (data.status === 'success') {
                        alert(data.message);
                    } else {
                        alert(data.message || 'Error updating profile');
                    }
                } catch (e) {
                    alert('An error occurred. Please try again.');
                }
            },
            error: function () {
                alert('An error occurred. Please try again.');
            }
        });
    });

    // Change password
    $('#change-password-form').on('submit', function (e) {
        e.preventDefault();

        if ($('#new_password').val() !== $('#confirm_password').val()) {
            alert('New password and confirm password do not match.');
            return;
        }

        var formData = new FormData(this);
        var formObject = {};

        formData.forEach(function (value, key) {
            formObject[key] = value;
        });

        formObject.action = 'change_password';

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'POST',
            data: formObject,
            success: function (response) {
                try {
                    var data = JSON.parse(response);
                    if (data.status === 'success') {
                        alert(data.message);
                        $('#change-password-form')[0].reset();
                    } else {
                        alert(data.message || 'Error changing password');
                    }
                } catch (e) {
                    alert('An error occurred. Please try again.');
                }
            },
            error: function () {
                alert('An error occurred. Please try again.');
            }
        });
    });

    // Update profile picture
    $('#update-picture-form').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'update_profile_picture');

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                try {
                    var data = JSON.parse(response);
                    if (data.status === 'success') {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert(data.message || 'Error updating profile picture');
                    }
                } catch (e) {
                    alert('An error occurred. Please try again.');
                }
            },
            error: function () {
                alert('An error occurred. Please try again.');
            }
        });
    });

    // Save system settings
    $('#system-settings-form').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);
        var formObject = {};

        formData.forEach(function (value, key) {
            formObject[key] = value;
        });

        formObject.action = 'save_system_settings';

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'POST',
            data: formObject,
            success: function (response) {
                try {
                    var data = JSON.parse(response);
                    if (data.status === 'success') {
                        alert(data.message);
                    } else {
                        alert(data.message || 'Error saving settings');
                    }
                } catch (e) {
                    alert('An error occurred. Please try again.');
                }
            },
            error: function () {
                alert('An error occurred. Please try again.');
            }
        });
    });

    // Load students
    function loadStudents() {
        $.ajax({
            url: 'admin_dashboard.php',
            type: 'GET',
            data: {
                action: 'get_students',
                search: $('#search-student').val()
            },
            success: function (response) {
                $('#students-container').html(response);
                initStudentButtons();
            },
            error: function () {
                $('#students-container').html('<div class="error">Error loading students. Please try again.</div>');
            }
        });
    }

    // Load attendance records
    function loadAttendanceRecords() {
        $.ajax({
            url: 'admin_dashboard.php',
            type: 'GET',
            data: {
                action: 'get_attendance_records',
                class_id: $('#filter_class').val(),
                date_from: $('#filter_date_from').val(),
                date_to: $('#filter_date_to').val(),
                status: $('#filter_status').val()
            },
            success: function (response) {
                $('#attendance-records-container').html(response);
                initAttendanceButtons();
            },
            error: function () {
                $('#attendance-records-container').html('<div class="error">Error loading attendance records. Please try again.</div>');
            }
        });
    }

    // Initialize student action buttons
    function initStudentButtons() {
        $(document).off('click', '.view-student-btn').on('click', '.view-student-btn', function () {
            var studentId = $(this).data('student-id');

            $.ajax({
                url: 'admin_dashboard.php',
                type: 'GET',
                data: {
                    action: 'get_student_details',
                    student_id: studentId
                },
                success: function (response) {
                    $('#student-details-container').html(response);
                    $('#student-details-modal').show();
                },
                error: function () {
                    alert('Error loading student details. Please try again.');
                }
            });
        });

        $(document).off('click', '.delete-student-btn').on('click', '.delete-student-btn', function () {
            var studentId = $(this).data('student-id');
            var studentName = $(this).data('student-name');

            $('#confirm-message').text(`Are you sure you want to delete student "${studentName}"? This action cannot be undone.`);
            $('#confirm-action').data('action', 'delete_student');
            $('#confirm-action').data('id', studentId);
            $('#confirm-modal').show();
        });
    }

    // Initialize attendance action buttons
    function initAttendanceButtons() {
        $(document).off('click', '.edit-attendance-btn').on('click', '.edit-attendance-btn', function () {
            var attendanceId = $(this).data('id');
            var studentId = $(this).data('student-id');
            var classId = $(this).data('class-id');
            var date = $(this).data('date');
            var status = $(this).data('status');

            $('#attendance_id').val(attendanceId);
            $('#edit_attendance_student_id').val(studentId);
            $('#edit_attendance_class').val(classId);
            $('#edit_attendance_date').val(date);
            $('#edit_attendance_status').val(status);

            $('#edit-attendance-modal').show();
        });

        $(document).off('click', '.delete-attendance-btn').on('click', '.delete-attendance-btn', function () {
            var attendanceId = $(this).data('id');

            $('#confirm-message').text('Are you sure you want to delete this attendance record? This action cannot be undone.');
            $('#confirm-action').data('action', 'delete_attendance');
            $('#confirm-action').data('id', attendanceId);
            $('#confirm-modal').show();
        });
    }

    // Search student
    $('#search-btn').on('click', function () {
        loadStudents();
    });

    $('#search-student').on('keyup', function (e) {
        if (e.key === 'Enter') {
            loadStudents();
        }
    });

    // Filter attendance records
    $('#filter-attendance-btn').on('click', function () {
        loadAttendanceRecords();
    });

    // Generate QR Code
    $('#generate-qr-btn').on('click', function () {
        var classId = $('#qr_class').val();
        var expiryMinutes = $('#qr_expiry').val();

        if (!classId) {
            alert('Please select a class.');
            return;
        }

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'POST',
            data: {
                action: 'generate_qr_code',
                class_id: classId,
                expiry_minutes: expiryMinutes
            },
            success: function (response) {
                try {
                    var data = JSON.parse(response);
                    if (data.status === 'success') {
                        $('#qr-code-container').empty();
                        new QRCode(document.getElementById("qr-code-container"), {
                            text: data.qr_data,
                            width: 256,
                            height: 256
                        });
                        $('#qr-code-display').show();

                        // Start countdown timer
                        var expirySeconds = expiryMinutes * 60;
                        var timerDisplay = $('#qr-timer');

                        function updateTimer() {
                            var minutes = Math.floor(expirySeconds / 60);
                            var seconds = expirySeconds % 60;
                            timerDisplay.text(minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0'));

                            if (expirySeconds <= 0) {
                                clearInterval(timerInterval);
                                $('#qr-code-display').hide();
                                alert('QR code has expired.');
                            } else {
                                expirySeconds--;
                            }
                        }

                        updateTimer();
                        var timerInterval = setInterval(updateTimer, 1000);

                    } else {
                        alert(data.message || 'Error generating QR code');
                    }
                } catch (e) {
                    alert('An error occurred. Please try again.');
                }
            },
            error: function () {
                alert('An error occurred. Please try again.');
            }
        });
    });

    // QR Settings
    $(document).on('click', '.qr-settings-btn', function () {
        var classId = $(this).data('class-id');
        var lateThreshold = $(this).data('threshold');

        $('#qr_settings_class_id').val(classId);
        $('#late_threshold_input').val(lateThreshold);
        $('#qr-settings-modal').show();
    });

    $('#qr-settings-form').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);
        var formObject = {};

        formData.forEach(function (value, key) {
            formObject[key] = value;
        });

        formObject.action = 'save_qr_settings';

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'POST',
            data: formObject,
            success: function (response) {
                try {
                    var data = JSON.parse(response);
                    if (data.status === 'success') {
                        alert(data.message);
                        $('#qr-settings-modal').hide();
                        location.reload();
                    } else {
                        alert(data.message || 'Error saving QR settings');
                    }
                } catch (e) {
                    alert('An error occurred. Please try again.');
                }
            },
            error: function () {
                alert('An error occurred. Please try again.');
            }
        });
    });

    // View attendance
    $(document).on('click', '.view-attendance-btn', function () {
        var studentId = $(this).data('student-id');
        var classId = $(this).data('class-id');

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'GET',
            data: {
                action: 'get_student_attendance',
                student_id: studentId,
                class_id: classId
            },
            success: function (response) {
                $('#attendance-details-container').html(response);
                $('#attendance-details-modal').show();
            },
            error: function () {
                alert('Error loading attendance details. Please try again.');
            }
        });
    });

    // View grades
    $(document).on('click', '.view-grades-btn', function () {
        var studentId = $(this).data('student-id');
        var classId = $(this).data('class-id');

        $('#grade_student_id').val(studentId);
        $('#grade_class_id').val(classId);

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'GET',
            data: {
                action: 'get_student_grades',
                student_id: studentId,
                class_id: classId
            },
            success: function (response) {
                $('#grades-container').html(response);
                $('#grades-modal').show();

                // Initialize delete grade buttons
                initGradeButtons();

                // Initialize grade chart if it exists
                if ($('#grade-chart').length) {
                    var ctx = document.getElementById('grade-chart').getContext('2d');
                    var chartData = JSON.parse($('#grade-chart-data').val());

                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: chartData.labels,
                            datasets: [{
                                label: 'Grade (%)',
                                data: chartData.data,
                                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                                borderColor: 'rgba(54, 162, 235, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: 100
                                }
                            }
                        }
                    });
                }
            },
            error: function () {
                alert('Error loading grades. Please try again.');
            }
        });
    });

    // Initialize grade buttons
    function initGradeButtons() {
        $(document).off('click', '.delete-grade-btn').on('click', '.delete-grade-btn', function () {
            var gradeId = $(this).data('id');

            $('#confirm-message').text('Are you sure you want to delete this grade? This action cannot be undone.');
            $('#confirm-action').data('action', 'delete_grade');
            $('#confirm-action').data('id', gradeId);
            $('#confirm-modal').show();
        });
    }

    // Add grade
    $('#add-grade-form').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);
        var formObject = {};

        formData.forEach(function (value, key) {
            formObject[key] = value;
        });

        formObject.action = 'add_grade';

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'POST',
            data: formObject,
            success: function (response) {
                try {
                    var data = JSON.parse(response);
                    if (data.status === 'success') {
                        alert(data.message);

                        // Refresh grades
                        var studentId = $('#grade_student_id').val();
                        var classId = $('#grade_class_id').val();

                        $.ajax({
                            url: 'admin_dashboard.php',
                            type: 'GET',
                            data: {
                                action: 'get_student_grades',
                                student_id: studentId,
                                class_id: classId
                            },
                            success: function (response) {
                                $('#grades-container').html(response);
                                initGradeButtons();
                            }
                        });

                        $('#add-grade-form')[0].reset();
                    } else {
                        alert(data.message || 'Error adding grade');
                    }
                } catch (e) {
                    alert('An error occurred. Please try again.');
                }
            },
            error: function () {
                alert('An error occurred. Please try again.');
            }
        });
    });

    // Class grades
    $(document).on('click', '.view-grades-class-btn', function () {
        var classId = $(this).data('class-id');

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'GET',
            data: {
                action: 'get_class_grades',
                class_id: classId
            },
            success: function (response) {
                $('#grades-container').html(response);
                $('#grades-modal').show();
            },
            error: function () {
                alert('Error loading class grades. Please try again.');
            }
        });
    });

    // Delete class
    $(document).on('click', '.delete-class-btn', function () {
        var classId = $(this).data('class-id');

        $('#confirm-message').text('Are you sure you want to delete this class? All related data including students, grades, and attendance will be deleted. This action cannot be undone.');
        $('#confirm-action').data('action', 'delete_class');
        $('#confirm-action').data('id', classId);
        $('#confirm-modal').show();
    });

    // Edit announcement
    $(document).on('click', '.edit-announcement-btn', function () {
        var id = $(this).data('id');
        var title = $(this).data('title');
        var message = $(this).data('message');
        var classId = $(this).data('class-id');

        $('#edit_announcement_id').val(id);
        $('#edit_announcement_title').val(title);
        $('#edit_announcement_message').val(message);
        $('#edit_announcement_class').val(classId);

        $('#edit-announcement-modal').show();
    });

    $('#edit-announcement-form').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);
        var formObject = {};

        formData.forEach(function (value, key) {
            formObject[key] = value;
        });

        formObject.action = 'update_announcement';

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'POST',
            data: formObject,
            success: function (response) {
                try {
                    var data = JSON.parse(response);
                    if (data.status === 'success') {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert(data.message || 'Error updating announcement');
                    }
                } catch (e) {
                    alert('An error occurred. Please try again.');
                }
            },
            error: function () {
                alert('An error occurred. Please try again.');
            }
        });
    });

    // Delete announcement
    $(document).on('click', '.delete-announcement-btn', function () {
        var id = $(this).data('id');

        $('#confirm-message').text('Are you sure you want to delete this announcement? This action cannot be undone.');
        $('#confirm-action').data('action', 'delete_announcement');
        $('#confirm-action').data('id', id);
        $('#confirm-modal').show();
    });

    // Generate report
    $(document).on('click', '.generate-report-btn', function () {
        var classId = $(this).data('class-id');

        window.location.href = 'report.php?class_id=' + classId;
    });

    // Edit attendance form submission
    $('#edit-attendance-form').on('submit', function (e) {
        e.preventDefault();

        var formData = new FormData(this);
        var formObject = {};

        formData.forEach(function (value, key) {
            formObject[key] = value;
        });

        formObject.action = 'update_attendance';

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'POST',
            data: formObject,
            success: function (response) {
                try {
                    var data = JSON.parse(response);
                    if (data.status === 'success') {
                        alert(data.message);
                        $('#edit-attendance-modal').hide();
                        loadAttendanceRecords();
                    } else {
                        alert(data.message || 'Error updating attendance');
                    }
                } catch (e) {
                    alert('An error occurred. Please try again.');
                }
            },
            error: function () {
                alert('An error occurred. Please try again.');
            }
        });
    });

    // Confirm action button
    $('#confirm-action').on('click', function () {
        var action = $(this).data('action');
        var id = $(this).data('id');

        $.ajax({
            url: 'admin_dashboard.php',
            type: 'POST',
            data: {
                action: action,
                id: id
            },
            success: function (response) {
                try {
                    var data = JSON.parse(response);
                    if (data.status === 'success') {
                        alert(data.message);
                        $('#confirm-modal').hide();

                        if (action === 'delete_student') {
                            loadStudents();
                        } else if (action === 'delete_attendance') {
                            loadAttendanceRecords();
                        } else if (action === 'delete_grade') {
                            // Refresh grades
                            var studentId = $('#grade_student_id').val();
                            var classId = $('#grade_class_id').val();

                            $.ajax({
                                url: 'admin_dashboard.php',
                                type: 'GET',
                                data: {
                                    action: 'get_student_grades',
                                    student_id: studentId,
                                    class_id: classId
                                },
                                success: function (response) {
                                    $('#grades-container').html(response);
                                    initGradeButtons();
                                }
                            });
                        } else {
                            location.reload();
                        }
                    } else {
                        alert(data.message || 'Error performing action');
                    }
                } catch (e) {
                    alert('An error occurred. Please try again.');
                }
            },
            error: function () {
                alert('An error occurred. Please try again.');
            }
        });
    });

    // Close modal
    $(document).on('click', '.close-modal, #cancel-confirm', function () {
        $(this).closest('.modal').hide();
    });

    // Close modal when clicking outside
    $(window).on('click', function (e) {
        if ($(e.target).hasClass('modal')) {
            $('.modal').hide();
        }
    });

    // Load initial data
    loadStudents();
    loadAttendanceRecords();

    // Tab navigation
    $('.nav-link').on('click', function (e) {
        e.preventDefault();
        var tab = $(this).attr('href').substring(1);

        // Update URL with tab
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.pushState({}, '', url);

        $('.nav-link').removeClass('active');
        $(this).addClass('active');

        $('.tab-content').removeClass('active');
        $('#' + tab + '-tab').addClass('active');
    });

    // Check URL for active tab on page load
    function setActiveTabFromUrl() {
        var url = new URL(window.location.href);
        var tab = url.searchParams.get('tab');

        if (tab) {
            $('.nav-link').removeClass('active');
            $(`a.nav-link[href="#${tab}"]`).addClass('active');

            $('.tab-content').removeClass('active');
            $('#' + tab + '-tab').addClass('active');
        }
    }

    // Set active tab from URL on page load
    setActiveTabFromUrl();
});