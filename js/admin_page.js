$(document).ready(function() {

    // --- Initialize modal instances once on page load ---
    const editCompanyModal = new bootstrap.Modal(document.getElementById('editCompanyModal'));
    const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));

    let companiesTable;
    let usersTable;

    // --- Helper function for Toast Notifications ---
    function showToast(title, message, type = 'success') {
        const toastEl = document.getElementById('appToast');
        if (!toastEl) {
            console.error('Toast element not found on page.');
            alert(message); // Fallback
            return;
        }
        const toast = bootstrap.Toast.getOrCreateInstance(toastEl);
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

    // --- Initialize Companies DataTable ---
    companiesTable = $('#companiesTable').DataTable({
        "processing": true,
        "serverSide": false, // Data is fetched all at once
        "ajax": {
            "url": "api/admin_actions.php",
            "type": "GET",
            "data": { "request_type": "get_companies" },
            "dataSrc": "data",
            "error": function(xhr, error, thrown) {
                console.error("Companies DataTable AJAX Error:", thrown, xhr.responseText);
                showToast('Error', 'Failed to load company data.', 'danger');
            }
        },
        "columns": [
            { "data": 0 }, { "data": 1 }, { "data": 2 }, 
            { "data": 3 }, { "data": 4 },
            { "data": 5, "orderable": false, "searchable": false }
        ],
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"tr>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
    });

    // --- Initialize Users DataTable ---
    // We initialize it but might defer loading until the tab is shown for performance
    usersTable = $('#usersTable').DataTable({
        "processing": true,
        "serverSide": false,
        "ajax": {
            "url": "api/admin_actions.php",
            "type": "GET",
            "data": { "request_type": "get_users" },
            "dataSrc": "data",
            "error": function(xhr, error, thrown) {
                console.error("Users DataTable AJAX Error:", thrown, xhr.responseText);
                showToast('Error', 'Failed to load user data.', 'danger');
            }
        },
        "columns": [
            { "data": 0 }, { "data": 1 }, { "data": 2 }, { "data": 3 },
            { "data": 4 }, { "data": 5 }, { "data": 6 },
            { "data": 7, "orderable": false, "searchable": false }
        ],
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"tr>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
    });
    
    // Redraw tables when tabs are shown to fix header alignment issues
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
    });

    // --- COMPANY ACTIONS ---

    // 1. Edit Company: Fetch details and show modal
    $('#companiesTable').on('click', '.edit-company-btn', function() {
        var companyId = $(this).data('id');
        $.ajax({
            url: 'api/admin_actions.php',
            type: 'GET',
            data: { request_type: 'get_company_details', id: companyId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    var company = response.data;
                    $('#editCompanyId').val(company.id);
                    $('#edit_company_name').val(company.company_name);
                    $('#edit_contact_person_name').val(company.contact_person_name);
                    $('#edit_contact_email').val(company.contact_email);
                    $('#edit_contact_phone').val(company.contact_phone);
                    $('#edit_address').val(company.address);
                    $('#edit_city').val(company.city);
                    $('#edit_state').val(company.state);
                    $('#edit_zip_code').val(company.zip_code);
                    $('#edit_company_is_active').prop('checked', company.is_active == 1);
                    editCompanyModal.show();
                } else {
                    showToast('Error', response.message || 'Could not fetch company details.', 'danger');
                }
            },
            error: function() { showToast('Error', 'AJAX error fetching details.', 'danger'); }
        });
    });

    // 2. Save Company Changes
    $('#saveCompanyChangesBtn').on('click', function() {
        var formData = $('#editCompanyForm').serialize();
        formData += '&request_type=update_company';
        
        $.ajax({
            url: 'api/admin_actions.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    editCompanyModal.hide();
                    showToast('Success', response.message || 'Company updated!');
                    companiesTable.ajax.reload(null, false); // a full reload to get fresh data
                } else {
                    showToast('Update Failed', response.message || 'An error occurred.', 'danger');
                }
            },
            error: function() { showToast('Error', 'AJAX error saving changes.', 'danger'); }
        });
    });

    // 3. Delete Company: Show confirmation
    $('#companiesTable').on('click', '.delete-company-btn', function() {
        var companyId = $(this).data('id');
        var companyName = $(this).closest('tr').find('td:eq(1)').text();
        
        $('#deleteConfirmModal .modal-body').text('Are you sure you want to delete the company "' + companyName + '"? This may affect associated users.');
        
        // Set data attributes for the final delete button
        $('#confirmDeleteBtn').data('id', companyId);
        $('#confirmDeleteBtn').data('type', 'company');
        
        deleteModal.show();
    });


    // --- USER ACTIONS ---
    
    // Pre-fetch companies and roles for the user edit modal
    function populateUserModalDropdowns() {
        // Fetch companies
        $.ajax({
            url: 'api/admin_actions.php',
            type: 'GET',
            data: { request_type: 'get_all_companies_list' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var options = '<option value="">(No Company)</option>';
                    response.data.forEach(function(company) {
                        options += `<option value="${company.id}">${company.company_name}</option>`;
                    });
                    $('#edit_company_id').html(options);
                }
            }
        });

        // Fetch roles
        $.ajax({
            url: 'api/admin_actions.php',
            type: 'GET',
            data: { request_type: 'get_all_roles' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var options = '';
                    response.data.forEach(function(role) {
                        options += `<option value="${role.id}">${role.role_name}</option>`;
                    });
                    $('#edit_role_id').html(options);
                }
            }
        });
    }
    populateUserModalDropdowns(); // Call on page load

    // 1. Edit User: Fetch details and show modal
    $('#usersTable').on('click', '.edit-user-btn', function() {
        var userId = $(this).data('id');
        $.ajax({
            url: 'api/admin_actions.php',
            type: 'GET',
            data: { request_type: 'get_user_details', id: userId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    var user = response.data;
                    $('#editUserId').val(user.id);
                    $('#edit_username').val(user.username);
                    $('#edit_email').val(user.email);
                    $('#edit_first_name').val(user.first_name);
                    $('#edit_last_name').val(user.last_name);
                    $('#edit_company_id').val(user.company_id);
                    $('#edit_role_id').val(user.role_id);
                    $('#edit_user_is_active').prop('checked', user.is_active == 1);
                    $('#edit_password').val(''); // Clear password field
                    editUserModal.show();
                } else {
                    showToast('Error', response.message || 'Could not fetch user details.', 'danger');
                }
            },
            error: function() { showToast('Error', 'AJAX error fetching details.', 'danger'); }
        });
    });

    // 2. Save User Changes
    $('#saveUserChangesBtn').on('click', function() {
        var formData = $('#editUserForm').serialize();
        formData += '&request_type=update_user';
        
        $.ajax({
            url: 'api/admin_actions.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    editUserModal.hide();
                    showToast('Success', response.message || 'User updated!');
                    usersTable.ajax.reload(null, false);
                } else {
                    showToast('Update Failed', response.message || 'An error occurred.', 'danger');
                }
            },
            error: function() { showToast('Error', 'AJAX error saving changes.', 'danger'); }
        });
    });

    // 3. Delete User: Show confirmation
    $('#usersTable').on('click', '.delete-user-btn', function() {
        var userId = $(this).data('id');
        var userName = $(this).closest('tr').find('td:eq(1)').text();
        
        $('#deleteConfirmModal .modal-body').text('Are you sure you want to delete the user "' + userName + '"? This action cannot be undone.');
        
        $('#confirmDeleteBtn').data('id', userId);
        $('#confirmDeleteBtn').data('type', 'user');
        
        deleteModal.show();
    });


    // --- UNIVERSAL DELETE ACTION ---
    $('#confirmDeleteBtn').on('click', function() {
        var id = $(this).data('id');
        var type = $(this).data('type');
        var request_type = (type === 'company') ? 'delete_company' : 'delete_user';
        var tableToReload = (type === 'company') ? companiesTable : usersTable;

        $.ajax({
            url: 'api/admin_actions.php',
            type: 'POST',
            data: { request_type: request_type, id: id },
            dataType: 'json',
            success: function(response) {
                deleteModal.hide();
                if (response.success) {
                    showToast('Success', response.message);
                    tableToReload.ajax.reload(null, false);
                } else {
                    showToast('Delete Failed', response.message, 'danger');
                }
            },
            error: function() {
                deleteModal.hide();
                showToast('Error', 'AJAX error during deletion.', 'danger');
            }
        });
    });
});