$(document).ready(function() {

    // --- Initialize modal instances once ---
    const editModal = new bootstrap.Modal(document.getElementById('editProjectModal'));
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));

    function showToast(title, message, type = 'success') {
        const toastEl = document.getElementById('appToast');
        const toastTitle = document.getElementById('toastTitle');
        const toastBody = document.getElementById('toastBody');
        const toastHeader = toastEl.querySelector('.toast-header');

        toastTitle.textContent = title;
        toastBody.textContent = message;

        if (type === 'success') {
            toastHeader.classList.remove('bg-danger');
            toastHeader.classList.add('bg-success', 'text-white');
        } else {
            toastHeader.classList.remove('bg-success');
            toastHeader.classList.add('bg-danger', 'text-white');
        }

        new bootstrap.Toast(toastEl).show();
    }

    var projectsTable = $('#projectsTable').DataTable({
        "processing": true,
        "ajax": {
            "url": "api/project_actions.php",
            "type": "GET",
            "data": { "request_type": "get_all_projects" },
            "dataSrc": "data",
            "error": function(xhr) {
                console.error("DataTables Error:", xhr.responseText);
                alert('Error loading project data.');
            }
        },
        "columns": [
            { "data": 0 }, { "data": 1 }, { "data": 2 }, { "data": 3 }, { "data": 4 },
            { "data": 5, "orderable": false, "searchable": false }
        ]
    });

    // *** NEW FUNCTION: To fetch and populate contact dropdowns ***
    function populateContactDropdowns(customerId, formPrefix, selectedContacts = {}) {
        const dropdowns = [
            $(`#${formPrefix}_contact_id_1`),
            $(`#${formPrefix}_contact_id_2`),
            $(`#${formPrefix}_contact_id_3`)
        ];

        // Reset and disable dropdowns first
        dropdowns.forEach(dd => {
            dd.html('<option value="">-- Loading... --</option>').prop('disabled', true);
        });

        if (!customerId) {
            dropdowns.forEach(dd => {
                dd.html('<option value="">-- Select a customer first --</option>');
            });
            return;
        }

        $.ajax({
            url: 'api/project_actions.php',
            type: 'GET',
            data: { request_type: 'get_contacts_for_customer', customer_id: customerId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    dropdowns.forEach((dd, index) => {
                        dd.html('<option value="">-- None --</option>'); // Add a "None" option
                        response.data.forEach(contact => {
                            const fullName = `${contact.first_name} ${contact.last_name}`.trim();
                            dd.append($('<option>', {
                                value: contact.id,
                                text: fullName
                            }));
                        });
                        dd.prop('disabled', false);

                        // Reselect the previously saved contact
                        const selectedId = selectedContacts[`contact_id_${index + 1}`];
                        if (selectedId) {
                            dd.val(selectedId);
                        }
                    });
                } else {
                    dropdowns.forEach(dd => {
                        dd.html('<option value="">-- No contacts found --</option>');
                    });
                }
            }
        });
    }

    // *** NEW: Event listener for the customer dropdown in the ADD form ***
    $('#add_customer_id').on('change', function() {
        const customerId = $(this).val();
        populateContactDropdowns(customerId, 'add');
    });

    // *** NEW: Event listener for the customer dropdown in the EDIT form ***
    $('#editCustomerId').on('change', function() {
        const customerId = $(this).val();
        populateContactDropdowns(customerId, 'edit');
    });

    // --- Edit Project ---
    $('#projectsTable').on('click', '.edit-project-btn', function() {
        var projectId = $(this).data('id');
        $.ajax({
            url: 'api/project_actions.php',
            type: 'GET',
            data: { request_type: 'get_project_details', id: projectId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    let p = response.data;
                    $('#editProjectId').val(p.id);
                    $('#editJobName').val(p.job_name);
                    $('#editJobNumber').val(p.job_number);
                    $('#editCustomerId').val(p.customer_id);
                    $('#editStreet1').val(p.street_1);
                    $('#editStreet2').val(p.street_2);
                    $('#editCity').val(p.city);
                    $('#editState').val(p.state);
                    $('#editZip').val(p.zip);
                    $('#editStatus').val(p.status);
                    $('#editNotes').val(p.notes);

                    // *** MODIFIED: Trigger the population of contacts for the selected customer ***
                    populateContactDropdowns(p.customer_id, 'edit', {
                        contact_id_1: p.contact_id_1,
                        contact_id_2: p.contact_id_2,
                        contact_id_3: p.contact_id_3
                    });

                    editModal.show();
                } else {
                    showToast('Error', response.message, 'danger');
                }
            }
        });
    });

    // --- Save Project Changes ---
    $('#saveProjectChangesBtn').on('click', function() {
        var formData = $('#editProjectForm').serialize();
        formData += '&request_type=update_project';

        // --- ADDED FOR DEBUGGING ---
        console.log("FormData being sent:", formData);
        // --- END DEBUGGING ---

        $.ajax({
            url: 'api/project_actions.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    editModal.hide();
                    showToast('Success', response.message);
                    projectsTable.ajax.reload(null, false);
                } else {
                    showToast('Update Failed', response.message, 'danger');
                }
            },
            // --- ADDED FOR DEBUGGING ---
            error: function(xhr, status, error) {
                console.error("AJAX Error Status:", status);
                console.error("AJAX Error:", error);
                console.error("Response Text:", xhr.responseText);
                showToast('AJAX Error', 'Check browser console for details. Server Response: ' + xhr.responseText.substring(0, 100) + '...', 'danger'); // Show first 100 chars of response
            }
            // --- END DEBUGGING ---
        });
    });

    // --- Delete Project ---
    $('#projectsTable').on('click', '.delete-project-btn', function() {
        var projectId = $(this).data('id');
        var projectName = $(this).closest('tr').find('td:eq(1)').text();

        $('#deleteConfirmModal .modal-body').text('Are you sure you want to delete project "' + projectName + '"?');
        $('#confirmDeleteBtn').data('projectId', projectId);
        deleteModal.show();
    });

    $('#confirmDeleteBtn').on('click', function() {
        var projectId = $(this).data('projectId');
        $.ajax({
            url: 'api/project_actions.php',
            type: 'POST',
            data: { request_type: 'delete_project', id: projectId },
            dataType: 'json',
            success: function(response) {
                deleteModal.hide();
                if (response.success) {
                    showToast('Success', response.message);
                    projectsTable.ajax.reload(null, false);
                } else {
                    showToast('Delete Failed', response.message, 'danger');
                }
            }
        });
    });
});