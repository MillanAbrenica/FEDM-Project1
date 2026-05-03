(() => {
    const state = {
        chart: null,
    };

    function applyNavbarScrollState() {
        const nav = document.querySelector('[data-main-nav]');
        if (!nav) {
            return;
        }

        const setState = () => {
            nav.classList.toggle('is-scrolled', window.scrollY > 12);
        };

        window.addEventListener('scroll', setState, { passive: true });
        setState();
    }

    function initScrollReveal() {
        const revealTargets = document.querySelectorAll('.section-card, .step-item, .upload-zone');
        if (revealTargets.length === 0) {
            return;
        }

        revealTargets.forEach((target) => {
            target.classList.add('reveal-on-scroll');
        });

        const observer = new IntersectionObserver((entries, localObserver) => {
            entries.forEach((entry, index) => {
                if (!entry.isIntersecting) {
                    return;
                }

                const target = entry.target;
                target.style.transitionDelay = `${Math.min(index * 45, 220)}ms`;
                target.classList.add('is-visible');
                localObserver.unobserve(target);
            });
        }, {
            threshold: 0.12,
        });

        revealTargets.forEach((target) => {
            observer.observe(target);
        });
    }

    function initUploadZone() {
        const zone = document.querySelector('[data-upload-zone]');
        const input = document.querySelector('[data-upload-input]');
        if (!zone || !input) {
            return;
        }

        let status = zone.querySelector('.upload-zone-status');
        if (!status) {
            status = document.createElement('div');
            status.className = 'upload-zone-status';
            status.textContent = 'No file selected';
            zone.appendChild(status);
        }

        const syncUploadStatus = () => {
            const hasFile = !!(input.files && input.files.length > 0);
            zone.classList.toggle('has-file', hasFile);

            if (!hasFile) {
                status.textContent = 'No file selected';
                return;
            }

            const file = input.files[0];
            status.textContent = `Selected: ${file.name}`;
        };

        zone.addEventListener('click', (event) => {
            if (event.target === input || input.contains(event.target)) {
                return;
            }

            input.click();
        });

        input.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        ['dragenter', 'dragover'].forEach((eventName) => {
            zone.addEventListener(eventName, (event) => {
                event.preventDefault();
                zone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            zone.addEventListener(eventName, (event) => {
                event.preventDefault();
                zone.classList.remove('dragover');
            });
        });

        zone.addEventListener('drop', (event) => {
            if (!event.dataTransfer || !event.dataTransfer.files || event.dataTransfer.files.length === 0) {
                return;
            }
            input.files = event.dataTransfer.files;
            syncUploadStatus();
        });

        input.addEventListener('change', syncUploadStatus);
        syncUploadStatus();
    }

    function getCheckedValue(containerSelector) {
        const checked = document.querySelector(`${containerSelector} input[type="checkbox"]:checked`);
        return checked ? checked.value : '';
    }

    function enforceSingleCheckboxSelection(containerSelector) {
        const boxes = document.querySelectorAll(`${containerSelector} input[type="checkbox"]`);
        boxes.forEach((box) => {
            box.addEventListener('change', () => {
                if (!box.checked) {
                    return;
                }
                boxes.forEach((other) => {
                    if (other !== box) {
                        other.checked = false;
                    }
                });
                updateChart();
            });
        });
    }

    async function updateChart() {
        const canvas = document.getElementById('mainChart');
        const chartTypeInput = document.querySelector('input[name="chart_type"]:checked');
        if (!canvas || !chartTypeInput) {
            return;
        }

        const xColumn = getCheckedValue('[data-x-columns]');
        const yColumn = getCheckedValue('[data-y-columns]');
        const chartType = chartTypeInput.value;

        if (!xColumn) {
            return;
        }

        const formData = new FormData();
        formData.append('x_column', xColumn);
        formData.append('y_column', yColumn);
        formData.append('chart_type', chartType);

        try {
            const response = await fetch('chart_data.php', {
                method: 'POST',
                body: formData,
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const configType = chartType === 'pie' ? 'pie' : (chartType === 'line' ? 'line' : 'bar');

            if (state.chart) {
                state.chart.destroy();
            }

            state.chart = new Chart(canvas, {
                type: configType,
                data: payload,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 550,
                        easing: 'easeOutQuart',
                    },
                    plugins: {
                        legend: {
                            display: true,
                        },
                    },
                    scales: configType === 'pie' ? {} : {
                        y: {
                            beginAtZero: true,
                        },
                    },
                },
            });
        } catch (_error) {
            // Keep the UI responsive even when chart data is unavailable.
        }
    }

    function initDashboardChart() {
        const chartContainer = document.getElementById('mainChart');
        if (!chartContainer) {
            return;
        }

        enforceSingleCheckboxSelection('[data-x-columns]');
        enforceSingleCheckboxSelection('[data-y-columns]');

        document.querySelectorAll('input[name="chart_type"]').forEach((radio) => {
            radio.addEventListener('change', updateChart);
        });

        updateChart();
    }

    document.addEventListener('DOMContentLoaded', () => {
        applyNavbarScrollState();
        initScrollReveal();
        initUploadZone();
        initDashboardChart();
    });
})();