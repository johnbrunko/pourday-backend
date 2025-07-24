$(document).ready(function() {
    const codeEntryModal = new bootstrap.Modal(document.getElementById('codeEntryModal'));
    const codeInput = $('#codeInput');
    const submitCodeBtn = $('#submitCodeBtn');
    const codeError = $('#codeError');
    const uploaderContainer = $('#uploaderContainer');
    const uploadForm = $('#uploadForm');

    // Show the code entry modal on page load
    codeEntryModal.show();

    // --- Code Validation ---
    submitCodeBtn.on('click', function() {
        const code = codeInput.val().trim();
        if (code.length !== 5) {
            codeError.text('Please enter a valid 5-digit code.').show();
            return;
        }

        $.ajax({
            url: 'api/public_actions.php',
            type: 'POST',
            data: { request_type: 'validate_code', upload_code: code },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // --- MODIFIED: Store validated data directly on the form element ---
                    uploadForm.data('project-id', response.project_id);
                    uploadForm.data('task-id', response.task_id);
                    uploadForm.data('upload-code', code); 

                    // Populate the display fields
                    $('#projectName').text(response.project_name);
                    $('#taskName').text(response.task_name);
                    
                    codeEntryModal.hide();
                    uploaderContainer.fadeIn();
                } else {
                    codeError.text(response.message || 'Invalid code. Please try again.').show();
                }
            },
            error: function() {
                codeError.text('An error occurred. Please try again later.').show();
            }
        });
    });

    // --- File Upload Logic ---
    const progressBar = $('#progressBar');
    const progressCount = $('#progressCount');
    const progressWrapper = $('#progressWrapper');
    const uploadStatus = $('#uploadStatus');
    const fileInput = $('#file_input');

    uploadForm.on('submit', async function(e) {
        e.preventDefault();
        const files = fileInput[0].files;
        if (files.length === 0) { alert('Please select at least one file or a folder.'); return; }
        
        // Reset UI
        uploadStatus.empty().removeClass('alert-success alert-danger');
        progressWrapper.show();
        progressBar.width('0%').removeClass('bg-success bg-danger');
        progressCount.text(`0 / ${files.length}`);

        let totalFiles = files.length, successfulUploads = 0, failedUploads = 0;

        for (const file of files) {
            const currentFileNumber = successfulUploads + failedUploads + 1;
            progressBar.width(`${(currentFileNumber / totalFiles) * 100}%`);
            progressCount.text(`${currentFileNumber} / ${totalFiles}`);

            try {
                const presignedUrl = await getPresignedUrl(file);
                await uploadToWasabi(presignedUrl, file);
                successfulUploads++;
            } catch (error) {
                console.error('Upload failed for file:', file.name, 'Error:', error);
                failedUploads++;
            }
        }
        // Final status update
        progressBar.addClass(failedUploads > 0 ? 'bg-danger' : 'bg-success');
        if (successfulUploads > 0) uploadStatus.addClass('alert-success').text(`${successfulUploads} file(s) uploaded successfully.`);
        if (failedUploads > 0) uploadStatus.addClass('alert-danger').append(`<br>${failedUploads} file(s) failed to upload.`);
    });

    // Helper functions
    function getPresignedUrl(file) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            // --- MODIFIED: Read validated data from the form's data attributes ---
            formData.append('upload_code', uploadForm.data('upload-code'));
            formData.append('project_id', uploadForm.data('project-id'));
            formData.append('task_id', uploadForm.data('task-id'));
            formData.append('filename', file.webkitRelativePath || file.name);
            formData.append('contentType', file.type || 'application/octet-stream');
            formData.append('upload_type', $('input[name="upload_type"]:checked').val());

            $.ajax({
                url: 'api/get_wasabi_upload_url.php',
                type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
                success: res => res.status === 'success' ? resolve(res.uploadUrl) : reject(new Error(res.message)),
                error: () => reject(new Error('AJAX error getting pre-signed URL.'))
            });
        });
    }

    function uploadToWasabi(url, file) {
        return new Promise((resolve, reject) => {
            $.ajax({
                url: url, type: 'PUT', data: file, processData: false, contentType: file.type || 'application/octet-stream',
                success: () => resolve(),
                error: (xhr) => reject(new Error('Upload to Wasabi failed: ' + xhr.statusText))
            });
        });
    }
    
    // Toggle folder selection attribute
    $('input[name="upload_type"]').on('change', function() {
        fileInput.attr(this.value === 'folders' ? { 'webkitdirectory': '', 'directory': '' } : {}).removeAttr(this.value !== 'folders' ? 'webkitdirectory directory' : '');
    });
});