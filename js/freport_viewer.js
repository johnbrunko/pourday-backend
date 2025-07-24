$(document).ready(function() {
    // === Element Selectors ===
    const projectSelect = $('#projectSelection');
    const reportSelect = $('#reportSelection');
    const userSelect = $('#userSelection');
    const viewReportBtn = $('#viewReportBtn');
    const downloadPdfBtn = $('#downloadPdfBtn');
    const reportSelectionCard = $('#reportSelectionCard');
    const reportDisplayContainer = $('#reportDisplayContainer');
    const reportDisplayFrame = $('#reportDisplayFrame');
    const reportTitle = $('#reportTitle');
    const pdfStatus = $('#pdfStatus');

    // === Data Loading Functions ===
    function loadProjects() {
        $.getJSON('api/freport_actions.php?action=get_projects')
            .done(function(response) {
                projectSelect.empty().append('<option value="">-- Select Project --</option>');
                if (response.success && response.data.length > 0) {
                    response.data.forEach(project => {
                        projectSelect.append($('<option>', { value: project.id, text: project.job_name }));
                    });
                } else {
                    projectSelect.append('<option value="">No projects found</option>');
                }
            })
            .fail(function() {
                projectSelect.empty().append('<option value="">Error loading projects</option>');
            });
    }

    function loadUsers() {
        $.getJSON('api/freport_actions.php?action=get_users')
            .done(function(response) {
                userSelect.empty().append('<option value="">-- Report Uploader (Default) --</option>');
                if (response.success && response.data.length > 0) {
                    response.data.forEach(user => {
                        userSelect.append($('<option>', { value: user.id, text: `${user.first_name} ${user.last_name}` }));
                    });
                }
            })
            .fail(function() {
                userSelect.empty().append('<option value="">Error loading users</option>');
            });
    }

    // === Event Handlers ===
    projectSelect.on('change', function() {
        const projectId = $(this).val();
        reportSelect.prop('disabled', true).html('<option value="">-- Select a project first --</option>');
        viewReportBtn.prop('disabled', true);
        
        if (projectId) {
            reportSelect.prop('disabled', false).html('<option value="">Loading Reports...</option>');
            $.getJSON(`api/freport_actions.php?action=get_reports_for_project&project_id=${projectId}`)
                .done(function(response) {
                    reportSelect.empty().append('<option value="">-- Select Report --</option>');
                    if (response.success && response.data.length > 0) {
                        response.data.forEach(report => {
                            reportSelect.append($('<option>', { value: report.id, text: report.report_name }));
                        });
                        userSelect.prop('disabled', false); // Enable user selection
                    } else {
                        reportSelect.html('<option value="">No reports found</option>');
                    }
                })
                .fail(function() {
                    reportSelect.html('<option value="">Error loading reports</option>');
                });
        }
    });

    reportSelect.on('change', function() {
        viewReportBtn.prop('disabled', !$(this).val());
    });

    viewReportBtn.on('click', function() {
        const reportId = reportSelect.val();
        if (reportId) {
            fetchAndDisplayReport(reportId);
        }
    });

    downloadPdfBtn.on('click', function() {
        const reportId = reportSelect.val();
        const selectedUserId = userSelect.val();
        const btn = $(this);
        const originalHtml = btn.html();

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...');
        pdfStatus.html('<em class="text-muted">Preparing PDF download...</em>');

        $.ajax({
            url: 'api/generate_freport_pdf.php',
            type: 'GET',
            data: { report_id: reportId, selected_user_id: selectedUserId },
            xhrFields: { responseType: 'blob' }
        })
        .done(function(blob, status, xhr) {
            if (blob.type === 'application/pdf') {
                const disposition = xhr.getResponseHeader('Content-Disposition');
                let filename = `report-${reportId}.pdf`; // Fallback
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
                pdfStatus.html('<span class="text-success">Download started.</span>');
            } else {
                 pdfStatus.html('<span class="text-danger">Error: Invalid file received from server.</span>');
            }
        })
        .fail(function() {
            pdfStatus.html('<span class="text-danger">PDF generation failed.</span>');
        })
        .always(function() {
            btn.prop('disabled', false).html(originalHtml);
        });
    });

    // === Core Logic ===
    function fetchAndDisplayReport(reportId) {
        reportDisplayContainer.show();
        reportTitle.text('Loading Report...');
        const frameDoc = reportDisplayFrame[0].contentWindow.document;
        frameDoc.open();
        frameDoc.write('<body><p style="font-family: sans-serif; padding: 1rem;"><em>Loading...</em></p></body>');
        frameDoc.close();

        $.getJSON(`api/freport_actions.php?action=get_report_details&report_id=${reportId}`)
            .done(function(response) {
                if (response.success) {
                    reportTitle.text(`Report: ${response.data.report_details.report_name}`);
                    renderReportInFrame(response.data);
                } else {
                    frameDoc.open();
                    frameDoc.write(`<body><p><strong>Error:</strong> ${response.message}</p></body>`);
                    frameDoc.close();
                }
            })
            .fail(function() {
                frameDoc.open();
                frameDoc.write('<body><p><strong>Error:</strong> Could not fetch report details from the server.</p></body>');
                frameDoc.close();
            });
    }

    function renderReportInFrame(data) {
        const details = data.report_details;
        let bodyHtml = '';

        // --- Specifications Table ---
        if (details) {
            bodyHtml += `<h5>Report Specifications</h5><table class="table table-bordered table-sm"><tbody>
                <tr><th>Overall FF Spec</th><td>${details.spec_overall_ff}</td><th>Overall FL Spec</th><td>${details.spec_overall_fl}</td></tr>
                <tr><th>Minimum Local FF Spec</th><td>${details.spec_min_local_ff}</td><th>Minimum Local FL Spec</th><td>${details.spec_min_local_fl}</td></tr>
                <tr><th>Surface Area</th><td>${details.surface_area}</td><th>Readings Required</th><td>${details.min_readings_required}</td></tr>
                <tr><th>Readings Taken</th><td>${details.total_readings_taken}</td><th>Original Filename</th><td>${details.original_filename}</td></tr>
            </tbody></table>`;
        }
        
        // --- Composite F-Numbers Table ---
        if (data.composite_f_numbers && data.composite_f_numbers.length > 0) {
            bodyHtml += `<h5 class="mt-4">Composite F-Numbers</h5><table class="table table-bordered table-sm"><thead><tr>
                <th>Metric</th><th>Overall</th><th>90% Conf.</th><th>SOV Pass/Fail</th>
                <th>Min</th><th>90% Conf.</th><th>MLV Pass/Fail</th>
            </tr></thead><tbody>`;
            data.composite_f_numbers.forEach(row => {
                bodyHtml += `<tr>
                    <td>${row.metric}</td><td>${row.overall_value}</td><td>${row.conf_interval_90}</td><td>${row.sov_pass_fail}</td>
                    <td>${row.min_value}</td><td>${row.min_conf_interval_90}</td><td>${row.mlv_pass_fail}</td>
                </tr>`;
            });
            bodyHtml += `</tbody></table>`;
        }

        // --- Image ---
        if (details && details.image_full_path) {
             bodyHtml += `<h5 class="mt-4">Testing Map</h5><div class="text-center"><img src="${details.image_full_path}" class="img-fluid border rounded"></div>`;
        }

        // --- Sample F-Numbers Tables ---
        if (data.sample_f_numbers && data.sample_f_numbers.length > 0) {
            const samples = data.sample_f_numbers.reduce((acc, row) => {
                (acc[row.sample_name] = acc[row.sample_name] || []).push(row);
                return acc;
            }, {});
            
            bodyHtml += `<h5 class="mt-4">Sample F-Numbers</h5>`;
            for (const sampleName in samples) {
                bodyHtml += `<h6 class="mt-3"><em>${sampleName}</em></h6><table class="table table-bordered table-sm"><thead><tr>
                    <th>Metric</th><th>Overall Value</th><th>90% Conf.</th><th>MLV Pass/Fail</th>
                </tr></thead><tbody>`;
                samples[sampleName].forEach(row => {
                     bodyHtml += `<tr>
                        <td>${row.metric}</td><td>${row.overall_value}</td><td>${row.conf_interval_90}</td><td>${row.mlv_pass_fail}</td>
                    </tr>`;
                });
                bodyHtml += `</tbody></table>`;
            }
        }

        // --- Final HTML for Iframe ---
        const fullHtml = `
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Report Preview</title>
                <link href="https://cdn.jsdelivr.net/npm/@trimble-oss/modus-bootstrap@2.0.12/dist/css/modus-bootstrap.min.css" rel="stylesheet">
                <style> body { padding: 1rem; } </style>
            </head>
            <body>${bodyHtml || '<p>No data to display.</p>'}</body>
            </html>`;

        const frameDoc = reportDisplayFrame[0].contentWindow.document;
        frameDoc.open();
        frameDoc.write(fullHtml);
        frameDoc.close();
    }

    // --- Initial Load ---
    loadProjects();
    loadUsers();
});