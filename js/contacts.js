// js/contacts.js

$(document).ready(function() {
    
    // --- Initialize modal instances once on page load ---
    const editModalEl = document.getElementById('editContactModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;

    const deleteModalEl = document.getElementById('deleteConfirmModal');
    const deleteModal = deleteModalEl ? new bootstrap.Modal(deleteModalEl) : null;
    
    // Helper function for Toast Notifications (can be in main.js if shared)
    function showToast(title, message, type = 'success') {
        const toastEl = document.getElementById('appToast');
        if (!toastEl) {
            console.error('Toast element not found on page.');
            alert(message);
            return;
        }
        const toast = new bootstrap.Toast(toastEl);
        const toastHeader = toastEl.querySelector('.toast-header');
        const toastTitle = document.getElementById('toastTitle');
        const toastBody = document.getElementById('toastBody');

        if (type === 'success') {
            toastHeader.classList.remove('bg-danger');
            toastHeader.classList.add('bg-success', 'text-white');
            toastTitle.innerText = title || 'Success';
        } else {
            toastHeader.classList.remove('bg-success');
            toastHeader.classList.add('bg-danger', 'text-white');
            toastTitle.innerText = title || 'Error';
        }
        toastBody.innerText = message;
        toast.show();
    }

    // Initialize DataTables
    var contactsTable = $('#contactsTable').DataTable({
        "processing": true,
        "serverSide": false,
        "ajax": {
            "url": "api/contact_actions.php",
            "type": "GET",
            "data": { "request_type": "get_all_contacts" },
            "dataSrc": "data",
            "error": function(xhr, error, thrown) {
                console.error("DataTables AJAX Error:", thrown, xhr.responseText);
                alert('Error loading contact data.');
            }
        },
        "columns": [
            { "data": 0 }, // ID
            { "data": 1 }, // Name
            { "data": 2 }, // Customer
            { "data": 3 }, // Email
            { "data": 4 }, // Phone
            { "data": 5, "orderable": false, "searchable": false } // Actions
        ],
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"tr>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
    });

    // --- Add New Contact Logic ---
    $('#saveNewContactBtn').on('click', function() {
        var formData = $('#addContactForm').serialize();
        formData += '&request_type=add_contact';
        $.ajax({
            url: 'api/contact_actions.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showToast('Success', response.message || 'Contact added!');
                    contactsTable.ajax.reload(null, false);
                    $('#addContactForm')[0].reset();
                    $('#addContactCollapse').collapse('hide');
                } else {
                    showToast('Add Failed', response.message || 'An error occurred.', 'danger');
                }
            },
            error: function() { showToast('Error', 'AJAX error adding contact.', 'danger'); }
        });
    });

    // --- Edit Contact Logic ---
    $('#contactsTable').on('click', '.edit-contact-btn', function() {
        var contactId = $(this).data('id');
        $.ajax({
            url: 'api/contact_actions.php',
            type: 'GET',
            data: { request_type: 'get_contact_details', id: contactId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    var contact = response.data;
                    $('#editContactId').val(contact.id);
                    $('#editFirstName').val(contact.first_name);
                    $('#editLastName').val(contact.last_name);
                    $('#editEmail').val(contact.email);
                    $('#editPhone').val(contact.phone);
                    $('#editTitle').val(contact.title);
                    $('#editCustomerId').val(contact.customer_id);
                    $('#editNotes').val(contact.notes);
                    
                    if (editModal) editModal.show();
                } else {
                    showToast('Error', response.message || 'Could not fetch details.', 'danger');
                }
            },
            error: function() { showToast('Error', 'AJAX error fetching details.', 'danger'); }
        });
    });
    
    // Save changes from edit modal
    $('#saveContactChangesBtn').on('click', function() {
        var formData = $('#editContactForm').serialize();
        formData += '&request_type=update_contact';
        $.ajax({
            url: 'api/contact_actions.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (editModal) editModal.hide();
                    showToast('Success', response.message || 'Contact updated!');
                    contactsTable.ajax.reload(null, false);
                } else {
                    showToast('Update Failed', response.message || 'An error occurred.', 'danger');
                }
            },
            error: function() { showToast('Error', 'AJAX error saving changes.', 'danger'); }
        });
    });

    // --- Delete Contact Logic ---
    $('#contactsTable').on('click', '.delete-contact-btn', function() {
        var contactId = $(this).data('id');
        var contactName = $(this).closest('tr').find('td:eq(1)').text();
        
        $('#deleteConfirmModal .modal-body').text('Are you sure you want to delete "' + contactName + '"? This action cannot be undone.');
        $('#confirmDeleteBtn').data('contactId', contactId);
        
        if (deleteModal) {
            deleteModal.show();
        }
    });

    // Final confirmation for delete
    $('#confirmDeleteBtn').on('click', function() {
        var contactId = $(this).data('contactId');
        
        $.ajax({
            url: 'api/contact_actions.php',
            type: 'POST',
            data: { request_type: 'delete_contact', id: contactId },
            dataType: 'json',
            success: function(response) {
                if (deleteModal) deleteModal.hide();
                if (response.success) {
                    showToast('Success', response.message || 'Contact deleted!');
                    contactsTable.ajax.reload(null, false);
                } else {
                    showToast('Delete Failed', response.message || 'An error occurred.', 'danger');
                }
            },
            error: function() {
                if (deleteModal) deleteModal.hide();
                showToast('Error', 'AJAX error during deletion.', 'danger');
            }
        });
    });
});