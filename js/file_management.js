$(document).ready(function() {
    // --- Element Selectors ---
    const projectIdSelect = $('#project_id');
    const taskIdSelect = $('#task_id');
    const uploadForm = $('#uploadForm');
    const uploadButton = uploadForm.find('button[type="submit"]');
    const fileInput = $('#file_input');
    const uploadTypeRadios = $('input[name="upload_type"]');
    
    // --- Tab & Viewer Selectors ---
    const viewFilesTab = $('#view-tab');
    const fileViewerContent = $('#file-viewer-content');

    // --- Progress Bar Selectors ---
    const progressWrapper = $('#progressWrapper');
    const progressBar = $('#progressBar');
    const progressText = progressBar.find('.progress-text');
    const progressCount = $('#progressCount');
    const uploadStatus = $('#uploadStatus');

    // --- Dynamic Dropdown Logic ---
    projectIdSelect.on('change', function() {
        const selectedProjectId = $(this).val();
        taskIdSelect.html('<option value="">-- Select a project first --</option>').prop('disabled', true);
        viewFilesTab.prop('disabled', true);
        fileViewerContent.html('<p class="text-center text-muted">Select a project and task to view files.</p>');

        if (!selectedProjectId) return;

        taskIdSelect.html('<option value="">Loading tasks...</option>').prop('disabled', false);
        $.ajax({
            url: 'api/upload_actions.php',
            type: 'GET',
            data: { request_type: 'get_tasks_for_project', project_id: selectedProjectId },
            dataType: 'json',
            success: function(response) {
                taskIdSelect.empty();
                if (response.success && response.data.length > 0) {
                    taskIdSelect.append('<option value="">-- Select a Task --</option>');
                    response.data.forEach(task => taskIdSelect.append($('<option>', { value: task.id, text: task.title })));
                } else {
                    taskIdSelect.html('<option value="">-- No active tasks found --</option>');
                }
            },
            error: function() { taskIdSelect.html('<option value="">-- Error loading tasks --</option>'); }
        });
    });

    // --- Task Selection Logic ---
    taskIdSelect.on('change', function() {
        const selectedTaskId = $(this).val();
        if (selectedTaskId) {
            viewFilesTab.prop('disabled', false);
            if(viewFilesTab.hasClass('active')) {
                fetchAndDisplayFiles();
            }
        } else {
            viewFilesTab.prop('disabled', true);
            fileViewerContent.html('<p class="text-center text-muted">Select a task to view files.</p>');
        }
    });

    // --- Event listener for clicking the 'View Files' tab ---
    viewFilesTab.on('show.bs.tab', function() {
        fetchAndDisplayFiles();
    });

    // --- Function to Fetch and Display Files ---
    function fetchAndDisplayFiles() {
        const taskId = taskIdSelect.val();
        const projectId = projectIdSelect.val(); // Pass project_id as well

        if (!taskId || !projectId) {
            return;
        }

        fileViewerContent.html('<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div><p>Loading files...</p></div>');

        $.ajax({
            url: 'api/get_files.php', 
            type: 'GET',
            data: { 
                project_id: projectId,
                task_id: taskId 
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderFileLists(response.data);
                } else {
                    fileViewerContent.html(`<div class="alert alert-danger">${response.message || 'An error occurred.'}</div>`);
                }
            },
            error: function() {
                fileViewerContent.html('<div class="alert alert-danger">Error loading files. Please try again.</div>');
            }
        });
    }

    // --- Function to Render File Lists ---
    function renderFileLists(data) {
        fileViewerContent.empty(); 

        if (!data.images.length && !data.docs.length && !data.folders.length) {
            fileViewerContent.html('<p class="text-center text-muted">No files have been uploaded for this task yet.</p>');
            return;
        }

        // Render Images
        if (data.images.length > 0) {
            let imageHtml = '<h5><i class="modus-icon modus-icon-image"></i> Images</h5><div class="list-group mb-4">';
            data.images.forEach(file => {
                imageHtml += `<a href="${file.url}" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">${file.name}<i class="modus-icon modus-icon-download"></i></a>`;
            });
            imageHtml += '</div>';
            fileViewerContent.append(imageHtml);
        }

        // Render Documents
        if (data.docs.length > 0) {
            let docHtml = '<h5><i class="modus-icon modus-icon-file-text"></i> Documents</h5><div class="list-group mb-4">';
            data.docs.forEach(file => {
                docHtml += `<a href="${file.url}" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">${file.name}<i class="modus-icon modus-icon-download"></i></a>`;
            });
            docHtml += '</div>';
            fileViewerContent.append(docHtml);
        }

        // Render Folder Groups
        if (data.folders.length > 0) {
            let folderHtml = '<h5><i class="modus-icon modus-icon-folder-zip"></i> Lidar Scans (Folders)</h5><div class="list-group mb-4">';
            data.folders.forEach(folder => {
                folderHtml += `<div class="list-group-item d-flex justify-content-between align-items-center">
                                 <span><i class="modus-icon modus-icon-folder"></i> ${folder.name}</span>
                                 <button class="btn btn-sm btn-outline-primary download-folder-btn" data-prefix="${folder.key_prefix}" title="Download folder as a .zip file.">
                                     Download Folder
                                 </button>
                               </div>`;
            });
            folderHtml += '</div>';
            fileViewerContent.append(folderHtml);
        }
    }
    
    // --- Event listener for download button ---
    fileViewerContent.on('click', '.download-folder-btn', function() {
        const $button = $(this);
        const prefix = $button.data('prefix');
        const originalHtml = $button.html();

        if (!prefix) {
            alert('Error: Could not find folder path.');
            return;
        }

        // Give user feedback that the download is preparing
        $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Preparing...');

        // Trigger the download by navigating to the zipping script
        window.location.href = `api/zip_and_download.php?prefix=${encodeURIComponent(prefix)}`;

        // After a delay, revert the button to its original state.
        // This prevents users from spamming the button while allowing them to re-click if the download fails.
        setTimeout(() => {
            $button.prop('disabled', false).html(originalHtml);
        }, 4000); // 4 seconds
    });

    // --- Adjust file input for folder selection ---
    uploadTypeRadios.on('change', function() {
        if (this.value === 'folders') {
            fileInput.attr({ 'webkitdirectory': '', 'directory': '' });
        } else {
            fileInput.removeAttr('webkitdirectory directory');
        }
    });

    // --- Main Form Submission Logic ---
    uploadForm.on('submit', async function(e) {
        e.preventDefault();
        const files = fileInput[0].files;
        if (files.length === 0) { alert('Please select at least one file or a folder.'); return; }
        if (!projectIdSelect.val() || !taskIdSelect.val()) { alert('Please select both a Project and a Task before uploading.'); return; }

        uploadStatus.empty().removeClass('alert alert-success alert-danger');
        progressWrapper.show();
        progressBar.width('0%').removeClass('bg-success bg-danger');
        progressText.text('Preparing upload...');
        progressCount.text(`0 / ${files.length}`);
        uploadButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Uploading...');

        let successfulUploads = 0, failedUploads = 0;
        for (const file of files) {
            const currentFileNumber = successfulUploads + failedUploads + 1;
            let percentComplete = Math.round(((currentFileNumber - 1) / files.length) * 100);
            progressBar.width(percentComplete + '%');
            progressCount.text(`${currentFileNumber} / ${files.length}`);
            progressText.text(`Uploading: ${file.name}`);
            try {
                const presignedUrl = await getPresignedUrl(file);
                await uploadToWasabi(presignedUrl, file);
                successfulUploads++;
            } catch (error) {
                console.error('Upload failed for file:', file.name, 'Error:', error);
                failedUploads++;
            }
        }
        
        progressBar.width('100%');
        if (failedUploads > 0) { progressBar.addClass('bg-danger'); progressText.text('Upload complete with errors'); } 
        else { progressBar.addClass('bg-success'); progressText.text('Upload Complete!'); }
        if (successfulUploads > 0) { uploadStatus.addClass('alert alert-success').text(`${successfulUploads} of ${files.length} file(s) uploaded successfully.`); }
        if (failedUploads > 0) {
            const currentMessage = uploadStatus.html();
            uploadStatus.removeClass('alert-success').addClass('alert alert-danger').html(currentMessage + `<br><strong>${failedUploads} file(s) failed to upload. Check console for details.</strong>`);
        }
        uploadButton.prop('disabled', false).html('Upload Files');
        uploadForm[0].reset();
        setTimeout(() => {
            progressWrapper.hide();
            if(viewFilesTab.hasClass('active')) { fetchAndDisplayFiles(); }
        }, 3000);
    });

    // --- Helper Functions for Uploading ---
    function getPresignedUrl(file) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('project_id', projectIdSelect.val());
            formData.append('task_id', taskIdSelect.val());
            const fileNameForPath = file.webkitRelativePath || file.name;
            formData.append('filename', fileNameForPath);  
            formData.append('contentType', file.type || 'application/octet-stream');
            formData.append('upload_type', $('input[name="upload_type"]:checked').val());
            $.ajax({
                url: 'api/get_wasabi_upload_url.php', type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
                success: function(response) {
                    if (response.status === 'success' && response.uploadUrl) { resolve(response.uploadUrl); } 
                    else { reject(new Error(response.message || 'Server rejected URL request.')); }
                },
                error: function(xhr) { reject(new Error('AJAX error getting pre-signed URL: ' + (xhr.responseJSON?.message || 'Server error'))); }
            });
        });
    }
    function uploadToWasabi(url, file) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: url, type: 'PUT', data: file, processData: false, contentType: file.type || 'application/octet-stream',
                success: function() { resolve(); },
                error: function(xhr) { reject(new Error('Failed to upload file to Wasabi. Status: ' + xhr.statusText)); }
            });
        });
    }
});

