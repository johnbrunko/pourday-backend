// File: js/weather_report.js

$(document).ready(function() {
    // --- Element Selectors ---
    const projectSelect = $('#projectSelection');
    const taskSelect = $('#taskSelection');
    const generateBtn = $('#generateReportBtn');
    const downloadBtn = $('#downloadChartsBtn');
    const reportContentEl = $('#report-content');
    const reportInfoEl = $('#report-info');
    
    // To store chart instances and report names
    let chartInstances = {};
    let reportProjectName = '';
    let reportTaskName = '';

    /**
     * Creates a URL-friendly slug from a string.
     */
    function slugify(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-')       // Replace spaces with -
            .replace(/[^\w\-]+/g, '')   // Remove all non-word chars
            .replace(/\-\-+/g, '-')     // Replace multiple - with single -
            .replace(/^-+/, '')         // Trim - from start of text
            .replace(/-+$/, '');        // Trim - from end of text
    }

    /**
     * Destroys any existing chart instances to prevent memory leaks.
     */
    function destroyCharts() {
        Object.values(chartInstances).forEach(chart => {
            if (chart) {
                chart.destroy();
            }
        });
        chartInstances = {};
    }

    /**
     * A generic function to create a styled line chart with Chart.js
     */
    function createLineChart(canvasId, labels, dataset, yAxisTitle, chartTitle) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        const backgroundPlugin = {
            id: 'background',
            beforeDraw: (chart, args, options) => {
                const { ctx } = chart;
                ctx.save();
                ctx.globalCompositeOperation = 'destination-over';
                ctx.fillStyle = options.color || '#ffffff';
                ctx.fillRect(0, 0, chart.width, chart.height);
                ctx.restore();
            }
        };

        return new Chart(ctx, {
            type: 'line',
            data: { labels: labels, datasets: [dataset] },
            options: {
                responsive: true,
                devicePixelRatio: 2, // Renders chart at 2x resolution for sharpness
                maintainAspectRatio: true,
                aspectRatio: 16 / 9,
                animation: false,
                scales: {
                    y: { beginAtZero: false, title: { display: true, text: yAxisTitle, font: { size: 14, weight: 'bold', family: "'Open Sans', sans-serif" } } },
                    x: { title: { display: true, text: 'Time', font: { size: 14, weight: 'bold', family: "'Open Sans', sans-serif" } } }
                },
                layout: {
                    padding: 20
                },
                plugins: {
                    legend: { display: false },
                    background: {
                        color: 'white'
                    },
                    title: {
                        display: true,
                        text: chartTitle,
                        font: {
                            size: 16,
                            family: "'Open Sans', sans-serif"
                        },
                        padding: {
                            top: 10,
                            bottom: 10
                        }
                    }
                }
            },
            plugins: [backgroundPlugin]
        });
    }
    
    /**
     * Clears all chart areas and report info.
     */
    function clearReport() {
        reportInfoEl.empty();
        downloadBtn.hide();
        destroyCharts();
    }

    /**
     * Downloads all generated charts as PNG files.
     */
    function downloadAllCharts() {
        if (!reportProjectName || !reportTaskName) return;

        const baseFilename = `${slugify(reportProjectName)}_${slugify(reportTaskName)}`;

        for (const key in chartInstances) {
            if (Object.hasOwnProperty.call(chartInstances, key)) {
                const chart = chartInstances[key];
                const filename = `${baseFilename}_${key}.png`;
                
                const link = document.createElement('a');
                link.href = chart.canvas.toDataURL('image/png');
                link.download = filename;
                link.click();
            }
        }
    }

    /**
     * Fetches data for a specific task and builds the report charts.
     */
    function buildReport(taskId) {
        if (!taskId) return;
        
        clearReport(); 
        reportContentEl.show();
        reportInfoEl.html('<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>');
        
        $.ajax({
            url: 'api/weather_report_data_handler.php',
            type: 'GET',
            data: { task_id: taskId },
            dataType: 'json',
            success: function(response) {
                if (!response.success || !response.weatherData || response.weatherData.length === 0) {
                    reportInfoEl.html(`<div class="alert alert-warning">${response.message || 'No weather data has been uploaded for this task yet.'}</div>`);
                    return;
                }

                const { project, task, weatherData } = response;
                
                reportProjectName = project.job_name;
                reportTaskName = task.title;

                const infoHtml = `
                    <h5>Project: ${project.job_name} (${project.job_number || 'N/A'})</h5>
                    <p class="mb-1"><strong>Location:</strong> ${project.location}</p>
                    <p class="mb-1"><strong>Task:</strong> ${task.title}</p>
                    <p><strong>Scheduled Date:</strong> ${task.scheduled_date}</p>
                `;
                reportInfoEl.html(infoHtml);

                const labels = weatherData.map(d => new Date(d.record_datetime).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }));
                const tempData = weatherData.map(d => d.temperature ? parseFloat(d.temperature) : null);
                const humidityData = weatherData.map(d => d.humidity ? parseFloat(d.humidity) : null);
                const windData = weatherData.map(d => d.windspeed ? parseFloat(d.windspeed) : null);
                const evapData = weatherData.map(d => d.evap_rate ? parseFloat(d.evap_rate) : null);
                
                destroyCharts(); 

                chartInstances.temperature = createLineChart('tempChartCanvas', labels, { label: 'Air Temp', data: tempData, borderColor: '#dc3545', backgroundColor: 'rgba(220, 53, 69, 0.2)', fill: true, tension: 0 }, 'Temperature (°F)', 'Air Temperature (°F)');
                chartInstances.humidity = createLineChart('humidityChartCanvas', labels, { label: 'Humidity', data: humidityData, borderColor: '#0d6efd', backgroundColor: 'rgba(13, 110, 253, 0.2)', fill: true, tension: 0 }, 'Humidity (%)', 'Relative Humidity (%)');
                chartInstances.wind = createLineChart('windChartCanvas', labels, { label: 'Wind Speed', data: windData, borderColor: '#198754', backgroundColor: 'rgba(25, 135, 84, 0.2)', fill: true, tension: 0 }, 'Wind Speed (mph)', 'Wind Speed (mph)');
                chartInstances.evaporation = createLineChart('evapChartCanvas', labels, { label: 'Evap. Rate', data: evapData, borderColor: '#6f42c1', backgroundColor: 'rgba(111, 66, 193, 0.2)', fill: true, tension: 0 }, 'Evaporation Rate (lb/ft²/h)', 'Evaporation Rate (lb/ft²/h)');
            
                downloadBtn.show();
            },
            error: function(xhr, status, error) {
                reportInfoEl.html('<div class="alert alert-danger">An unexpected server error occurred. Check the console for details.</div>');
            }
        });
    }

    // --- Event Listeners ---
    projectSelect.on('change', function() {
        const projectId = $(this).val();
        taskSelect.html('<option value="">Loading...</option>').prop('disabled', true);
        generateBtn.prop('disabled', true);
        reportContentEl.hide();
        clearReport();

        if (!projectId) {
            taskSelect.html('<option value="">-- Select a Project First --</option>');
            return;
        }

        $.ajax({
            url: 'api/weather_form_helper.php?action=get_tasks_with_data',
            type: 'GET',
            data: { project_id: projectId },
            dataType: 'json',
            success: function(response) {
                taskSelect.empty().append('<option value="">-- Select a Task --</option>');
                if (response.success && response.data.length > 0) {
                    response.data.forEach(task => {
                        taskSelect.append($('<option>', { value: task.id, text: task.title }));
                    });
                    taskSelect.prop('disabled', false);
                } else {
                    taskSelect.html('<option value="">No tasks with weather data found</option>');
                }
            }
        });
    });

    taskSelect.on('change', function() {
        generateBtn.prop('disabled', !$(this).val());
        reportContentEl.hide();
        clearReport();
    });

    generateBtn.on('click', function() {
        const taskId = taskSelect.val();
        buildReport(taskId);
    });

    downloadBtn.on('click', downloadAllCharts);
});

