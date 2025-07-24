// File: js/image_tracker.js

$(document).ready(function() {
    // --- Modal and Element Selectors ---
    const taskSelectionModal = new bootstrap.Modal(document.getElementById('taskSelectionModal'));
    const photoUploadModal = new bootstrap.Modal(document.getElementById('photoUploadModal'));
    const projectSelect = $('#projectSelection');
    const taskSelect = $('#taskSelection');
    const startTrackingBtn = $('#startTrackingBtn');
    const mainContent = $('#mainContent');
    const photoInput = $('#photoInput');
    const cameraIconContainer = $('#cameraIconContainer');
    const imagePreview = $('#imagePreview');
    const activityTypeSelect = $('#activityType');
    const photoComments = $('#photoComments');
    const savePhotoBtn = $('#savePhotoBtn');
    const photoChecklist = $('#photoChecklist');
    const photoGallery = $('#photoGallery');

    // --- State Variables ---
    let currentTaskId = null;
    let currentTaskName = '';
    let currentProjectName = '';
    let allActivityTypes = [];
    let selectedFile = null;

    // --- Initial Setup ---
    taskSelectionModal.show();

    // --- Event Listeners ---

    projectSelect.on('change', function() {
        const projectId = $(this).val();
        taskSelect.html('<option value="">Loading...</option>').prop('disabled', true);
        startTrackingBtn.prop('disabled', true);

        if (!projectId) {
            taskSelect.html('').prop('disabled', true);
            return;
        }

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
            }
        });
    });

    taskSelect.on('change', function() {
        startTrackingBtn.prop('disabled', !$(this).val());
    });

    startTrackingBtn.on('click', function() {
        currentTaskId = taskSelect.val();
        currentTaskName = taskSelect.find('option:selected').text();
        currentProjectName = projectSelect.find('option:selected').text();

        if (currentTaskId) {
            $('#trackingHeader').text(`Image Tracking for: ${currentTaskName}`);
            $('#trackingSubHeader').text(`Project: ${currentProjectName}`);
            mainContent.show();
            taskSelectionModal.hide();
            loadPhotoData();
        }
    });
    
    cameraIconContainer.on('click', function() {
        photoInput.click();
    });

    // --- MODIFIED: Handle file selection with HEIC conversion ---
    photoInput.on('change', async function(event) {
        let file = event.target.files[0];
        if (!file) return;

        // Show a loading indicator
        cameraIconContainer.html('<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>');

        const fileName = file.name.toLowerCase();
        if (fileName.endsWith('.heic') || fileName.endsWith('.heif')) {
            // --- FIX: Check if the conversion library is loaded ---
            if (typeof heic2any === 'undefined') {
                alert('Error: The HEIC conversion library failed to load. Please check your internet connection or contact support.');
                cameraIconContainer.html('<i id="cameraIcon" class="modus-icons notranslate">camera</i>'); // Restore icon
                return;
            }
            // --- END FIX ---

            try {
                const conversionResult = await heic2any({
                    blob: file,
                    toType: "image/jpeg",
                    quality: 0.8,
                });
                
                // Create a new File object with the correct name and type
                const newFileName = file.name.replace(/\.(heic|heif)$/i, '.jpeg');
                file = new File([conversionResult], newFileName, { type: 'image/jpeg' });

            } catch (e) {
                alert('Error converting HEIC image. Please try a different file. ' + e.message);
                cameraIconContainer.html('<i id="cameraIcon" class="modus-icons notranslate">camera</i>'); // Restore icon
                return;
            }
        }
        
        selectedFile = file;
        const reader = new FileReader();
        reader.onload = function(e) {
            imagePreview.attr('src', e.target.result);
            loadActivityTypesAndShowModal();
        };
        reader.readAsDataURL(file);
        
        // Restore camera icon after processing
        cameraIconContainer.html('<i id="cameraIcon" class="modus-icons notranslate">camera</i>');
    });

    activityTypeSelect.on('change', function() {
        const selectedText = $(this).find('option:selected').text();
        const isIssue = selectedText.toLowerCase() === 'issue';
        photoComments.prop('required', isIssue);
        photoComments.toggleClass('is-invalid', false);
    });

    savePhotoBtn.on('click', function() {
        const activityId = activityTypeSelect.val();
        const comments = photoComments.val();
        const selectedText = activityTypeSelect.find('option:selected').text();
        const isIssue = selectedText.toLowerCase() === 'issue';

        if (isIssue && !comments) {
            photoComments.addClass('is-invalid');
            return;
        }
        photoComments.removeClass('is-invalid');

        const formData = new FormData();
        formData.append('action', 'upload_photo');
        formData.append('task_id', currentTaskId);
        formData.append('activity_category_id', activityId);
        formData.append('comments', comments);
        formData.append('photo', selectedFile);

        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Uploading...');

        $.ajax({
            url: 'api/photo_handler.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    photoUploadModal.hide();
                    loadPhotoData();
                } else {
                    alert('Upload failed: ' + response.message);
                }
            },
            error: function() {
                alert('An unexpected error occurred during upload.');
            },
            complete: function() {
                savePhotoBtn.prop('disabled', false).text('Save Photo');
            }
        });
    });
    
    $('#view-tab').on('shown.bs.tab', function() {
        loadPhotoData();
    });

    // --- Core Functions ---

    function loadActivityTypesAndShowModal() {
        if (allActivityTypes.length > 0) {
            populateActivityDropdown();
            photoUploadModal.show();
        } else {
            $.ajax({
                url: 'api/photo_handler.php',
                type: 'GET',
                data: { action: 'get_activity_types' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        allActivityTypes = response.data;
                        populateActivityDropdown();
                        photoUploadModal.show();
                    }
                }
            });
        }
    }

    function populateActivityDropdown() {
        activityTypeSelect.empty().append('<option value="">-- Select Type --</option>');
        allActivityTypes.forEach(type => {
            activityTypeSelect.append($('<option>', { value: type.id, text: type.name }));
        });
    }

    function loadPhotoData() {
        if (!currentTaskId) return;

        $.ajax({
            url: 'api/photo_handler.php',
            type: 'GET',
            data: { action: 'get_activity_types' },
            dataType: 'json',
            success: function(typesResponse) {
                if (typesResponse.success) {
                    allActivityTypes = typesResponse.data;
                    
                    $.ajax({
                        url: 'api/photo_handler.php',
                        type: 'GET',
                        data: { action: 'get_photos_for_task', task_id: currentTaskId },
                        dataType: 'json',
                        success: function(photosResponse) {
                            if (photosResponse.success) {
                                renderChecklistAndGallery(allActivityTypes, photosResponse.data);
                            }
                        }
                    });
                }
            }
        });
    }

    function renderChecklistAndGallery(activities, photos) {
        photoChecklist.empty();
        photoGallery.empty();

        const uploadedActivityIds = new Set(photos.map(p => p.activity_id.toString()));

        activities.forEach(activity => {
            const hasPhoto = uploadedActivityIds.has(activity.id.toString());
            const itemClass = hasPhoto ? 'completed' : 'pending';
            const checklistItem = `<div class="list-group-item activity-item ${itemClass}">${activity.name}</div>`;
            photoChecklist.append(checklistItem);
        });

        if (photos.length > 0) {
            photos.forEach(photo => {
                const galleryCard = `
                    <div class="col-md-4">
                        <div class="card">
                            <img src="${photo.presigned_url || 'https://placehold.co/600x400?text=Preview'}" class="card-img-top" alt="${photo.activity_name}">
                            <div class="card-body">
                                <h6 class="card-title">${photo.activity_name}</h6>
                                <p class="card-text">${photo.comments || '<i>No notes provided.</i>'}</p>
                            </div>
                            <div class="card-footer text-muted">
                                Uploaded: ${new Date(photo.uploaded_at).toLocaleString()}
                            </div>
                        </div>
                    </div>`;
                photoGallery.append(galleryCard);
            });
        } else {
            photoGallery.html('<p class="text-center">No photos have been uploaded for this task yet.</p>');
        }
    }
});
