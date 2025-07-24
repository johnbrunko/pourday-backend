// File: js/weather_upload.js

$(document).ready(function() {
    // --- Element Selectors ---
    const projectSelect = $('#projectSelection');
    const taskSelect = $('#taskSelection');
    const weatherForm = $('#weatherFetchForm');
    const statusMessage = $('#statusMessage');
    const weatherResults = $('#weatherResults');
    const manualInputSection = $('#manualInputSection');
    const csvUploadSection = $('#csvUploadSection');
    // ADDED: Selector for the zip code input
    const zipCodeInput = $('#zipCode');

    // --- Populate hour dropdowns ---
    function populateHourSelects() {
        const hourDropdowns = $('#startHour, #endHour, #csvStartHour, #csvEndHour');
        hourDropdowns.empty();
        for (let i = 1; i <= 12; i++) {
            hourDropdowns.append($('<option>', { value: i, text: i }));
        }
    }

    // --- Set default date/time values ---
    function setDefaultTimes() {
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        const todayString = `${yyyy}-${mm}-${dd}`;

        $('#startDate, #endDate').val(todayString);
        $('#startHour, #csvStartHour').val('8');
        $('#startAmPm, #csvStartAmPm').val('AM');
        $('#endHour, #csvEndHour').val('5');
        $('#endAmPm, #csvEndAmPm').val('PM');
    }

    // --- Helper function to format time for the backend ---
    function formatTimeForBackend(hourEl, amPmEl) {
        let hour = parseInt($(hourEl).val(), 10);
        const amPm = $(amPmEl).val();
        if (amPm === 'PM' && hour < 12) hour += 12;
        if (amPm === 'AM' && hour === 12) hour = 0;
        return String(hour).padStart(2, '0') + ':00:00';
    }

    // --- Helper function to get full datetime string for API call ---
    function getFullIsoDateTime(dateEl, hourEl, amPmEl) {
        const dateString = $(dateEl).val();
        const timeString = formatTimeForBackend(hourEl, amPmEl);
        return `${dateString}T${timeString}`;
    }

    // --- Chained Dropdown Logic ---
    projectSelect.on('change', function() {
        const projectId = $(this).val();
        taskSelect.html('<option value="">Loading...</option>').prop('disabled', true);
        
        // MODIFIED: Clear zip code when project changes
        zipCodeInput.val('');

        if (!projectId) {
            taskSelect.html('<option value="">Select a project first</option>');
            return;
        }
        $.ajax({
            url: 'api/weather_form_helper.php?action=get_tasks_for_project',
            type: 'GET',
            data: { project_id: projectId },
            dataType: 'json',
            success: function(response) {
                // --- MODIFICATION START ---
                // Populate the zip code field from the response
                if (response.success && response.zip) {
                    zipCodeInput.val(response.zip);
                }
                // --- MODIFICATION END ---

                taskSelect.empty().append('<option value="">-- Select Task --</option>');
                if (response.success && response.data.length > 0) {
                    response.data.forEach(task => {
                        taskSelect.append($('<option>', { value: task.id, text: task.title }));
                    });
                    taskSelect.prop('disabled', false);
                } else {
                    taskSelect.html('<option value="">No tasks found</option>');
                }
            }
        });
    });

    // --- Data Source Toggle Logic ---
    $('input[name="dataSource"]').on('change', function() {
        const isManual = $(this).val() === 'manual';
        manualInputSection.toggle(isManual);
        csvUploadSection.toggle(!isManual);
        $('#manualInputSection').find('input, select').prop('required', isManual);
        $('#csvUploadSection').find('input, select').prop('required', !isManual);
    });

    // --- Form Submission Logic ---
    weatherForm.on('submit', function(e) {
        e.preventDefault();
        statusMessage.html('<div class="alert alert-info">Processing data... Please wait.</div>');
        weatherResults.empty();

        const selectedDataSource = $('input[name="dataSource"]:checked').val();
        const taskId = taskSelect.val();

        if (!taskId) {
            statusMessage.html('<div class="alert alert-danger">Please select a Task.</div>');
            return;
        }
        
        let ajaxUrl;
        let formData;

        if (selectedDataSource === 'manual') {
            ajaxUrl = 'api/weather_handler.php';
            formData = {
                task_id: taskId,
                zip_code: zipCodeInput.val(), // Use the selector here
                concrete_temp: $('#concreteTemp').val(),
                start_date: getFullIsoDateTime('#startDate', '#startHour', '#startAmPm'),
                end_date: getFullIsoDateTime('#endDate', '#endHour', '#endAmPm')
            };
        } else { // CSV
            ajaxUrl = 'api/csv_weather_handler.php';
            formData = new FormData();
            formData.append('task_id', taskId);
            formData.append('csv_file', $('#csvFile')[0].files[0]);
            formData.append('csv_filter_start_time', formatTimeForBackend('#csvStartHour', '#csvStartAmPm'));
            formData.append('csv_filter_end_time', formatTimeForBackend('#csvEndHour', '#csvEndAmPm'));
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            processData: selectedDataSource === 'manual',
            contentType: selectedDataSource === 'manual' ? 'application/x-www-form-urlencoded; charset=UTF-8' : false,
            success: function(response) {
                if (response.success) {
                    statusMessage.html(`<div class="alert alert-success">${response.message}</div>`);
                    if (response.weatherData && response.weatherData.length > 0) {
                        let tableHtml = `
                            <h4 class="mt-4">Saved Weather Data</h4>
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Temp (&deg;F)</th>
                                        <th>Humidity (%)</th>
                                        <th>Wind (mph)</th>
                                        <th>Conditions</th>
                                        <th>Evap. Rate</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                        response.weatherData.forEach(hour => {
                            const recordDate = new Date(hour.record_datetime);
                            const formattedDateTime = recordDate.toLocaleString();
                            const formattedEvapRate = parseFloat(hour.evap_rate).toFixed(4);
                            tableHtml += `
                                <tr>
                                    <td>${formattedDateTime}</td>
                                    <td>${hour.temperature}</td>
                                    <td>${hour.humidity}</td>
                                    <td>${hour.windspeed}</td>
                                    <td>${hour.conditions || 'N/A'}</td>
                                    <td>${formattedEvapRate}</td>
                                </tr>`;
                        });
                        tableHtml += '</tbody></table>';
                        weatherResults.html(tableHtml);
                    }
                } else {
                    statusMessage.html(`<div class="alert alert-danger">${response.message}</div>`);
                }
            },
            error: function() {
                statusMessage.html('<div class="alert alert-danger">An unexpected server error occurred.</div>');
            }
        });
    });

    // --- Initial page setup ---
    populateHourSelects();
    setDefaultTimes();
    $('input[name="dataSource"]:checked').trigger('change');
});