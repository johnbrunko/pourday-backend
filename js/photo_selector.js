// File: js/photo_selector.js

$(document).ready(function() {
    // --- Modal and Element Selectors ---
    const taskSelectionModal = new bootstrap.Modal(document.getElementById('taskSelectionModal'));
    const photoEditModal = new bootstrap.Modal(document.getElementById('photoEditModal'));
    const projectSelect = $('#projectSelection');
    const taskSelect = $('#taskSelection');
    const startSelectionBtn = $('#startSelectionBtn');
    const mainContent = $('#mainContent');
    const assignedPhotosContainer = $('#assignedPhotos');
    const allPhotosContainer = $('#allPhotos');
    const editImagePreview = $('#editImagePreview');
    const editActivityTypeSelect = $('#editActivityType');
    const editPhotoComments = $('#editPhotoComments');
    const saveCategoryBtn = $('#saveCategoryBtn');

    // --- State Variables ---
    let currentTaskId = null;
    let currentProjectId = null;
    let allActivityTypes = [];
    let currentEditingPhoto = {}; // Store data for the photo being edited

    // --- Initial Setup ---
    taskSelectionModal.show();

    // --- Event Listeners ---

    projectSelect.on('change', function() {
        const projectId = $(this).val();
        taskSelect.html('<option value="">Loading...</option>').prop('disabled', true);
        startSelectionBtn.prop('disabled', true);

        if (!projectId) {
            taskSelect.html('').prop('disabled', true);
            return;
        }

        $.ajax({
            url: 'api/photo_handler.php', // Re-using the same handler is fine
            type: 'GET',
            data: { action: 'get_tasks_for_project', project_id: projectId },
            dataType: 'json',
            success: function(response) {
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

    taskSelect.on('change', function() {
        startSelectionBtn.prop('disabled', !$(this).val());
    });

    startSelectionBtn.on('click', function() {
        currentTaskId = taskSelect.val();
        currentProjectId = projectSelect.val();
        const taskName = taskSelect.find('option:selected').text();
        const projectName = projectSelect.find('option:selected').text();

        if (currentTaskId) {
            $('#selectionHeader').text(`Photo Selection for: ${taskName}`);
            $('#selectionSubHeader').text(`Project: ${projectName}`);
            mainContent.show();
            taskSelectionModal.hide();
            loadAllData();
        }
    });

    // --- Event Delegation for Photo Clicks ---
    $(document).on('click', '.photo-thumbnail', function() {
        currentEditingPhoto = $(this).data('photo-data');
        openEditModal(currentEditingPhoto);
    });

    // --- Save button in Edit Modal ---
    saveCategoryBtn.on('click', function() {
        const activityId = editActivityTypeSelect.val() || null; // Send null if empty
        const comments = editPhotoComments.val();
        const activityName = editActivityTypeSelect.find('option:selected').text();

        // Validate 'Issue' notes
        if (activityName.toLowerCase() === 'issue' && !comments) {
            editPhotoComments.addClass('is-invalid');
            return;
        }
        editPhotoComments.removeClass('is-invalid');

        $.ajax({
            url: 'api/photo_selector_handler.php',
            type: 'POST',
            data: {
                action: 'update_photo_category',
                task_id: currentTaskId,
                image_path: currentEditingPhoto.image_path,
                activity_category_id: activityId,
                comments: comments
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    photoEditModal.hide();
                    loadAllData(); // Refresh the view
                } else {
                    alert('Error: ' + response.message);
                }
            }
        });
    });

    // --- Core Functions ---

    function loadAllData() {
        if (!currentProjectId || !currentTaskId) return;

        // Show a loading spinner
        assignedPhotosContainer.html('<div class="spinner-border"></div>');
        allPhotosContainer.html('<div class="spinner-border"></div>');

        $.ajax({
            url: 'api/photo_selector_handler.php',
            type: 'GET',
            data: {
                action: 'get_all_data',
                project_id: currentProjectId,
                task_id: currentTaskId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    allActivityTypes = response.data.activityTypes;
                    renderPhotos(response.data.allPhotos);
                    populateActivityDropdown();
                } else {
                    alert('Failed to load data: ' + response.message);
                }
            }
        });
    }

    function renderPhotos(photos) {
        assignedPhotosContainer.empty();
        allPhotosContainer.empty();
        
        const activityMap = new Map(allActivityTypes.map(type => [type.id.toString(), type.name]));

        if (photos.length === 0) {
            allPhotosContainer.html('<p>No photos found in this folder.</p>');
            return;
        }

        photos.forEach(photo => {
            const categoryName = activityMap.get(photo.activity_category_id);
            const badgeHtml = categoryName ? `<span class="badge bg-primary category-badge">${categoryName}</span>` : '';

            const thumbnailHtml = `
                <div class="photo-thumbnail">
                    <img src="${photo.presigned_url}" alt="Photo">
                    ${badgeHtml}
                </div>`;
            
            const $thumbnail = $(thumbnailHtml);
            $thumbnail.data('photo-data', photo); // Attach full data object to the element

            if (photo.activity_category_id) {
                assignedPhotosContainer.append($thumbnail);
            }
            allPhotosContainer.append($thumbnail.clone(true)); // Use clone(true) to keep data and events
        });
    }

    function populateActivityDropdown() {
        editActivityTypeSelect.empty().append('<option value="">-- Unassigned --</option>');
        allActivityTypes.forEach(type => {
            editActivityTypeSelect.append($('<option>', { value: type.id, text: type.name }));
        });
    }

    function openEditModal(photoData) {
        editImagePreview.attr('src', photoData.presigned_url);
        editPhotoComments.val(photoData.comments || '');
        editActivityTypeSelect.val(photoData.activity_category_id || '');
        
        // Trigger change to check if notes are required for 'Issue'
        editActivityTypeSelect.trigger('change');
        
        photoEditModal.show();
    }
    
    // Add listener to check for 'Issue' notes requirement
    editActivityTypeSelect.on('change', function() {
        const selectedText = $(this).find('option:selected').text();
        const isIssue = selectedText.toLowerCase() === 'issue';
        editPhotoComments.prop('required', isIssue);
        if(!isIssue) editPhotoComments.removeClass('is-invalid');
    });
});
