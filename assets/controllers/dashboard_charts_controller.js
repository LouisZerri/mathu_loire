import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        labels: Array,
        fillRates: Array,
        adults: Number,
        children: Number,
        invitations: Number,
        cityLabels: Array,
        cityValues: Array,
        showLabels: Array,
        showRevenues: Array,
    };

    async connect() {
        const ChartModule = await import('chart.js/auto');
        const Chart = ChartModule.default;

        // Attendre que le layout soit complètement stabilisé
        if (document.readyState !== 'complete') {
            await new Promise(resolve => window.addEventListener('load', resolve, { once: true }));
        }
        await new Promise(resolve => requestAnimationFrame(resolve));

        const grayLight = 'rgba(156, 163, 175, 0.2)';

        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            animation: {
                duration: 1200,
                easing: 'easeOutQuart',
            },
            animations: {
                y: {
                    from: (ctx) => ctx.chart.scales.y.getPixelForValue(0),
                    duration: 1200,
                    easing: 'easeOutCubic',
                },
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                y: { grid: { color: grayLight }, ticks: { font: { size: 10 } } },
            },
        };

        // Remplissage
        new Chart(this.element.querySelector('#chartFillRate'), {
            type: 'bar',
            data: {
                labels: this.labelsValue,
                datasets: [{
                    data: this.fillRatesValue,
                    backgroundColor: this.fillRatesValue.map(function(r) {
                        return r >= 90 ? 'rgba(239,68,68,0.8)' : r >= 70 ? 'rgba(234,179,8,0.8)' : 'rgba(34,197,94,0.8)';
                    }),
                    borderRadius: 4,
                }],
            },
            options: Object.assign({}, defaultOptions, {
                scales: Object.assign({}, defaultOptions.scales, {
                    y: Object.assign({}, defaultOptions.scales.y, {
                        max: 100,
                        ticks: { font: { size: 10 }, callback: function(v) { return v + '%'; } }
                    })
                })
            }),
        });

        // Répartition spectateurs (donut)
        const donutCanvas = this.element.querySelector('#chartSpectators');
        if (donutCanvas) {
            const donutChart = new Chart(donutCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Adultes', 'Enfants', 'Invitations'],
                    datasets: [{
                        data: [0, 0, 0],
                        backgroundColor: ['rgb(17,24,39)', 'rgb(59,130,246)', 'rgb(249,115,22)'],
                        borderWidth: 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 1500, easing: 'easeInOutCubic' },
                    plugins: {
                        legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 16 } },
                    },
                },
            });

            setTimeout(() => {
                donutChart.data.datasets[0].data = [this.adultsValue, this.childrenValue, this.invitationsValue];
                donutChart.update();
            }, 50);
        }

        // Provenance par ville (horizontal bar)
        const cityCanvas = this.element.querySelector('#chartCities');
        if (cityCanvas && this.cityLabelsValue.length > 0) {
            const topLabels = this.cityLabelsValue.slice(0, 10);
            const topValues = this.cityValuesValue.slice(0, 10);
            const colors = [
                'rgb(17,24,39)', 'rgb(59,130,246)', 'rgb(34,197,94)', 'rgb(249,115,22)',
                'rgb(139,69,114)', 'rgb(234,179,8)', 'rgb(239,68,68)', 'rgb(16,185,129)',
                'rgb(99,102,241)', 'rgb(168,85,247)',
            ];

            new Chart(cityCanvas, {
                type: 'bar',
                data: {
                    labels: topLabels,
                    datasets: [{
                        data: topValues,
                        backgroundColor: topLabels.map((_, i) => colors[i % colors.length]),
                        borderRadius: 4,
                    }],
                },
                options: Object.assign({}, defaultOptions, {
                    indexAxis: 'y',
                    scales: {
                        x: { grid: { color: grayLight }, ticks: { font: { size: 10 } } },
                        y: { grid: { display: false }, ticks: { font: { size: 11 } } },
                    },
                }),
            });
        }

        // Recettes par spectacle
        const showRevenueCanvas = this.element.querySelector('#chartShowRevenue');
        if (showRevenueCanvas && this.showLabelsValue.length > 0) {
            new Chart(showRevenueCanvas, {
                type: 'bar',
                data: {
                    labels: this.showLabelsValue,
                    datasets: [{
                        data: this.showRevenuesValue,
                        backgroundColor: 'rgb(17,24,39)',
                        borderRadius: 4,
                    }],
                },
                options: Object.assign({}, defaultOptions, {
                    scales: Object.assign({}, defaultOptions.scales, {
                        y: Object.assign({}, defaultOptions.scales.y, {
                            ticks: { font: { size: 10 }, callback: function(v) { return v + ' €'; } }
                        })
                    })
                }),
            });
        }

    }
}
