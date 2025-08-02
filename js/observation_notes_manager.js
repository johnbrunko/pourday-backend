$(document).ready(function() {
    // --- Element Selectors ---
    const tableBody = $('#templates-table-body');
    const modal = new bootstrap.Modal(document.getElementById('templateModal'));
    const modalForm = $('#templateForm');
    const modalTitle = $('#templateModalLabel');
    const addNewBtn = $('#add-new-btn');

    /**
     * Fetches all templates from the API and populates the table.
     */
    function loadTemplates() {
        tableBody.html('<tr><td colspan="4" class="text-center">Loading...</td></tr>');

        $.ajax({
            url: 'api/observation_notes_manager_actions.php?action=get_templates',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                tableBody.empty();
                if (response.success && response.data.length > 0) {
                    response.data.forEach(template => {
                        const statusBadge = template.is_active == 1 
                            ? '<span class="badge bg-success">Active</span>' 
                            : '<span class="badge bg-secondary">Inactive</span>';
                        
                        const toggleBtnText = template.is_active == 1 ? 'Deactivate' : 'Activate';
                        const toggleBtnClass = template.is_active == 1 ? 'btn-warning' : 'btn-success';

                        const row = `
                            <tr>
                                <td>${template.category}</td>
                                <td>${$('<p>').text(template.text).html()}</td>
                                <td>${statusBadge}</td>
                                <td class="text-end">
                                    <button class="btn btn-primary btn-sm edit-btn" data-id="${template.id}" title="Edit">
                                        <i class="modus-icons notranslate">edit</i>
                                    </button>
                                    <button class="btn ${toggleBtnClass} btn-sm toggle-status-btn" data-id="${template.id}" title="${toggleBtnText}">
                                        <i class="modus-icons notranslate">${template.is_active == 1 ? 'visibility_off' : 'visibility'}</i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        tableBody.append(row);
                    });
                } else {
                    tableBody.html('<tr><td colspan="4" class="text-center">No templates found. Click "Add New Template" to get started.</td></tr>');
                }
            },
            error: function() {
                tableBody.html('<tr><td colspan="4" class="text-center text-danger">Error loading templates.</td></tr>');
            }
        });
    }

    /**
     * Resets and prepares the modal for a new template entry.
     */
    addNewBtn.on('click', function() {
        modalTitle.text('Add New Template');
        modalForm[0].reset();
        $('#templateId').val('');
        modal.show();
    });

    /**
     * Handles the form submission for both adding and editing templates.
     */
    modalForm.on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        const action = $('#templateId').val() ? 'update_template' : 'add_template';
        
        $.ajax({
            url: `api/observation_notes_manager_actions.php?action=${action}`,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    modal.hide();
                    loadTemplates(); // Refresh the table
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An unexpected error occurred.');
            }
        });
    });

    /**
     * Handles click on 'Edit' button. Fetches template data and populates the modal.
     */
    tableBody.on('click', '.edit-btn', function() {
        const templateId = $(this).data('id');
        
        $.ajax({
            url: `api/observation_notes_manager_actions.php?action=get_template&id=${templateId}`,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const template = response.data;
                    modalTitle.text('Edit Template');
                    $('#templateId').val(template.id);
                    $('#templateCategory').val(template.category);
                    $('#templateText').val(template.text);
                    modal.show();
                } else {
                    alert('Error: ' + response.message);
                }
            }
        });
    });

    /**
     * Handles click on 'Toggle Status' button (Activate/Deactivate).
     */
    tableBody.on('click', '.toggle-status-btn', function() {
        const templateId = $(this).data('id');
        
        if (confirm('Are you sure you want to change the status of this template?')) {
            $.ajax({
                url: 'api/observation_notes_manager_actions.php?action=toggle_status',
                type: 'POST',
                data: { id: templateId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        loadTemplates(); // Refresh the table
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('An unexpected error occurred.');
                }
            });
        }
    });

    // --- Initial Load ---
    loadTemplates();
});
