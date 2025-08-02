// File: js/photo_selector.js - REVERTED to original flow + essential bug fixes

$(document).ready(function() {
    // --- Modal and Element Selectors ---
    const taskSelectionModal = new bootstrap.Modal(document.getElementById('taskSelectionModal'));
    const photoEditModal = new bootstrap.Modal(document.getElementById('photoEditModal')); // Still a Bootstrap instance
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

        // This AJAX call is to your existing photo_handler.php, which you confirmed works for tasks
        $.ajax({
            url: 'api/photo_handler.php',
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
            },
            error: function() {
                taskSelect.html('<option value="">Error loading tasks</option>');
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

        if (currentTaskId && currentProjectId) {
            $('#selectionHeader').text(`Photo Selection for: ${taskName}`);
            $('#selectionSubHeader').text(`Project: ${projectName}`);
            mainContent.show();
            taskSelectionModal.hide();
            loadAllData(); // Load all photo data
        } else {
            alert('Please select both a Project and a Task.');
        }
    });

    // --- Event Delegation for Photo Clicks ---
    // This handler remains simple: click thumbnail, open modal
    $(document).on('click', '.photo-thumbnail', function() {
        currentEditingPhoto = $(this).data('photo-data'); // Retrieve stored photo data
        openEditModal(currentEditingPhoto);
    });

    // --- Save button in Edit Modal ---
    saveCategoryBtn.on('click', function() {
        const activityId = editActivityTypeSelect.val() || null;
        const comments = editPhotoComments.val();
        const activityName = editActivityTypeSelect.find('option:selected').text();

        // Validate 'Issue' notes
        if (activityName.toLowerCase() === 'issue' && !comments) {
            editPhotoComments.addClass('is-invalid');
            return;
        }
        editPhotoComments.removeClass('is-invalid');

        // Send file_id instead of image_path
        $.ajax({
            url: 'api/photo_selector_handler.php',
            type: 'POST',
            data: {
                action: 'update_photo_category',
                task_id: currentTaskId,
                file_id: currentEditingPhoto.file_id, // Now sending file_id
                activity_category_id: activityId,
                comments: comments
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    photoEditModal.hide();
                    loadAllData(); // Refresh the view (original behavior)
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function() {
                alert('An error occurred while saving changes.');
            }
        });
    });

    // --- Core Functions ---

    function loadAllData() {
        if (!currentProjectId || !currentTaskId) return;

        // Show a loading spinner
        assignedPhotosContainer.html('<div class="spinner-border text-primary m-3"></div><p>Loading assigned photos...</p>');
        allPhotosContainer.html('<div class="spinner-border text-primary m-3"></div><p>Loading all photos...</p>');

        $.ajax({
            url: 'api/photo_selector_handler.php', // This uses the updated handler with file_id logic
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
                    assignedPhotosContainer.html('<p class="text-danger">Failed to load photos.</p>');
                    allPhotosContainer.html('<p class="text-danger">Failed to load photos.</p>');
                }
            },
            error: function() {
                alert('An error occurred while fetching data.');
                assignedPhotosContainer.html('<p class="text-danger">Error fetching photos.</p>');
                allPhotosContainer.html('<p class="text-danger">Error fetching photos.</p>');
            }
        });
    }

    function renderPhotos(photos) {
        assignedPhotosContainer.empty();
        allPhotosContainer.empty();
        
        const activityMap = new Map(allActivityTypes.map(type => [type.id.toString(), type.name]));

        if (photos.length === 0) {
            allPhotosContainer.html('<p class="text-muted">No photos found in this folder.</p>');
            assignedPhotosContainer.html('<p class="text-muted">No photos assigned yet.</p>');
            return;
        }

        photos.forEach(photo => {
            const categoryName = photo.activity_category_id ? activityMap.get(photo.activity_category_id.toString()) : '';
            // Determine if assigned for class
            const isAssigned = photo.activity_category_id !== null && photo.activity_category_id !== undefined;

            const badgeHtml = categoryName ? `<span class="badge bg-primary category-badge">${categoryName}</span>` : '';

            // Using object_key from backend to get filename for alt text/title
            const fileName = photo.object_key ? photo.object_key.split('/').pop() : 'Image';

            const thumbnailHtml = `
                <div class="photo-thumbnail ${isAssigned ? 'is-assigned' : ''}">
                    <img src="${photo.presigned_url}" alt="${fileName}" title="${fileName}">
                    ${badgeHtml}
                </div>`;
            
            const $thumbnail = $(thumbnailHtml);
            $thumbnail.data('photo-data', photo); // Attach full data object to the element

            if (isAssigned) {
                assignedPhotosContainer.append($thumbnail);
            }
            // Always add to allPhotosContainer; clone(true) preserves data and events
            allPhotosContainer.append($thumbnail.clone(true)); 
        });

        // Add empty state messages if containers are still empty
        if (assignedPhotosContainer.is(':empty')) {
            assignedPhotosContainer.html('<p class="text-muted text-center py-4">No photos assigned yet.</p>');
        }
        if (allPhotosContainer.is(':empty')) {
            allPhotosContainer.html('<p class="text-muted text-center py-4">No photos found for this task.</p>');
        }
    }

    function populateActivityDropdown() {
        editActivityTypeSelect.empty().append('<option value="">-- Unassigned --</option>');
        allActivityTypes.forEach(type => {
            editActivityTypeSelect.append($('<option>', { value: type.id, text: type.name }));
        });
    }

    function openEditModal(photoData) {
        // Set modal title (using object_key for filename display)
        const fileName = photoData.object_key ? photoData.object_key.split('/').pop() : 'Image';
        $('#photoEditModal .modal-title').text(`Categorize Photo: ${fileName}`);
        
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
        if(isIssue) {
            editPhotoComments.attr('placeholder', 'Enter required notes for this issue.');
            editPhotoComments.addClass('is-invalid');
        } else {
            editPhotoComments.attr('placeholder', '');
            editPhotoComments.removeClass('is-invalid');
        }
    });

    // Fix for the Uncaught TypeError: photoEditModal.on is not a function
    // We need to target the modal's actual DOM element for jQuery events.
    $(photoEditModal._element).on('shown.bs.modal', function () {
        editActivityTypeSelect.trigger('change');
    });

});