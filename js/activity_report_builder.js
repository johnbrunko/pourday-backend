$(document).ready(function() {
    // --- Element Selectors ---
    const projectSelect = $('#projectSelection');
    const taskSelect = $('#taskSelection');
    const reportBuilderSections = $('#reportBuilderSections');
    const generateReportBtn = $('#generateReportBtn');
    const generationStatus = $('#generation-status');
    const notesAccordion = $('#notesAccordion');
    const selectedObservationsList = $('#selected-observations-list');
    const selectedConcernsList = $('#selected-concerns-list');
    const selectedRecommendationsList = $('#selected-recommendations-list');
    const reorderablePhotosList = $('#reorderable-photos-list');

    // --- Initialize SortableJS for Notes ---
    ['selected-observations-list', 'selected-concerns-list', 'selected-recommendations-list'].forEach(id => {
        new Sortable(document.getElementById(id), {
            group: id,
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
        });
    });

    // --- Initialize SortableJS for Photos ---
    if (reorderablePhotosList.length) {
        new Sortable(reorderablePhotosList[0], {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'sortable-ghost',
        });
    }

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
            });
    }

    function loadBuilderData(taskId) {
        reportBuilderSections.show();
        loadAllNoteTemplates();
        loadTaskPhotos(taskId);
    }
    
    function addNoteToSelectedList(id, text, category, type) {
        const targetList = getListForCategory(category);
        if (!targetList || targetList.find(`[data-id="${id}"]`).length > 0) {
            return; 
        }
        const noteHtml = `
            <div class="selected-note-item" data-id="${id}" data-type="${type}" data-category="${category}">
                <i class="modus-icons notranslate drag-handle">drag_handle</i>
                <span class="flex-grow-1">${$('<p>').text(text).html()}</span>
            </div>
        `;
        targetList.append(noteHtml);
    }
    
    function removeNoteFromSelectedList(id) {
        [selectedObservationsList, selectedConcernsList, selectedRecommendationsList].forEach(list => {
            list.find(`[data-id="${id}"]`).remove();
        });
    }

    function getListForCategory(category) {
        if (category === 'Observation') return selectedObservationsList;
        if (category === 'Concern') return selectedConcernsList;
        if (category === 'Recommendation') return selectedRecommendationsList;
        return null;
    }

    function loadAllNoteTemplates() {
        notesAccordion.html('<p class="text-muted p-3">Loading note templates...</p>');
        $.getJSON(`api/activity_report_actions.php?action=get_all_note_templates`)
            .done(function(response) {
                notesAccordion.empty();
                if (response.success) {
                    const categories = ['Observation', 'Concern', 'Recommendation'];
                    categories.forEach((category, index) => {
                        const templates = response.data[category] || [];
                        const isExpanded = index === 0 ? 'true' : 'false';
                        const showClass = index === 0 ? 'show' : '';
                        
                        let accordionItemHtml = `
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button ${!isExpanded ? 'collapsed' : ''}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse${category}" aria-expanded="${isExpanded}">
                                        ${category}s
                                    </button>
                                </h2>
                                <div id="collapse${category}" class="accordion-collapse collapse ${showClass}" data-bs-parent="#notesAccordion">
                                    <div class="accordion-body">
                                        <div class="list-group mb-3">`;
                        
                        if (templates.length > 0) {
                            templates.forEach(template => {
                                accordionItemHtml += `
                                    <label class="list-group-item">
                                        <input class="form-check-input me-2 note-template-checkbox" type="checkbox" data-id="${template.id}" data-text="${$('<p>').text(template.text).html()}" data-category="${category}">
                                        ${$('<p>').text(template.text).html()}
                                    </label>`;
                            });
                        } else {
                            accordionItemHtml += '<p class="text-muted">No templates found.</p>';
                        }

                        accordionItemHtml += `
                                        </div>
                                        <h6>Add Custom ${category}</h6>
                                        <div class="input-group">
                                            <textarea class="form-control custom-note-textarea" data-category="${category}" rows="3" placeholder="Type a custom ${category.toLowerCase()}..."></textarea>
                                            <button class="btn btn-outline-secondary add-custom-note-btn" type="button">Add</button>
                                        </div>
                                    </div>
                                </div>
                            </div>`;
                        notesAccordion.append(accordionItemHtml);
                    });
                }
            });
    }

    function loadTaskPhotos(taskId) {
        reorderablePhotosList.html('<p class="text-muted p-3">Loading photos...</p>');
        $.getJSON(`api/activity_report_actions.php?action=get_task_photos&task_id=${taskId}`)
            .done(function(response) {
                reorderablePhotosList.empty();
                if (response.success && response.data.length > 0) {
                    response.data.forEach(photo => {
                        let captionHtml = `<strong>${$('<div>').text(photo.activity_name || 'Uncategorized').html()}</strong>`;
                        
                        // --- MODIFIED: This check now also ensures the comment is not "0" ---
                        if (photo.comments && photo.comments.trim() !== '' && photo.comments.trim() !== '0') {
                            captionHtml += `: ${$('<div>').text(photo.comments).html()}`;
                        }

                        const photoItem = `
                            <div class="photo-reorder-item" data-id="${photo.file_id}">
                                <i class="modus-icons notranslate drag-handle">drag_handle</i>
                                <img src="${photo.thumbnail_url}" class="photo-thumbnail-reorder" alt="${photo.activity_name || 'Task photo'}">
                                <span class="photo-caption-reorder"></span>
                            </div>
                        `;
                        
                        const photoElement = $(photoItem);
                        photoElement.find('.photo-caption-reorder').html(captionHtml);
                        reorderablePhotosList.append(photoElement);
                    });
                } else {
                    reorderablePhotosList.html('<p class="text-muted p-3">No photos found for this task.</p>');
                }
            });
    }

    // --- Event Handlers ---
    function clearSelectedNotes() {
        selectedObservationsList.empty();
        selectedConcernsList.empty();
        selectedRecommendationsList.empty();
    }

    projectSelect.on('change', function() {
        const projectId = $(this).val();
        reportBuilderSections.hide();
        taskSelect.html('<option value="">-- Select a project first --</option>').prop('disabled', true);
        clearSelectedNotes();
        reorderablePhotosList.empty();
        if (projectId) {
            loadTasksForProject(projectId);
        }
    });

    taskSelect.on('change', function() {
        const taskId = $(this).val();
        clearSelectedNotes();
        reorderablePhotosList.empty();
        if (taskId) {
            loadBuilderData(taskId);
        } else {
            reportBuilderSections.hide();
        }
    });
    
    notesAccordion.on('change', '.note-template-checkbox', function() {
        const checkbox = $(this);
        const id = checkbox.data('id');
        const text = checkbox.data('text');
        const category = checkbox.data('category');

        if (checkbox.is(':checked')) {
            addNoteToSelectedList(id, text, category, 'template');
        } else {
            removeNoteFromSelectedList(id);
        }
    });

    notesAccordion.on('click', '.add-custom-note-btn', function() {
        const button = $(this);
        const textarea = button.siblings('textarea.custom-note-textarea');
        const text = textarea.val().trim();
        const category = textarea.data('category');

        if (text) {
            const customId = 'custom_' + new Date().getTime(); 
            addNoteToSelectedList(customId, text, category, 'custom');
            textarea.val('');
        }
    });

    generateReportBtn.on('click', function() {
        const taskId = taskSelect.val();
        if (!taskId) {
            alert('Please select a task first.');
            return;
        }
        
        const orderedNotes = [];
        [selectedObservationsList, selectedConcernsList, selectedRecommendationsList].forEach(list => {
            list.find('.selected-note-item').each(function() {
                const item = $(this);
                orderedNotes.push({
                    type: item.data('type'),
                    category: item.data('category'),
                    id: item.data('type') === 'template' ? item.data('id') : null,
                    text: item.data('type') === 'custom' ? item.find('span').text() : null
                });
            });
        });

        const orderedPhotoIds = [];
        reorderablePhotosList.find('.photo-reorder-item').each(function() {
            orderedPhotoIds.push($(this).data('id'));
        });

        const reportData = {
            task_id: taskId,
            ordered_notes: orderedNotes,
            photos: orderedPhotoIds,
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
