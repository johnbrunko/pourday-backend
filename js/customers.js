// js/customers.js

$(document).ready(function() {
    
    // --- Initialize modal instances once on page load ---
    const editModalEl = document.getElementById('editCustomerModal');
    const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;

    const deleteModalEl = document.getElementById('deleteConfirmModal');
    const deleteModal = deleteModalEl ? new bootstrap.Modal(deleteModalEl) : null;
    
    // Helper function for Toast Notifications
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
    var customersTable = $('#customersTable').DataTable({
        "processing": true,
        "serverSide": false, // This is set to false, meaning you're loading all data at once or handling server-side manually.
        "ajax": {
            "url": "api/customer_actions.php",
            "type": "GET",
            "data": { "request_type": "get_all_customers" },
            "dataSrc": "data",
            "error": function(xhr, error, thrown) {
                console.error("DataTables AJAX Error:", thrown, xhr.responseText);
                showToast('Error', 'Error loading customer data.', 'danger');
            }
        },
        "columns": [
            { "data": 0 }, // ID
            { "data": 1 }, // Customer Name
            // Removed { "data": 2 } (Contact Person)
            // Removed { "data": 3 } (Contact Phone)
            { "data": 2 }, // City (previously data:4)
            { "data": 3 }, // Status (previously data:5)
            { "data": 4, "orderable": false, "searchable": false } // Actions (previously data:6)
        ],
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"tr>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
    });

    // --- Edit Customer Logic ---
    $('#customersTable').on('click', '.edit-customer-btn', function() {
        var customerId = $(this).data('id');
        $.ajax({
            url: 'api/customer_actions.php',
            type: 'GET',
            data: { request_type: 'get_customer_details', id: customerId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    var customer = response.data;
                    $('#editCustomerId').val(customer.id);
                    $('#editCustomerName').val(customer.customer_name);
                    // Removed: $('#editContactPerson').val(customer.contact_person);
                    // Removed: $('#editContactPhone').val(customer.contact_phone);
                    // Removed: $('#editContactEmail').val(customer.contact_email);
                    $('#editAddressLine1').val(customer.address_line_1);
                    $('#editAddressLine2').val(customer.address_line_2);
                    $('#editCity').val(customer.city);
                    $('#editStateProvince').val(customer.state_province);
                    $('#editPostalCode').val(customer.postal_code);
                    $('#editNotes').val(customer.notes);
                    $('#editStatus').val(customer.status);
                    
                    if (editModal) editModal.show();
                } else {
                    showToast('Error', response.message || 'Could not fetch details.', 'danger');
                }
            },
            error: function() { showToast('Error', 'AJAX error fetching details.', 'danger'); }
        });
    });
    
    // Save changes from edit modal
    $('#saveCustomerChangesBtn').on('click', function() {
        var formData = $('#editCustomerForm').serialize();
        formData += '&request_type=update_customer';
        $.ajax({
            url: 'api/customer_actions.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (editModal) editModal.hide();
                    showToast('Success', response.message || 'Customer updated!');
                    customersTable.ajax.reload(null, false);
                } else {
                    showToast('Update Failed', response.message || 'An error occurred.', 'danger');
                }
            },
            error: function() { showToast('Error', 'AJAX error saving changes.', 'danger'); }
        });
    });

    // --- Delete Customer Logic ---
    $('#customersTable').on('click', '.delete-customer-btn', function() {
        console.log('Delete button clicked in table.'); // DEBUG
        var customerId = $(this).data('id');
        // Adjust index for customerName since columns shifted
        var customerName = $(this).closest('tr').find('td:eq(1)').text(); 
        
        $('#deleteConfirmModal .modal-body').text('Are you sure you want to delete "' + customerName + '"? This action cannot be undone.');
        $('#confirmDeleteBtn').data('customerId', customerId);
        
        if (deleteModal) {
            console.log('Showing delete confirmation modal for customer ID:', customerId); // DEBUG
            deleteModal.show();
        } else {
            console.error('Delete modal object not found!'); // DEBUG
        }
    });

    // Final confirmation for delete
    $('#confirmDeleteBtn').on('click', function() {
        var customerId = $(this).data('customerId');
        console.log('Final delete button clicked in modal. Attempting to delete customer ID:', customerId); // DEBUG
        
        $.ajax({
            url: 'api/customer_actions.php',
            type: 'POST',
            data: { request_type: 'delete_customer', id: customerId },
            dataType: 'json',
            success: function(response) {
                console.log('AJAX success:', response); // DEBUG
                if (deleteModal) deleteModal.hide();
                if (response.success) {
                    showToast('Success', response.message || 'Customer deleted!');
                    customersTable.ajax.reload(null, false);
                } else {
                    showToast('Delete Failed', response.message || 'An error occurred.', 'danger');
                }
            },
            error: function(xhr) {
                console.error('AJAX error:', xhr.responseText); // DEBUG
                if (deleteModal) deleteModal.hide();
                showToast('Error', 'AJAX error during deletion.', 'danger');
            }
        });
    });
});