$(document).ready(function() {
    // --- Element Selectors ---
    const projectSelect = $('#projectSelection');
    const taskSelect = $('#taskSelection');
    const reportBuilderSections = $('#reportBuilderSections');
    const generateReportBtn = $('#generateReportBtn');
    const generationStatus = $('#generation-status');

    // --- Data Loading Functions ---

    function loadProjects() {
        projectSelect.html('<option value="">Loading Projects...</option>');
        $.getJSON('api/freport_actions.php?action=get_projects')
            .done(function(response) {
                projectSelect.empty().append('<option value="">-- Select Project --</option>');
                if (response.success && response.data.length > 0) {
                    response.data.forEach(project => {
                        projectSelect.append($('<option>', { value: project.id, text: project.job_name }));
                    });
                } else {
                    projectSelect.append('<option value="">No ongoing projects found</option>');
                }
            })
            .fail(function() {
                projectSelect.empty().append('<option value="">Error loading projects</option>');
            });
    }

    function loadTasksForProject(projectId) {
        taskSelect.html('<option value="">Loading Tasks...</option>').prop('disabled', false);
        $.getJSON(`api/activity_report_actions.php?action=get_tasks_for_project&project_id=${projectId}`)
            .done(function(response) {
                taskSelect.empty().append('<option value="">-- Select Task --</option>');
                if (response.success && response.data.length > 0) {
                    response.data.forEach(task => {
                        taskSelect.append($('<option>', { value: task.id, text: task.title }));
                    });
                } else {
                    taskSelect.html('<option value="">No tasks found for this project</option>');
                }
            })
            .fail(function() {
                taskSelect.html('<option value="">Error loading tasks</option>');
            });
    }

    function loadBuilderData(taskId) {
        reportBuilderSections.show();
        loadNoteTemplates();
        loadTaskPhotos(taskId);
    }

    function loadNoteTemplates() {
        ['Observation', 'Concern', 'Recommendation'].forEach(category => {
            const container = $(`#${category.toLowerCase()}s-body`);
            container.html('<p>Loading templates...</p>');

            $.getJSON(`api/activity_report_actions.php?action=get_note_templates&category=${category}`)
                .done(function(response) {
                    let contentHtml = '<div class="list-group mb-3">';
                    if (response.success && response.data.length > 0) {
                        response.data.forEach(template => {
                            contentHtml += `
                                <label class="list-group-item">
                                    <input class="form-check-input me-2" type="checkbox" name="note_${category.toLowerCase()}" value="${template.id}">
                                    ${$('<p>').text(template.text).html()}
                                </label>
                            `;
                        });
                    } else {
                        contentHtml += '<p class="text-muted">No templates found.</p>';
                    }
                    contentHtml += '</div>';
                    contentHtml += `
                        <h6>Add Custom ${category}</h6>
                        <textarea class="form-control" name="custom_note_${category.toLowerCase()}" rows="3" placeholder="Type a custom ${category.toLowerCase()}..."></textarea>
                    `;
                    container.html(contentHtml);
                })
                .fail(function() {
                    container.html(`<p class="text-danger">Error loading ${category} templates.</p>`);
                });
        });
    }
    
    function loadTaskPhotos(taskId) {
        const container = $('#photo-selection-container');
        container.html('<p>Loading photos...</p>');
        $.getJSON(`api/activity_report_actions.php?action=get_task_photos&task_id=${taskId}`)
            .done(function(response) {
                container.empty();
                if (response.success && response.data.length > 0) {
                    response.data.forEach(photo => {
                        const photoHtml = `
                            <div class="col-auto">
                                <label class="position-relative">
                                    <input class="form-check-input position-absolute top-0 start-0 m-1" type="checkbox" name="selected_photos" value="${photo.file_id}">
                                    <img src="${photo.thumbnail_url}" class="photo-thumbnail" alt="${photo.comments || 'Task photo'}">
                                </label>
                            </div>
                        `;
                        container.append(photoHtml);
                    });
                } else {
                    container.html('<p class="text-muted">No photos found for this task.</p>');
                }
            });
    }

    // --- Event Handlers ---

    projectSelect.on('change', function() {
        const projectId = $(this).val();
        reportBuilderSections.hide();
        taskSelect.html('<option value="">-- Select a project first --</option>').prop('disabled', true);
        if (projectId) {
            loadTasksForProject(projectId);
        }
    });

    taskSelect.on('change', function() {
        const taskId = $(this).val();
        if (taskId) {
            loadBuilderData(taskId);
        } else {
            reportBuilderSections.hide();
        }
    });

    generateReportBtn.on('click', function() {
        const taskId = taskSelect.val();
        if (!taskId) {
            alert('Please select a task first.');
            return;
        }

        const reportData = {
            task_id: taskId,
            notes: {
                observations: {
                    templates: $('input[name="note_observation"]:checked').map((_, el) => $(el).val()).get(),
                    custom: $('textarea[name="custom_note_observation"]').val()
                },
                concerns: {
                    templates: $('input[name="note_concern"]:checked').map((_, el) => $(el).val()).get(),
                    custom: $('textarea[name="custom_note_concern"]').val()
                },
                recommendations: {
                    templates: $('input[name="note_recommendation"]:checked').map((_, el) => $(el).val()).get(),
                    custom: $('textarea[name="custom_note_recommendation"]').val()
                }
            },
            photos: $('input[name="selected_photos"]:checked').map((_, el) => $(el).val()).get(),
        };

        $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Generating...');
        generationStatus.html('<em class="text-muted">Building your report... This may take a moment.</em>');

        $.ajax({
            url: 'api/generate_activity_report_pdf.php',
            type: 'POST',
            data: JSON.stringify(reportData),
            contentType: 'application/json; charset=utf-8',
            xhrFields: { responseType: 'blob' }
        })
        .done(function(blob, status, xhr) {
             if (blob.type === 'application/pdf') {
                const disposition = xhr.getResponseHeader('Content-Disposition');
                let filename = `activity-report.pdf`;
                if (disposition && disposition.indexOf('attachment') !== -1) {
                    const matches = /filename="?([^"]+)"?/.exec(disposition);
                    if (matches && matches[1]) filename = matches[1];
                }

                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                a.remove();
                generationStatus.html('<span class="text-success">Report downloaded successfully!</span>');
            } else {
                generationStatus.html('<span class="text-danger">Error: Invalid file received from server.</span>');
            }
        })
        .fail(function() {
            generationStatus.html('<span class="text-danger">An error occurred while generating the report.</span>');
        })
        .always(function() {
            generateReportBtn.prop('disabled', false).html('<i class="modus-icons notranslate">file_text</i> Generate Activity Report');
        });
    });

    // --- Initial Load ---
    loadProjects();
});
