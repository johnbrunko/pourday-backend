$(document).ready(function() {

    const editModal = new bootstrap.Modal(document.getElementById('editTaskModal'));
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));

    // Function to populate modal dropdowns
    function populateModalDropdowns() {
        const projectOptions = $('#project_id').html();
        $('#editProjectId').html(projectOptions);
        const userOptions = $('#assigned_to_user_id').html();
        $('#editAssignedTo').html(userOptions);
    }
    populateModalDropdowns(); // Call on page load

    // Helper function for Bootstrap Toasts
    function showToast(title, message, type = 'success') {
        const toastEl = document.getElementById('appToast');
        const toastTitle = document.getElementById('toastTitle');
        const toastBody = document.getElementById('toastBody');
        const toastHeader = toastEl.querySelector('.toast-header');

        // Reset and set header classes for color
        toastHeader.className = 'toast-header text-white'; // Reset
        toastHeader.classList.add(type === 'success' ? 'bg-success' : 'bg-danger');

        toastTitle.textContent = title;
        toastBody.textContent = message;

        const bsToast = new bootstrap.Toast(toastEl);
        bsToast.show();
    }

    // --- DataTable for Open Tasks ---
    var openTasksTable = $('#openTasksTable').DataTable({
        "processing": true,
        "ajax": {
            "url": "api/task_actions.php",
            "type": "GET",
            "data": { "request_type": "get_open_tasks" }, // Changed request_type
            "dataSrc": "data",
            "error": function(xhr) {
                showToast('Load Error', 'Failed to load open task data. Check API endpoint and server logs.', 'danger');
                console.error("Open Tasks DataTables AJAX error: ", xhr.responseText);
            }
        },
        "columns": [
            { "data": 0, "visible": false }, // ID
            { "data": 1 }, // Task Title
            { "data": 2 }, // Project
            { "data": 3 }, // Upload Code
            { "data": 4 }, // Scheduled
            { "data": 5 }, // Assigned To
            { "data": 6 }, // Task Types
            { "data": 7, "orderable": false, "searchable": false } // Actions
            // Note: completed_at is not included in the data array for open tasks
        ],
        "order": [[ 4, 'desc' ]] // Default order by scheduled date
    });

    // --- DataTable for Completed Tasks ---
    var completedTasksTable = $('#completedTasksTable').DataTable({
        "processing": true,
        "ajax": {
            "url": "api/task_actions.php",
            "type": "GET",
            "data": { "request_type": "get_completed_tasks" }, // New request_type
            "dataSrc": "data",
            "error": function(xhr) {
                showToast('Load Error', 'Failed to load completed task data. Check API endpoint and server logs.', 'danger');
                console.error("Completed Tasks DataTables AJAX error: ", xhr.responseText);
            }
        },
        "columns": [
            { "data": 0, "visible": false }, // ID (from API: $row['id'])
            {   // Task Title (from API: $row['title'])
                "data": 1,
                "title": "Task",
                // Removed strikethrough and date, as per request.
                // The rowCallback handles the row highlight.
                // The Completed On column handles the date.
                "render": function(data, type, row) {
                    if (type === 'display') {
                        return data;
                    }
                    return data;
                }
            },
            { "data": 2 }, // Project (from API: $row['project_name'])
            { "data": 3 }, // Sq Footage (from API: $row['sq_ft'])
            {   // Billable Y/N (from API: $row['billable'])
                "data": 4,
                "title": "Billable",
                "render": function(data, type, row) {
                    return data == 1 ? 'Yes' : 'No';
                }
            },
            {   // Completed On (from API: $row['completed_at'])
                "data": 5,
                "title": "Completed On",
                "render": function(data, type, row) {
                    if (type === 'display' && data) {
                        return new Date(data).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                    }
                    return ''; // Empty if no date
                }
            },
            { "data": 6 }, // Assigned To (from API: $row['assigned_to_user_name'])
            { "data": 7 }, // Task Types (from API: $task_types_display_str)
            { "data": 8, "orderable": false, "searchable": false } // Actions (from API: $actions_html)
        ],
        "order": [[ 5, 'desc' ]], // Default order by Completed On date (index 5)
        "rowCallback": function( row, data ) {
            $(row).addClass('table-success'); // Always highlight completed rows
        }
    });


    // Handle opening the Edit Task Modal (applies to both tables)
    $('#openTasksTable, #completedTasksTable').on('click', '.edit-task-btn', function() {
        var taskId = $(this).data('id');
        $.ajax({
            url: 'api/task_actions.php',
            type: 'GET',
            data: { request_type: 'get_task_details', id: taskId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let task = response.data;
                    $('#editTaskId').val(task.id);
                    $('#editTitle').val(task.title);
                    $('#editProjectId').val(task.project_id);
                    $('#editSqFt').val(task.sq_ft);
                    $('#editNotes').val(task.notes);
                    $('#editScheduled').val(task.scheduled ? task.scheduled.substring(0, 10) : '');
                    $('#editBillable').prop('checked', task.billable == 1);
                    $('#editAssignedTo').val(task.assigned_to_user_id || '');

                    $('#editTaskForm .task-type-check').prop('checked', false);
                    for (const typeKey of ['pour', 'bent_plate', 'pre_camber', 'post_camber', 'fffl', 'moisture', 'cut_fill', 'other']) {
                        if (task.hasOwnProperty(typeKey) && task[typeKey] == 1) {
                           $('#edit_task_type_' + typeKey).prop('checked', true);
                        }
                    }

                    // Handle completed_at status
                    if (task.completed_at) {
                        $('#editTaskCompleted').prop('checked', true);
                        const completionDate = new Date(task.completed_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                        $('#displayCompletionDate').val(completionDate);
                        $('#completionDateDisplay').show();
                    } else {
                        $('#editTaskCompleted').prop('checked', false);
                        $('#displayCompletionDate').val('');
                        $('#completionDateDisplay').hide();
                    }

                    editModal.show();
                } else {
                    showToast('Error', response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error (get_task_details): ", status, error, xhr.responseText);
                showToast('Error', 'An unknown server error occurred while fetching task details.', 'danger');
            }
        });
    });

    // Event listener for when the edit modal is hidden to re-enable fields (if they were disabled)
    document.getElementById('editTaskModal').addEventListener('hidden.bs.modal', function () {
        // No longer uncommenting this by default, but leaving it as a reminder if needed.
        // $('#editTaskForm input, #editTaskForm select, #editTaskForm textarea').prop('disabled', false);
    });

    // Handle saving changes from Edit Task Modal
    $('#saveTaskChangesBtn').on('click', function() {
        var form = document.getElementById('editTaskForm');
        var formData = new FormData(form);
        formData.append('request_type', 'update_task');

        if (!formData.has('billable')) {
            formData.set('billable', '0');
        }
        if (!formData.has('completed_at')) {
            formData.set('completed_at', '0');
        } else {
            formData.set('completed_at', '1');
        }

        $.ajax({
            url: 'api/task_actions.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    editModal.hide();
                    openTasksTable.ajax.reload(null, false);       // Reload both tables
                    completedTasksTable.ajax.reload(null, false);
                    showToast('Success!', response.message, 'success');
                } else {
                    showToast('Update Failed', response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error (update_task): ", status, error, xhr.responseText);
                showToast('Error', 'An unknown server error occurred. Check console for details.', 'danger');
            }
        });
    });

    // Handle Delete Task Modal opening (applies to both tables)
    $('#openTasksTable, #completedTasksTable').on('click', '.delete-task-btn', function() {
        var taskId = $(this).data('id');
        var row = $(this).parents('tr');
        var table = $(this).closest('table').attr('id') === 'openTasksTable' ? openTasksTable : completedTasksTable;
        var rowData = table.row(row).data();
        var taskName = rowData[1]; // Get task title from the retrieved row data

        $('#deleteConfirmModal .modal-body').text('Are you sure you want to delete the task: "' + taskName + '"?');
        $('#confirmDeleteBtn').data('taskId', taskId);
        deleteModal.show();
    });

    // Handle Delete Confirmation
    $('#confirmDeleteBtn').on('click', function() {
        var taskId = $(this).data('taskId');
        $.ajax({
            url: 'api/task_actions.php',
            type: 'POST',
            data: {
                request_type: 'delete_task',
                id: taskId
            },
            dataType: 'json',
            success: function(response) {
                deleteModal.hide();
                if (response.success) {
                    openTasksTable.ajax.reload(null, false);       // Reload both tables
                    completedTasksTable.ajax.reload(null, false);
                    showToast('Deleted!', response.message, 'success');
                } else {
                    showToast('Delete Failed', response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error (delete_task): ", status, error, xhr.responseText);
                showToast('Error', 'An unknown server error occurred while deleting. Check console for details.', 'danger');
            }
        });
    });
        $('#openTasksTable').on('click', '.email-task-btn', function() {
        var taskId = $(this).data('id');
        showToast('Sending Email', 'Attempting to send email notification...', 'info');

        $.ajax({
            url: 'api/task_actions.php',
            type: 'GET', // This will be a GET request to the new endpoint
            data: {
                request_type: 'send_task_email',
                id: taskId,
                email_type: 'assigned' // You can change this or make it dynamic later
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Email Sent!', response.message, 'success');
                } else {
                    showToast('Email Failed!', response.message, 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error (send_task_email): ", status, error, xhr.responseText);
                showToast('Error', 'An unknown server error occurred while sending email. Check console for details.', 'danger');
            }
        });
    });
});
