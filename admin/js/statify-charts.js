/**
 * Advanced Stats — Chart.js initialization and rendering.
 */
(function () {
    'use strict';

    window.StatifyCharts = {
        visitsChart: null,
        devicesChart: null,
        visitorsChart: null,

        /**
         * Colors palette.
         */
        colors: {
            primary: '#6c63ff',
            primaryLight: 'rgba(108, 99, 255, 0.1)',
            success: '#10b981',
            successLight: 'rgba(16, 185, 129, 0.1)',
            warning: '#f59e0b',
            warningLight: 'rgba(245, 158, 11, 0.1)',
            danger: '#ef4444',
            info: '#3b82f6',
            purple: '#8b5cf6',
            pink: '#ec4899',
            teal: '#14b8a6',
            gray: '#6b7280',
        },

        /**
         * Default Chart.js configuration.
         */
        defaults: function () {
            Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
            Chart.defaults.font.size = 12;
            Chart.defaults.color = '#646970';
            Chart.defaults.plugins.legend.display = false;
            Chart.defaults.plugins.tooltip.backgroundColor = '#1d2327';
            Chart.defaults.plugins.tooltip.titleFont = { weight: '600' };
            Chart.defaults.plugins.tooltip.cornerRadius = 8;
            Chart.defaults.plugins.tooltip.padding = 10;
            Chart.defaults.elements.point.radius = 0;
            Chart.defaults.elements.point.hoverRadius = 5;
        },

        /**
         * Render the main visits line chart.
         */
        renderVisitsChart: function (data) {
            var ctx = document.getElementById('statify-visits-chart');
            if (!ctx) return;

            if (this.visitsChart) {
                this.visitsChart.destroy();
            }

            // Détecter le mode : horaire (today) ou journalier
            var isHourly = data.length > 0 && data[0].hasOwnProperty('hour');

            var labels, visitors, pageViews, sessions;

            if (isHourly) {
                // Mode horaire — 24 points, heures futures grisées
                labels    = data.map(function (d) { return d.hour + 'h'; });
                visitors  = data.map(function (d) { return d.future ? null : d.visitors; });
                pageViews = data.map(function (d) { return d.future ? null : d.page_views; });
                sessions  = data.map(function (d) { return d.future ? null : d.sessions; });
            } else {
                labels    = data.map(function (d) {
                    var parts = d.date.split('-');
                    var date  = new Date( parts[0], parts[1] - 1, parts[2] );
                    return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
                });
                visitors  = data.map(function (d) { return d.visitors; });
                pageViews = data.map(function (d) { return d.page_views; });
                sessions  = data.map(function (d) { return d.sessions; });
            }

            this.visitsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Visiteurs',
                            data: visitors,
                            borderColor: this.colors.primary,
                            backgroundColor: this.createGradient(ctx, this.colors.primary),
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2.5,
                            hidden: false,
                            spanGaps: false,
                        },
                        {
                            label: 'Pages vues',
                            data: pageViews,
                            borderColor: this.colors.success,
                            backgroundColor: this.createGradient(ctx, this.colors.success),
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2.5,
                            hidden: true,
                            spanGaps: false,
                        },
                        {
                            label: 'Sessions',
                            data: sessions,
                            borderColor: this.colors.warning,
                            backgroundColor: this.createGradient(ctx, this.colors.warning),
                            fill: true,
                            tension: 0.4,
                            borderWidth: 2.5,
                            hidden: true,
                            spanGaps: false,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { display: false },
                            ticks: {
                                maxTicksLimit: isHourly ? 24 : 15,
                                font: { size: 11 },
                            },
                        },
                        y: {
                            beginAtZero: true,
                            border: { display: false },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.04)',
                                drawBorder: false,
                            },
                            ticks: {
                                font: { size: 11 },
                                callback: function (value) {
                                    if (value >= 1000) return (value / 1000).toFixed(1) + 'k';
                                    return value;
                                },
                            },
                        },
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                title: function (items) {
                                    return isHourly
                                        ? 'Aujourd\'hui à ' + items[0].label
                                        : items[0].label;
                                },
                                label: function (context) {
                                    if (context.parsed.y === null) return null;
                                    return context.dataset.label + ': ' + context.parsed.y.toLocaleString('fr-FR');
                                },
                            },
                        },
                    },
                },
            });
        },

        /**
         * Render the devices doughnut chart.
         */
        renderDevicesChart: function (devices) {
            var ctx = document.getElementById('statify-devices-chart');
            if (!ctx) return;

            if (this.devicesChart) {
                this.devicesChart.destroy();
            }

            var labels = devices.map(function (d) {
                var names = { desktop: 'Desktop', mobile: 'Mobile', tablet: 'Tablette', unknown: 'Autre' };
                return names[d.device_type] || d.device_type;
            });
            var values = devices.map(function (d) { return parseInt(d.count, 10); });
            var colors = [this.colors.primary, this.colors.success, this.colors.warning, this.colors.gray];

            this.devicesChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: colors.slice(0, values.length),
                        borderWidth: 0,
                        hoverOffset: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                padding: 12,
                                usePointStyle: true,
                                pointStyleWidth: 10,
                                font: { size: 11 },
                            },
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    var total = context.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                    var pct = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.parsed.toLocaleString('fr-FR') + ' (' + pct + '%)';
                                },
                            },
                        },
                    },
                },
            });
        },

        /**
         * Render the visitors (new vs returning) doughnut chart.
         */
        renderVisitorsChart: function (data) {
            var ctx = document.getElementById('statify-visitors-chart');
            if (!ctx) return;

            if (this.visitorsChart) {
                this.visitorsChart.destroy();
            }

            var newV = parseInt(data.new_visitors || 0, 10);
            var retV = parseInt(data.returning_visitors || 0, 10);

            this.visitorsChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Nouveaux', 'Récurrents'],
                    datasets: [{
                        data: [newV, retV],
                        backgroundColor: [this.colors.primary, this.colors.teal],
                        borderWidth: 0,
                        hoverOffset: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '60%',
                    plugins: {
                        legend: { display: false },
                    },
                },
            });

            // Update legend
            var legend = document.getElementById('statify-visitors-legend');
            if (legend) {
                var total = newV + retV;
                var newPct = total > 0 ? ((newV / total) * 100).toFixed(1) : 0;
                var retPct = total > 0 ? ((retV / total) * 100).toFixed(1) : 0;
                legend.innerHTML =
                    '<span><span class="statify-legend-dot" style="background:' + this.colors.primary + '"></span> Nouveaux: ' + newV.toLocaleString('fr-FR') + ' (' + newPct + '%)</span>' +
                    '<span><span class="statify-legend-dot" style="background:' + this.colors.teal + '"></span> Récurrents: ' + retV.toLocaleString('fr-FR') + ' (' + retPct + '%)</span>';
            }
        },

        /**
         * Create a gradient fill for a chart.
         */
        createGradient: function (ctx, color) {
            var canvas = ctx.getContext ? ctx : ctx.canvas || ctx;
            if (!canvas.getContext) canvas = canvas;
            try {
                var context = (canvas.getContext ? canvas : document.getElementById(canvas.id)).getContext('2d');
                var gradient = context.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, color.replace(')', ', 0.2)').replace('rgb', 'rgba'));
                gradient.addColorStop(1, color.replace(')', ', 0)').replace('rgb', 'rgba'));
                return gradient;
            } catch (e) {
                return color + '1a'; // fallback
            }
        },

        /**
         * Toggle dataset visibility on the visits chart.
         */
        toggleDataset: function (datasetName) {
            if (!this.visitsChart) return;

            var map = { visitors: 0, page_views: 1, sessions: 2 };
            var idx = map[datasetName];
            if (idx === undefined) return;

            // Hide all, show selected
            this.visitsChart.data.datasets.forEach(function (ds, i) {
                ds.hidden = (i !== idx);
            });
            this.visitsChart.update();
        },
    };

    // Initialize defaults on load
    if (typeof Chart !== 'undefined') {
        StatifyCharts.defaults();
    }
})();
