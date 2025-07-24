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
                    $('#editContactPerson').val(p.contact_person);
                    $('#editStreet1').val(p.street_1);
                    $('#editStreet2').val(p.street_2);
                    $('#editCity').val(p.city);
                    $('#editState').val(p.state);
                    $('#editZip').val(p.zip);
                    $('#editStatus').val(p.status);
                    $('#editNotes').val(p.notes);
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
            }
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