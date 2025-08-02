$(document).ready(function() {
    // === Element Selectors ===
    const projectSelect = $('#projectSelection');
    const reportSelect = $('#reportSelection');
    const userSelect = $('#userSelection');
    const viewReportBtn = $('#viewReportBtn');
    const downloadPdfBtn = $('#downloadPdfBtn');
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
                        userSelect.prop('disabled', false);
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
                let filename = `report-${reportId}.pdf`;
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

        // The API call now needs to fetch all data, including what's needed for the intro.
        // We assume freport_actions.php joins projects and contacts to get this info.
        $.getJSON(`api/freport_actions.php?action=get_report_details&report_id=${reportId}`)
            .done(function(response) {
                if (response.success) {
                    reportTitle.text(`Report Preview: ${response.data.report_details.report_name}`);
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
        let bodyHtml = '<main>';

        // --- NEW: Build the Cover Page (Intro) ---
        let pourStatus = 'met';
        if (data.composite_f_numbers && data.composite_f_numbers.length > 0) {
            for (const row of data.composite_f_numbers) {
                if ((row.sov_pass_fail || '').toLowerCase() === 'fail' || (row.mlv_pass_fail || '').toLowerCase() === 'fail') {
                    pourStatus = 'did not meet';
                    break;
                }
            }
        }
        
        // MODIFIED: Use the task's 'scheduled' date if available, otherwise fallback to the upload timestamp.
        const dateToUse = details.scheduled || details.upload_timestamp;
        const pourDate = new Date(dateToUse).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        
        let attnLine = 'Attn: ';
        const attnParts = [];
        if(details.contact_title) attnParts.push(details.contact_title);
        const contactName = `${details.contact_first_name || ''} ${details.contact_last_name || ''}`.trim();
        if(contactName) attnParts.push(contactName);
        attnLine += attnParts.join(', ');

        const addressLine = [details.city, details.state, details.zip].filter(Boolean).join(', ');

        bodyHtml += `<div class="pdf-section intro-text">
            <h3 class="report-main-title">FF/FL ANALYSIS (ASTM E1155) REPORT</h3>
            <p style="line-height: 1.6; margin-bottom: 20px;">
                <strong>${attnLine}</strong><br>
                <strong>${details.customer_name || ''}</strong><br>
                <strong>Regarding:</strong><br>
                <strong>${details.job_name || ''}</strong><br>
                <strong>${addressLine}</strong>
            </p>
            <p>As requested, FST was present to observe and provide VS309 Oversite services during the placement of ${details.report_name} on ${pourDate}. The specified F<sub>F</sub> and F<sub>L</sub> values for this pour were ${details.spec_overall_ff} and ${details.spec_overall_fl}. The pour ${pourStatus} these values. Individual readings were also then compared against minimum local values to "establish the minimum surface quality that would be acceptable anywhere on any of the concrete placements".</p>
            <h3>Scope</h3>
            <p>The testing performed by FST adheres to ASTM 1155 for individual test sections to provide a record of placement performance for an individual pour. ACI 117 states that " the specified overall values...are the F<sub>F</sub> and F<sub>L</sub> to which the completed project floor surface must conform viewed in its entirety". A combined overall F<sub>F</sub>/F<sub>L</sub> report may not be produced until the slab is completed.</p>
            <p>Please note that F<sub>L</sub> values are typically not evaluated for elevated decks due to the inherent sag and deflection associated with these structures. In those cases, the results here are shared for informational purposes and may be consulted to provide guidance for shoring plans for subsequent pours.</p>
            <h3>Testing Procedures</h3>
            <p>All tests were performed using 3D Laser Scanning as a data collection method per ASTM E1155. All tested surfaces were then analyzed before test completion to ensure that penetrations, walls, joints, forms and columns were provided two feet of clearance. A map of the samples taken and individual readings is included in this report for reference.</p>
            <h3>F<sub>F</sub>/F<sub>L</sub> and Flooring Tolerances</h3>
            <p>The nature of F<sub>F</sub>/F<sub>L</sub> testing and its results does not immediately translate to manufacturer flooring tolerances. F<sub>F</sub> numbers are greatly affected by the number of variances present in a sample run, meaning that an 1/8" in 10 ft can still produce a low result if that variance occurs repeatedly. VS309 Oversite allows for smoother corrections, typically resulting in a finished floor that exceeds manufacturer specifications.</p>
            <p>The following comparisons are then provided for reference only:</p>
            <ul>
                <li>F<sub>F</sub> 25-35 is typically equal to approximately 1/4" in 10'</li>
                <li>F<sub>F</sub> 50-60 is typically equal to approximately 1/8" in 10'</li>
                <li>F<sub>F</sub> 100 is typically equal to approximately 1/16" in 10'</li>
            </ul>
            <p>This report was prepared by ${details.uploader_first_name || ''} ${details.uploader_last_name || ''}</p>
            <p style="margin-top: 20px;"><strong>Measurement Units:</strong> U.S. Survey Feet</p>
        </div>`;

        // --- Contract Specifications ---
        bodyHtml += `<h4>Contract Specifications</h4>
        <table class="spec-table-container">
            <tr>
                <td>
                    <table class="spec-table">
                        <thead><tr><th class="table-title" colspan="2">Specified FF Value</th></tr></thead>
                        <tbody>
                            <tr><td>Overall</td><td>${details.spec_overall_ff || 'N/A'}</td></tr>
                            <tr><td>Minimum Local</td><td>${details.spec_min_local_ff || 'N/A'}</td></tr>
                        </tbody>
                    </table>
                </td>
                <td>
                    <table class="spec-table">
                        <thead><tr><th class="table-title" colspan="2">Specified FL Values</th></tr></thead>
                        <tbody>
                            <tr><td>Overall</td><td>${details.spec_overall_fl || 'N/A'}</td></tr>
                            <tr><td>Minimum Local</td><td>${details.spec_min_local_fl || 'N/A'}</td></tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>`;

        // --- Test Section Detail ---
        bodyHtml += `<table class="spec-table">
            <thead><tr><th class="table-title" colspan="2">Test Section Detail</th></tr></thead>
            <tbody>
                <tr><td>Surface Area</td><td>${details.surface_area || 'N/A'}</td></tr>
                <tr><td>Minimum Readings Required</td><td>${details.min_readings_required || 'N/A'}</td></tr>
                <tr><td>Total Number of Readings</td><td>${details.total_readings_taken || 'N/A'}</td></tr>
            </tbody>
        </table>`;
        
        // --- Composite F-Numbers Table ---
        if (data.composite_f_numbers && data.composite_f_numbers.length > 0) {
            bodyHtml += `<h4 class="mt-4">Composite F-Numbers</h4><table><thead><tr>
                <th>Metric</th><th>Overall</th><th>90% Conf.</th><th>SOV P/F</th>
                <th>Min</th><th>90% Conf.</th><th>MLV P/F</th>
            </tr></thead><tbody>`;
            data.composite_f_numbers.forEach(row => {
                bodyHtml += `<tr>
                    <td>${row.metric || ''}</td><td>${row.overall_value || ''}</td><td>${row.conf_interval_90 || ''}</td>
                    <td class="${(row.sov_pass_fail || '').toLowerCase()}">${row.sov_pass_fail || ''}</td>
                    <td>${row.min_value || ''}</td><td>${row.min_conf_interval_90 || ''}</td>
                    <td class="${(row.mlv_pass_fail || '').toLowerCase()}">${row.mlv_pass_fail || ''}</td>
                </tr>`;
            });
            bodyHtml += `</tbody></table>`;
        }

        // --- Image ---
        if (details && details.image_full_path) {
             bodyHtml += `<div class="image-container">
                 <h4 class="mt-4">Testing Map</h4>
                 <div class="text-center"><img src="${details.image_full_path}" class="report-image"></div>
               </div>`;
        }

        // --- Sample F-Numbers Tables ---
        if (data.sample_f_numbers && data.sample_f_numbers.length > 0) {
            const samples = data.sample_f_numbers.reduce((acc, row) => {
                (acc[row.sample_name] = acc[row.sample_name] || []).push(row);
                return acc;
            }, {});
            
            bodyHtml += `<h4 class="mt-4">Sample F-Numbers</h4>`;
            for (const sampleName in samples) {
                const cleanSampleName = sampleName.replace(/Sample \(HTML Table (\d+)\)/, 'Sample $1');
                bodyHtml += `<div class="sample-section">
                    <h5>${cleanSampleName}</h5>
                    <table class="sample-table">
                        <colgroup><col class="metric"><col class="value"><col class="conf"><col class="passfail"></colgroup>
                        <thead><tr><th>Metric</th><th>Overall Value</th><th>90% Conf.</th><th>MLV P/F</th></tr></thead>
                        <tbody>`;
                samples[sampleName].forEach(row => {
                    bodyHtml += `<tr>
                        <td>${row.metric || ''}</td><td>${row.overall_value || ''}</td><td>${row.conf_interval_90 || ''}</td>
                        <td class="${(row.mlv_pass_fail || '').toLowerCase()}">${row.mlv_pass_fail || ''}</td>
                    </tr>`;
                });
                bodyHtml += `</tbody></table></div>`;
            }
        }

        bodyHtml += '</main>';

        // --- Final HTML for Iframe ---
        const fullHtml = `
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <title>Report Preview</title>
                <style>
                    body { margin: 0; padding: 1.5rem; background-color: #fff; } 
                    main { max-width: 800px; margin: 0 auto; }
                    ${pdfReportCss}
                </style>
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