$(document).ready(function() {
    // --- Element Selectors ---
    const projectIdSelect = $('#project_id');
    const taskIdSelect = $('#task_id');
    const uploadForm = $('#uploadForm');
    const uploadButton = uploadForm.find('button[type="submit"]');
    const fileInput = $('#file_input');
    const uploadTypeRadios = $('input[name="upload_type"]');
    
    // --- New Progress Bar Selectors ---
    const progressWrapper = $('#progressWrapper');
    const progressBar = $('#progressBar');
    const progressText = progressBar.find('.progress-text'); // Text is now inside the bar
    const progressCount = $('#progressCount');
    const uploadStatus = $('#uploadStatus');

    // --- Dynamic Dropdown Logic (unchanged) ---
    projectIdSelect.on('change', function() {
        const selectedProjectId = $(this).val();
        taskIdSelect.html('<option value="">-- Select a project first --</option>').prop('disabled', true);
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

    // --- Adjust file input for folder selection (unchanged) ---
    uploadTypeRadios.on('change', function() {
        if (this.value === 'folders') {
            fileInput.attr({ 'webkitdirectory': '', 'directory': '' });
        } else {
            fileInput.removeAttr('webkitdirectory directory');
        }
    });

    // --- Main Form Submission Logic (MODIFIED) ---
    uploadForm.on('submit', async function(e) {
        e.preventDefault();

        const files = fileInput[0].files;
        if (files.length === 0) {
            alert('Please select at least one file or a folder.');
            return;
        }
        if (!projectIdSelect.val() || !taskIdSelect.val()) {
            alert('Please select both a Project and a Task before uploading.');
            return;
        }

        // Reset UI for new upload
        uploadStatus.empty().removeClass('alert alert-success alert-danger');
        progressWrapper.show();
        progressBar.width('0%').removeClass('bg-success bg-danger');
        progressText.text('Preparing upload...');
        progressCount.text(`0 / ${files.length}`);
        uploadButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Uploading...');

        let totalFiles = files.length;
        let successfulUploads = 0;
        let failedUploads = 0;

        for (const file of files) {
            const currentFileNumber = successfulUploads + failedUploads + 1;
            
            let percentComplete = Math.round(((currentFileNumber - 1) / totalFiles) * 100);
            progressBar.width(percentComplete + '%');
            progressCount.text(`${currentFileNumber} / ${totalFiles}`);
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
        
        // --- Final Status Update ---
        progressBar.width('100%'); // Fill the bar at the end
        if (failedUploads > 0) {
            progressBar.addClass('bg-danger');
            progressText.text('Upload complete with errors');
        } else {
            progressBar.addClass('bg-success');
            progressText.text('Upload Complete!');
        }

        if (successfulUploads > 0) {
            uploadStatus.addClass('alert alert-success').text(`${successfulUploads} of ${totalFiles} file(s) uploaded successfully.`);
        }
        if (failedUploads > 0) {
            const currentMessage = uploadStatus.html();
            uploadStatus.removeClass('alert-success').addClass('alert alert-danger').html(currentMessage + `<br><strong>${failedUploads} file(s) failed to upload. Check console for details.</strong>`);
        }
        
        uploadButton.prop('disabled', false).html('Upload Files');
        uploadForm[0].reset();
        taskIdSelect.html('<option value="">-- Select a project first --</option>').prop('disabled', true);
        setTimeout(() => progressWrapper.hide(), 5000);
    });

    // --- Helper Functions (unchanged) ---
    function getPresignedUrl(file) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('project_id', projectIdSelect.val());
            formData.append('task_id', taskIdSelect.val());
            formData.append('filename', file.webkitRelativePath || file.name); 
            formData.append('contentType', file.type || 'application/octet-stream');
            formData.append('upload_type', $('input[name="upload_type"]:checked').val());

            $.ajax({
                url: 'api/get_wasabi_upload_url.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success' && response.uploadUrl) {
                        resolve(response.uploadUrl);
                    } else {
                        reject(new Error(response.message || 'Server rejected URL request.'));
                    }
                },
                error: function(xhr) {
                    reject(new Error('AJAX error getting pre-signed URL: ' + (xhr.responseJSON?.message || 'Server error')));
                }
            });
        });
    }

    function uploadToWasabi(url, file) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: url,
                type: 'PUT',
                data: file,
                processData: false,
                contentType: file.type || 'application/octet-stream',
                success: function() {
                    resolve();
                },
                error: function(xhr) {
                    reject(new Error('Failed to upload file to Wasabi. Status: ' + xhr.statusText));
                }
            });
        });
    }
});