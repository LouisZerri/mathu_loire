import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        labels: Array,
        fillRates: Array,
        revenues: Array,
        places: Array,
        capacities: Array,
        adults: Number,
        children: Number,
        invitations: Number,
    };

    async connect() {
        const { Chart, BarController, DoughnutController, BarElement, ArcElement, CategoryScale, LinearScale, Tooltip, Legend } = await import('chart.js');
        Chart.register(BarController, DoughnutController, BarElement, ArcElement, CategoryScale, LinearScale, Tooltip, Legend);

        const grayLight = 'rgba(156, 163, 175, 0.2)';

        const defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
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

        // Recettes
        new Chart(this.element.querySelector('#chartRevenue'), {
            type: 'bar',
            data: {
                labels: this.labelsValue,
                datasets: [{
                    data: this.revenuesValue,
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

        // Répartition spectateurs
        new Chart(this.element.querySelector('#chartSpectators'), {
            type: 'doughnut',
            data: {
                labels: ['Adultes', 'Enfants', 'Invitations'],
                datasets: [{
                    data: [this.adultsValue, this.childrenValue, this.invitationsValue],
                    backgroundColor: ['rgb(17,24,39)', 'rgb(59,130,246)', 'rgb(249,115,22)'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 16 } },
                },
            },
        });

        // Places vs capacité
        new Chart(this.element.querySelector('#chartPlaces'), {
            type: 'bar',
            data: {
                labels: this.labelsValue,
                datasets: [
                    { label: 'Réservées', data: this.placesValue, backgroundColor: 'rgb(34,197,94)', borderRadius: 4 },
                    { label: 'Capacité', data: this.capacitiesValue, backgroundColor: grayLight, borderRadius: 4 },
                ],
            },
            options: Object.assign({}, defaultOptions, {
                plugins: {
                    legend: { display: true, position: 'top', labels: { font: { size: 11 }, boxWidth: 12, padding: 16 } }
                },
            }),
        });
    }
}
