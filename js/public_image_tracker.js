// File: js/public_image_tracker.js

$(document).ready(function() {
    // --- Modal and Element Selectors ---
    const codeEntryModal = new bootstrap.Modal(document.getElementById('codeEntryModal'));
    const photoUploadModal = new bootstrap.Modal(document.getElementById('photoUploadModal'));
    const uploadCodeInput = $('#uploadCodeInput');
    const submitCodeBtn = $('#submitCodeBtn');
    const codeError = $('#codeError');
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
    let allActivityTypes = [];
    let selectedFile = null;

    // --- Initial Setup ---
    codeEntryModal.show();

    // --- Event Listeners ---

    // Handle code submission
    submitCodeBtn.on('click', function() {
        validateCode();
    });
    $('#codeEntryForm').on('submit', function(e) {
        e.preventDefault();
        validateCode();
    });

    function validateCode() {
        const code = uploadCodeInput.val();
        if (code.length !== 5) {
            codeError.text('Code must be 5 characters.').removeClass('d-none');
            return;
        }

        submitCodeBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
        codeError.addClass('d-none');

        $.ajax({
            url: 'api/public_image_tracker_handler.php',
            type: 'POST',
            data: { action: 'validate_code', upload_code: code },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#trackingHeader').text(`Image Upload for: ${response.data.task_name}`);
                    $('#trackingSubHeader').text(`Project: ${response.data.project_name}`);
                    mainContent.show();
                    codeEntryModal.hide();
                    loadPhotoData(); // Initial load of photo data
                } else {
                    codeError.text(response.message).removeClass('d-none');
                }
            },
            error: function() {
                codeError.text('An error occurred. Please try again.').removeClass('d-none');
            },
            complete: function() {
                submitCodeBtn.prop('disabled', false).text('Submit');
            }
        });
    }

    // --- Logic copied from image_tracker.js (with minor adjustments) ---
    
    cameraIconContainer.on('click', function() { photoInput.click(); });

    photoInput.on('change', async function(event) {
        let file = event.target.files[0];
        if (!file) return;

        cameraIconContainer.html('<div class="spinner-border text-primary"></div>');

        const fileName = file.name.toLowerCase();
        if (fileName.endsWith('.heic') || fileName.endsWith('.heif')) {
            if (typeof heic2any === 'undefined') {
                alert('Error: The HEIC conversion library failed to load.');
                cameraIconContainer.html('<i id="cameraIcon" class="modus-icons notranslate">camera</i>');
                return;
            }
            try {
                const conversionResult = await heic2any({ blob: file, toType: "image/jpeg", quality: 0.8 });
                file = new File([conversionResult], file.name.replace(/\.(heic|heif)$/i, '.jpeg'), { type: 'image/jpeg' });
            } catch (e) {
                alert('Error converting HEIC image.');
                cameraIconContainer.html('<i id="cameraIcon" class="modus-icons notranslate">camera</i>');
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
        cameraIconContainer.html('<i id="cameraIcon" class="modus-icons notranslate">camera</i>');
    });

    activityTypeSelect.on('change', function() {
        const isIssue = $(this).find('option:selected').text().toLowerCase() === 'issue';
        photoComments.prop('required', isIssue).toggleClass('is-invalid', false);
    });

    savePhotoBtn.on('click', function() {
        const activityId = activityTypeSelect.val();
        const comments = photoComments.val();
        if (photoComments.prop('required') && !comments) {
            photoComments.addClass('is-invalid');
            return;
        }
        photoComments.removeClass('is-invalid');

        const formData = new FormData();
        formData.append('action', 'upload_photo');
        formData.append('activity_category_id', activityId);
        formData.append('comments', comments);
        formData.append('photo', selectedFile);

        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Uploading...');

        $.ajax({
            url: 'api/public_image_tracker_handler.php',
            type: 'POST',
            data: formData,
            processData: false, contentType: false, dataType: 'json',
            success: function(response) {
                if (response.success) {
                    photoUploadModal.hide();
                    loadPhotoData();
                } else {
                    alert('Upload failed: ' + response.message);
                }
            },
            error: function() { alert('An unexpected error occurred during upload.'); },
            complete: function() { savePhotoBtn.prop('disabled', false).text('Save Photo'); }
        });
    });
    
    $('#view-tab').on('shown.bs.tab', function() { loadPhotoData(); });

    function loadActivityTypesAndShowModal() {
        if (allActivityTypes.length > 0) {
            populateActivityDropdown();
            photoUploadModal.show();
        } else {
            $.ajax({
                url: 'api/public_image_tracker_handler.php',
                type: 'GET', data: { action: 'get_activity_types' }, dataType: 'json',
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
        $.ajax({
            url: 'api/public_image_tracker_handler.php',
            type: 'GET', data: { action: 'get_activity_types' }, dataType: 'json',
            success: function(typesResponse) {
                if (typesResponse.success) {
                    allActivityTypes = typesResponse.data;
                    $.ajax({
                        url: 'api/public_image_tracker_handler.php',
                        type: 'GET', data: { action: 'get_photos_for_task' }, dataType: 'json',
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
            photoChecklist.append(`<div class="list-group-item activity-item ${itemClass}">${activity.name}</div>`);
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
                            <div class="card-footer text-muted">Uploaded: ${new Date(photo.uploaded_at).toLocaleString()}</div>
                        </div>
                    </div>`;
                photoGallery.append(galleryCard);
            });
        } else {
            photoGallery.html('<p class="text-center">No photos have been uploaded for this task yet.</p>');
        }
    }
});