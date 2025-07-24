// js/freport_uploader.js - FINAL VERSION WITH CONDITIONAL HEADER SKIPPING
$(document).ready(function() {
    // Existing Vanilla JS DOM elements
    const htmlFileInput = document.getElementById('htmlFile');
    const processButton = document.getElementById('processAndSaveBtn');
    const statusArea = document.getElementById('statusArea');
    const imageFileInput = document.getElementById('imageFile');
    const uploadedImagePreviewContainer = document.getElementById('uploadedImagePreviewContainer');
    const uploadedImagePreview = document.getElementById('uploadedImagePreview');
    const reportNameInput = document.getElementById('reportName');
    const reportNameErrorSpan = document.querySelector('#uploadHtmlReportForm .reportName-error');
    const mainErrorSpan = document.querySelector('#uploadHtmlReportForm .main-upload-error');
    const htmlFileErrorSpan = document.querySelector('#uploadHtmlReportForm .htmlFileToUpload-error');
    const imageFileErrorSpan = document.querySelector('#uploadHtmlReportForm .imageFileToUpload-error');

    // jQuery selected elements
    const projectSelect = $('#projectSelection');
    const taskSelect = $('#taskSelection');
    const activeEventIdInput = $('#activeEventId');
    const projectErrorSpan = $('.projectSelection-error');
    const taskErrorSpan = $('.task-error');

    function cleanMsoSpansInDocument(documentObject) {
        if (!documentObject) return;
        const msoSpans = documentObject.querySelectorAll("span[style='mso-spacerun:yes']");
        msoSpans.forEach(span => {
            span.remove();
        });
    }

    function loadProjects() {
        projectSelect.html('<option value="">Loading projects...</option>').prop('disabled', true);
        taskSelect.html('<option value="">Select a project first</option>').prop('disabled', true);
        activeEventIdInput.val('');
        projectErrorSpan.text('');
        taskErrorSpan.text('');

        $.ajax({
            url: 'components/f_form_data_source.php?action=get_projects',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                projectSelect.empty().append('<option value="">-- Select Project --</option>');
                if (response.success && response.data.length > 0) {
                    $.each(response.data, function(index, project) {
                        projectSelect.append($('<option>', {
                            value: project.id,
                            text: project.job_name
                        }));
                    });
                } else if (response.data.length === 0) {
                    projectSelect.append('<option value="">No projects found</option>');
                    projectErrorSpan.text('No projects available to select.');
                } else {
                    projectSelect.append('<option value="">Error loading projects</option>');
                    projectErrorSpan.text(response.message || 'Could not load projects.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                projectSelect.empty().append('<option value="">Error loading projects</option>');
                projectErrorSpan.text('AJAX error fetching projects: ' + textStatus);
                console.error("AJAX error get_projects:", textStatus, errorThrown);
            },
            complete: function() {
                projectSelect.prop('disabled', false);
            }
        });
    }

    function loadTasks(projectId) {
        taskSelect.html('<option value="">Loading tasks...</option>').prop('disabled', true);
        activeEventIdInput.val('');
        taskErrorSpan.text('');

        if (!projectId) {
            taskSelect.html('<option value="">Select a project first</option>').prop('disabled', true);
            return;
        }

        $.ajax({
            url: `api/upload_actions.php?request_type=get_tasks_for_project&project_id=${projectId}`,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                taskSelect.empty().append('<option value="">-- Select Task --</option>');
                if (response.success && response.data.length > 0) {
                    $.each(response.data, function(index, event) {
                        taskSelect.append($('<option>', {
                            value: event.id,
                            text: event.title
                        }));
                    });
                    taskSelect.prop('disabled', false);
                } else if (response.data.length === 0) {
                    taskSelect.append('<option value="">No tasks found for this project</option>');
                    taskErrorSpan.text('No tasks found for the selected project.');
                    taskSelect.prop('disabled', true);
                } else {
                    taskSelect.append('<option value="">Error loading tasks</option>');
                    taskErrorSpan.text(response.message || 'Could not load tasks.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                taskSelect.empty().append('<option value="">Error loading tasks</option>');
                taskErrorSpan.text('AJAX error fetching tasks: ' + textStatus);
                console.error("AJAX error get_events:", textStatus, errorThrown);
            }
        });
    }

    projectSelect.on('change', function() {
        const selectedProjectId = $(this).val();
        loadTasks(selectedProjectId);
    });

    taskSelect.on('change', function() {
        const selectedEventId = $(this).val();
        activeEventIdInput.val(selectedEventId);
        taskErrorSpan.text('');
        if (!selectedEventId) {
            taskErrorSpan.text('Please select a task.');
        }
    });

    if (imageFileInput) {
        imageFileInput.addEventListener('change', function(event) {
            if (imageFileErrorSpan) imageFileErrorSpan.textContent = '';
            const file = event.target.files[0];
            if (file) {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        if (uploadedImagePreview) uploadedImagePreview.src = e.target.result;
                        if (uploadedImagePreviewContainer) uploadedImagePreviewContainer.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                } else {
                    if (imageFileErrorSpan) imageFileErrorSpan.textContent = 'Invalid file type. Please select an image.';
                    if (uploadedImagePreview) uploadedImagePreview.src = "#";
                    if (uploadedImagePreviewContainer) uploadedImagePreviewContainer.style.display = 'none';
                    this.value = null;
                }
            } else {
                if (uploadedImagePreview) uploadedImagePreview.src = "#";
                if (uploadedImagePreviewContainer) uploadedImagePreviewContainer.style.display = 'none';
            }
        });
    }

    $('#uploadHtmlModal').on('show.bs.modal', function() {
        loadProjects();
        if (htmlFileInput) htmlFileInput.value = null;
        if (imageFileInput) imageFileInput.value = null;
        if (reportNameInput) reportNameInput.value = '';
        if (statusArea) {
            statusArea.innerHTML = '<p><em>File content will be processed...</em></p>';
            statusArea.style.display = 'none';
        }
        if (uploadedImagePreviewContainer) {
            uploadedImagePreviewContainer.style.display = 'none';
        }
        if (uploadedImagePreview) {
            uploadedImagePreview.src = "#";
        }
        if (mainErrorSpan) mainErrorSpan.textContent = '';
        if (htmlFileErrorSpan) htmlFileErrorSpan.textContent = '';
        if (imageFileErrorSpan) imageFileErrorSpan.textContent = '';
        if (reportNameErrorSpan) reportNameErrorSpan.textContent = '';
        projectErrorSpan.text('');
        taskErrorSpan.text('');
    });

    if (processButton) {
        processButton.addEventListener('click', function() {
            if (htmlFileErrorSpan) htmlFileErrorSpan.textContent = '';
            if (imageFileErrorSpan) imageFileErrorSpan.textContent = '';
            if (reportNameErrorSpan) reportNameErrorSpan.textContent = '';
            projectErrorSpan.text('');
            taskErrorSpan.text('');
            if (mainErrorSpan) mainErrorSpan.textContent = '';

            const selectedProjectId = projectSelect.val();
            const selectedEventId = taskSelect.val();
            activeEventIdInput.val(selectedEventId);

            let formValid = true;
            if (!selectedProjectId) {
                projectErrorSpan.text('Please select a project.');
                formValid = false;
            }
            if (!selectedEventId) {
                taskErrorSpan.text('Please select an event.');
                formValid = false;
            }
            if (!reportNameInput.value.trim()) {
                if (reportNameErrorSpan) reportNameErrorSpan.textContent = 'Please enter a name for the report.';
                formValid = false;
            }
            if (!htmlFileInput || !htmlFileInput.files || htmlFileInput.files.length === 0) {
                if (htmlFileErrorSpan) htmlFileErrorSpan.textContent = 'Please select an HTML report file.';
                formValid = false;
            }

            const imageFile = imageFileInput && imageFileInput.files.length > 0 ? imageFileInput.files[0] : null;
            if (imageFile && !imageFile.type.startsWith('image/')) {
                if (imageFileErrorSpan) imageFileErrorSpan.textContent = 'Invalid image file type. Please select a valid image.';
                formValid = false;
            }

            if (!formValid) {
                if (statusArea && !statusArea.innerHTML.includes('alert-danger')) {
                    statusArea.innerHTML = '<p class="text-danger">Please correct the errors above.</p>';
                    statusArea.style.display = 'block';
                }
                return;
            }

            const htmlFile = htmlFileInput.files[0];
            const reportName = reportNameInput.value.trim();

            if (htmlFile.type && (htmlFile.type.toLowerCase() !== "text/html")) {
                if (htmlFileErrorSpan) htmlFileErrorSpan.textContent = 'Invalid HTML file type. Please upload an HTML file.';
                if (statusArea) statusArea.style.display = 'block';
                return;
            }

            if (statusArea) {
                statusArea.innerHTML = '<p><em><i class="fas fa-spinner fa-spin"></i> Processing HTML file content...</em></p>';
                statusArea.style.display = 'block';
            }

            const reader = new FileReader();
            reader.onload = function(event) {
                const fileContent = event.target.result;
                let allTablesData = [];
                let extractedDataHtmlPreview = '';

                try {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(fileContent, 'text/html');
                    cleanMsoSpansInDocument(doc);

                    const tables = doc.getElementsByTagName('table');
                    extractedDataHtmlPreview = `<h4>Found ${tables.length} table(s) in HTML:</h4>`;
                    if (tables.length > 0) {
                        for (let i = 0; i < tables.length; i++) {
                            const table = tables[i];
                            extractedDataHtmlPreview += `<h5>Table Preview ${i + 1} (HTML Index ${i}):</h5>`;
                            const tableOuterHtml = table.outerHTML.replace(/\uFFFD/g, '');
                            if (tableOuterHtml.length > 1500) {
                                extractedDataHtmlPreview += `<div class="table-responsive" style="max-height: 150px; overflow-y:auto;">${tableOuterHtml}</div><p class="text-muted small">Preview truncated.</p>`;
                            } else {
                                extractedDataHtmlPreview += `<div class="table-responsive">${tableOuterHtml}</div>`;
                            }

                            const currentTableData = [];
                            let tableHeaders = [];
                            let sampleName = null;

                            const headers = table.querySelectorAll('thead th');
                            if (headers.length > 0) {
                                headers.forEach(th => tableHeaders.push(th.textContent.trim()));
                            }

                            // =================================================================
                            // --- CONDITIONAL FIX TO SKIP HEADER ROWS ---
                            // 1. Select ALL table rows (tr)
                            const rows = table.querySelectorAll('tr');

                            // 2. Loop through each row, getting the row's index
                            rows.forEach((row, index) => {

                                // 3. Check if we are on table 3 or higher AND if it's the first row.
                                // If so, it's a header we need to skip.
                                if (i >= 3 && index === 0) {
                                    return; // Skip this header row and move to the next.
                                }

                                // If we reach here, we are processing a valid data row.
                                const cells = row.querySelectorAll('td');
                                const rowDataForJson = {};
                                let hasDataInRow = false;

                                // This specific logic for table 3 can remain, as it now
                                // only operates on rows that have already been identified as data rows.
                                if (i === 3 && cells.length >= 7) {
                                    rowDataForJson['metric'] = cells[0] ? cells[0].innerText.replace(/\uFFFD/g, '').trim() : '';
                                    rowDataForJson['overall_value'] = cells[1] ? cells[1].innerText.replace(/\uFFFD/g, '').trim() : '';
                                    rowDataForJson['conf_interval_90'] = cells[2] ? cells[2].innerText.replace(/\uFFFD/g, '').trim() : '';
                                    rowDataForJson['sov_pass_fail'] = cells[3] ? cells[3].innerText.replace(/\uFFFD/g, '').trim() : '';
                                    rowDataForJson['min_value'] = cells[4] ? cells[4].innerText.replace(/\uFFFD/g, '').trim() : '';
                                    rowDataForJson['min_conf_interval_90'] = cells[5] ? cells[5].innerText.replace(/\uFFFD/g, '').trim() : '';
                                    rowDataForJson['mlv_pass_fail'] = cells[6] ? cells[6].innerText.replace(/\uFFFD/g, '').trim() : '';
                                    hasDataInRow = true;
                                } else {
                                    // Generic logic for all other data rows
                                    cells.forEach((cell, cellIndex) => {
                                        const cellText = cell.innerText.replace(/\uFFFD/g, '').trim();
                                        const key = (tableHeaders[cellIndex]) ? tableHeaders[cellIndex] : `column_${cellIndex + 1}`;
                                        rowDataForJson[key] = cellText;
                                        if (cellText !== '') hasDataInRow = true;
                                    });
                                }

                                if (hasDataInRow && Object.keys(rowDataForJson).length > 0) {
                                    currentTableData.push(rowDataForJson);
                                }
                            });
                               // --- END OF FIX ---
                               // =================================================================

                            if (currentTableData.length > 0) {
                                const tableObject = {
                                    tableIndex: i,
                                    headers: tableHeaders.length > 0 ? tableHeaders : determineHeadersFromData(currentTableData),
                                    data: currentTableData
                                };

                                if (i >= 4) { // Logic for sample tables (index 4 onwards)
                                    const caption = table.querySelector('caption');
                                    if (caption) {
                                        sampleName = caption.textContent.trim();
                                    } else {
                                        let prevSibling = table.previousElementSibling;
                                        let foundHeading = false;
                                        for (let k = 0; k < 3 && prevSibling; k++) {
                                            if (prevSibling.tagName && prevSibling.tagName.match(/^H[1-6]$/)) {
                                                sampleName = prevSibling.textContent.trim();
                                                foundHeading = true;
                                                break;
                                            }
                                            prevSibling = prevSibling.previousElementSibling;
                                        }
                                        if (!foundHeading) sampleName = `Sample (HTML Table ${i})`;
                                    }
                                    tableObject.sampleName = sampleName;
                                }
                                allTablesData.push(tableObject);
                            }
                        }
                        console.log("Extracted JSON Data for all tables:", allTablesData);
                    } else {
                        extractedDataHtmlPreview = '<p>No tables found in the uploaded HTML file.</p>';
                    }

                    if (statusArea) {
                        statusArea.innerHTML = extractedDataHtmlPreview;
                    }

                    sendDataToBackend(allTablesData, htmlFile, imageFile, reportName);

                } catch (e) {
                    console.error("Error processing HTML:", e);
                    if (mainErrorSpan) mainErrorSpan.textContent = 'Error processing HTML content.';
                    if (statusArea) statusArea.innerHTML = '<p class="alert alert-danger">Error processing HTML.</p>';
                }
            };
            reader.onerror = function() {
                console.error("Error reading HTML file:", reader.error);
                if (mainErrorSpan) mainErrorSpan.textContent = 'Error reading the selected HTML file.';
                if (statusArea) statusArea.innerHTML = '<p class="alert alert-danger">Could not read the HTML file.</p>';
            };
            reader.readAsText(htmlFile);
        });
    }

    function determineHeadersFromData(tableDataArray) {
        if (tableDataArray.length > 0 && typeof tableDataArray[0] === 'object' && tableDataArray[0] !== null) {
            return Object.keys(tableDataArray[0]);
        }
        return [];
    }

    function sendDataToBackend(extractedTablesData, htmlReportFile, uploadedImageFile, reportName) {
            const taskId = taskSelect.val();
          const projectId = projectSelect.val(); // 1. Get the Project ID
        
            if (!taskId || taskId.trim() === '' || !projectId || projectId.trim() === '') {
                console.error("Task ID or Project ID is missing. Cannot send data.");
                if (mainErrorSpan) mainErrorSpan.textContent = 'Task or Project ID not set. Please select a project and task.';
                return;
            }
    
            const processBtnEl = document.getElementById('processAndSaveBtn');
            const originalButtonText = processBtnEl.innerHTML;
            processBtnEl.disabled = true;
            processBtnEl.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
    
            const formData = new FormData();
            formData.append('taskId', taskId);
            formData.append('projectId', projectId); // 2. Append the Project ID
            formData.append('reportName', reportName);
            formData.append('allTablesData', JSON.stringify(extractedTablesData));
            formData.append('htmlFileToUpload', htmlReportFile);
    
            if (uploadedImageFile) {
                formData.append('imageFileToUpload', uploadedImageFile);
            }
            
        $.ajax({
            url: 'api/freport_data_handler.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                console.log('Server response:', response);
                let currentOutput = "";
                if (statusArea.innerHTML.includes("<h4>Found") || statusArea.innerHTML.includes("<p>No tables found")) {
                    currentOutput = statusArea.innerHTML;
                }

                if (response.success) {
                    statusArea.innerHTML = currentOutput + `<p class="alert alert-success mt-2"><strong>Success:</strong> ${response.message || 'Data saved!'} (Report ID: ${response.report_id || 'N/A'})</p>`;
                    if (response.image_saved_as) {
                        statusArea.innerHTML += `<p class="small text-muted">Image saved as: ${response.image_saved_as}</p>`;
                    } else if (uploadedImageFile) {
                        statusArea.innerHTML += `<p class="small text-info">Image was sent to server.</p>`;
                    }
                } else {
                    statusArea.innerHTML = currentOutput + `<p class="alert alert-danger mt-2"><strong>Server Error:</strong> ${response.message || 'Unknown error processing your request.'}</p>`;
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Error sending data to server:', textStatus, errorThrown, jqXHR.responseText);
                let currentOutput = "";
                if (statusArea.innerHTML.includes("<h4>Found") || statusArea.innerHTML.includes("<p>No tables found")) {
                    currentOutput = statusArea.innerHTML;
                }
                if (statusArea) {
                    statusArea.innerHTML = currentOutput + `<p class="alert alert-danger mt-2"><strong>AJAX Error:</strong> ${textStatus}: ${errorThrown}. Could not reach server or server error. Check console.</p>`;
                }
            },
            complete: function() {
                processBtnEl.disabled = false;
                processBtnEl.innerHTML = originalButtonText;
            }
        });
    }

    $('#uploadHtmlModal').on('hidden.bs.modal', function() {
        if (htmlFileInput) htmlFileInput.value = null;
        if (imageFileInput) imageFileInput.value = null;
        if (reportNameInput) reportNameInput.value = '';

        if (statusArea) {
            statusArea.innerHTML = '<p><em>File content will be processed...</em></p>';
            statusArea.style.display = 'none';
        }
        if (uploadedImagePreviewContainer) {
            uploadedImagePreviewContainer.style.display = 'none';
        }
        if (uploadedImagePreview) {
            uploadedImagePreview.src = "#";
        }

        projectSelect.empty().append('<option value="">Loading projects...</option>').prop('disabled', true);
        taskSelect.empty().append('<option value="">Select a project first</option>').prop('disabled', true);
        activeEventIdInput.val('');

        projectErrorSpan.text('');
        taskErrorSpan.text('');
        if (mainErrorSpan) mainErrorSpan.textContent = '';
        if (htmlFileErrorSpan) htmlFileErrorSpan.textContent = '';
        if (imageFileErrorSpan) imageFileErrorSpan.textContent = '';
        if (reportNameErrorSpan) reportNameErrorSpan.textContent = '';
    });
});