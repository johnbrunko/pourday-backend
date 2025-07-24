// js/company_admin.js

$(document).ready(function() {

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
        } else { // 'danger'
            toastHeader.classList.remove('bg-success');
            toastHeader.classList.add('bg-danger', 'text-white');
            toastTitle.innerText = title || 'Error';
        }
        toastBody.innerText = message;
        toast.show();
    }

    // Initialize DataTables
    var usersTable = $('#usersTable').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: 'api/user_actions.php',
            type: 'GET',
            data: { request_type: 'get_all_users' },
            dataSrc: 'data',
            error: function(xhr, error, thrown) {
                console.error("DataTables AJAX Error:", thrown, xhr.responseText);
                showToast('Loading Error', 'Error loading user data. Please check the console for details.', 'danger');
            }
        },
        columns: [
            { data: 0 }, { data: 1 }, { data: 2 }, { data: 3 }, { data: 4 }, { data: 5 },
            { data: 6, orderable: false, searchable: false }
        ],
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"tr>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
    });

    // Event listener for Edit button
    $('#usersTable').on('click', '.edit-user-btn', function() {
        // (This logic remains the same)
        var userId = $(this).data('id');
        $('#editUserForm')[0].reset();
        $('.is-invalid').removeClass('is-invalid');
        $('#editUserId').val(userId);
        $('#editUserModalLabel').text('Edit User (ID: ' + userId + ')');

        $.ajax({
            url: 'api/user_actions.php',
            type: 'GET',
            dataType: 'json',
            data: { request_type: 'get_user_details', id: userId },
            success: function(response) {
                if (response.success && response.data) {
                    var userData = response.data;
                    $('#editFirstName').val(userData.first_name);
                    $('#editLastName').val(userData.last_name);
                    $('#editUsername').val(userData.username);
                    $('#editEmail').val(userData.email);
                    $('#editPhoneNumber').val(userData.phone_number);
                    $('#editRole').val(userData.role_id);
                    $('#editIsActive').prop('checked', userData.is_active == 1);
                    var editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
                    editUserModal.show();
                } else {
                    showToast('Fetch Error', response.message || 'Unknown error.', 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error fetching details:", error, xhr.responseText);
                showToast('AJAX Error', 'An error occurred while fetching data.', 'danger');
            }
        });
    });

    // Event listener for Save Changes button
    $('#saveUserChangesBtn').on('click', function() {
        // (This logic remains the same)
        var formData = $('#editUserForm').serialize();
        formData += '&request_type=update_user';
        $.ajax({
            url: 'api/user_actions.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#editUserModal').modal('hide');
                    showToast('Success', response.message || 'User updated!', 'success');
                    usersTable.ajax.reload(null, false);
                } else {
                    showToast('Update Failed', response.message || 'Unknown error.', 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error updating user:", error, xhr.responseText);
                showToast('AJAX Error', 'A server error occurred.', 'danger');
            }
        });
    });

    // --- MODIFIED SECTION: Event listener for Delete button ---
    $('#usersTable').on('click', '.delete-user-btn', function() {
        var userId = $(this).data('id');
        var userName = $(this).closest('tr').find('td:eq(1)').text();
        
        // Update the modal's body with specific user info
        $('#deleteConfirmModal .modal-body').text('Are you sure you want to delete user "' + userName + '" (ID: ' + userId + ')? This action cannot be undone.');

        // Store the user ID on the confirmation button itself
        $('#confirmDeleteBtn').data('userId', userId);
        
        // Show the confirmation modal
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        deleteModal.show();
    });

    // --- NEW: Event listener for the final confirmation delete button inside the modal ---
    $('#confirmDeleteBtn').on('click', function() {
        var userId = $(this).data('userId');
        
        $.ajax({
            url: 'api/user_actions.php',
            type: 'POST',
            data: {
                request_type: 'delete_user',
                id: userId
            },
            dataType: 'json',
            success: function(response) {
                // Hide the modal first
                var deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
                deleteModal.hide();

                if (response.success) {
                    showToast('Success', response.message || 'User deleted!', 'success');
                    usersTable.ajax.reload(null, false);
                } else {
                    showToast('Delete Failed', response.message || 'Unknown error.', 'danger');
                }
            },
            error: function(xhr, status, error) {
                var deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal'));
                deleteModal.hide();
                console.error("AJAX Error deleting user:", error, xhr.responseText);
                showToast('AJAX Error', 'A server error occurred during deletion.', 'danger');
            }
        });
    });
});