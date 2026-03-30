/* ============================================================
   app.js — WVR Income & Expenses Tracker
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    // ── Sidebar Toggle ────────────────────────────────────────
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');

    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
            } else {
                sidebar.classList.toggle('collapsed');
            }
        });
    }

    // Close mobile sidebar on outside click
    document.addEventListener('click', function (e) {
        if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains('mobile-open')) {
            if (!sidebar.contains(e.target) && e.target !== toggle) {
                sidebar.classList.remove('mobile-open');
            }
        }
    });

    // ── Flatpickr date pickers ────────────────────────────────
    document.querySelectorAll('.datepicker').forEach(el => {
        flatpickr(el, {
            dateFormat: 'Y-m-d',
            allowInput: true
        });
    });

    document.querySelectorAll('.datepicker-range').forEach(el => {
        flatpickr(el, {
            dateFormat: 'Y-m-d',
            mode: 'range',
            allowInput: true
        });
    });

    // ── DataTables init ───────────────────────────────────────
    const dts = document.querySelectorAll('.datatable');
    dts.forEach(table => {
        if ($.fn.DataTable.isDataTable(table)) return;
        $(table).DataTable({
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'desc']],
            language: {
                search: '',
                searchPlaceholder: 'Search records...',
                lengthMenu: 'Show _MENU_ entries',
                info: 'Showing _START_–_END_ of _TOTAL_ records',
                paginate: { previous: '‹', next: '›' }
            },
            dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>',
            responsive: true,
        });
    });

    // ── Delete confirmation ───────────────────────────────────
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function (e) {
            if (!confirm('Are you sure you want to delete this record? This cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    // ── Auto-dismiss alerts ───────────────────────────────────
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        });
    }, 5000);

    // ── Amount formatting on blur ─────────────────────────────
    document.querySelectorAll('input[name="amount"]').forEach(input => {
        input.addEventListener('blur', function () {
            const val = parseFloat(this.value);
            if (!isNaN(val)) this.value = val.toFixed(2);
        });
    });

    // ── Week auto-fill ────────────────────────────────────────
    const dateInput = document.getElementById('date');
    const weekInput = document.getElementById('week_number');
    const monthInput = document.getElementById('month_display');

    if (dateInput && weekInput) {
        dateInput.addEventListener('change', function () {
            const d = new Date(this.value);
            if (!isNaN(d)) {
                // ISO week number
                const jan1 = new Date(d.getFullYear(), 0, 1);
                const week = Math.ceil(((d - jan1) / 86400000 + jan1.getDay() + 1) / 7);
                weekInput.value = week;
                if (monthInput) {
                    monthInput.value = d.toLocaleString('default', { month: 'long' });
                }
            }
        });
    }

    // ── Chart helpers ─────────────────────────────────────────
    window.buildBarChart = function (canvasId, labels, incomeData, expenseData) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Income',
                        data: incomeData,
                        backgroundColor: 'rgba(34,197,94,0.75)',
                        borderRadius: 5,
                    },
                    {
                        label: 'Expenses',
                        data: expenseData,
                        backgroundColor: 'rgba(239,68,68,0.75)',
                        borderRadius: 5,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: ctx => '₱' + ctx.raw.toLocaleString('en', { minimumFractionDigits: 2 })
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: v => '₱' + v.toLocaleString()
                        },
                        grid: { color: '#f1f3f5' }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    };

    window.buildDoughnutChart = function (canvasId, labels, data, colors) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { font: { size: 12 } } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ctx.label + ': ₱' + ctx.raw.toLocaleString('en', { minimumFractionDigits: 2 })
                        }
                    }
                },
                cutout: '65%'
            }
        });
    };
});
