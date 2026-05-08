(function() {
  'use strict';

  // Constants
  const CANVAS_ID = 'vizCanvas';
  const PALETTE_SELECTOR = '.palette-item';
  const WIDGET_SELECTOR = '.viz-widget';
  const TAB_BUTTON_SELECTOR = '.tab-button';
  const TAB_CONTENT_SELECTOR = '.tab-content';

  // State
  let widgets = new Map(); // id -> { type, xCol, yCol, chart }
  let draggedChart = null;
  let widgetCounter = 0;

  // ===== PALETTE DRAG SETUP =====
  function initPaletteDrag() {
    document.querySelectorAll(PALETTE_SELECTOR).forEach(item => {
      item.addEventListener('dragstart', e => {
        draggedChart = e.currentTarget.dataset.chartType;
        e.currentTarget.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'copy';
      });

      item.addEventListener('dragend', e => {
        e.currentTarget.classList.remove('dragging');
        draggedChart = null;
      });
    });
  }

  // ===== CANVAS DROP SETUP =====
  function initCanvasDrop() {
    const canvas = document.getElementById(CANVAS_ID);
    if (!canvas) return;

    canvas.addEventListener('dragover', e => {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'copy';
      canvas.classList.add('droppable');
    });

    canvas.addEventListener('dragleave', e => {
      if (e.target === canvas) {
        canvas.classList.remove('droppable');
      }
    });

    canvas.addEventListener('drop', e => {
      e.preventDefault();
      canvas.classList.remove('droppable');

      if (!draggedChart) return;

      const rect = canvas.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;

      // Don't create widget if dropping on placeholder
      if (e.target.closest('.canvas-placeholder')) {
        createWidget(draggedChart, x, y);
      } else {
        createWidget(draggedChart, x, y);
      }
    });
  }

  // ===== WIDGET CREATION =====
  function createWidget(chartType, x, y) {
    const widgetId = `widget-${++widgetCounter}`;
    const xCol = window.RACO_COLUMNS[0] || '';
    const yCol = window.RACO_NUMERIC_COLS[0] || '';

    // Create widget element
    const widget = document.createElement('div');
    widget.className = 'viz-widget';
    widget.id = widgetId;
    widget.style.left = Math.max(0, x - 180) + 'px';
    widget.style.top = Math.max(0, y - 80) + 'px';

    widget.innerHTML = `
      <div class="viz-widget-header">
        <div class="viz-widget-title">${chartType.charAt(0).toUpperCase() + chartType.slice(1)} Chart</div>
        <button class="viz-widget-close" data-widget-id="${widgetId}">×</button>
      </div>
      <div class="viz-widget-controls">
        <div class="viz-control-group">
          <label>X Axis</label>
          <select class="widget-xaxis" data-widget-id="${widgetId}">
            ${window.RACO_COLUMNS.map(col => `<option value="${col}">${col}</option>`).join('')}
          </select>
        </div>
        <div class="viz-control-group">
          <label>Y Axis</label>
          <select class="widget-yaxis" data-widget-id="${widgetId}">
            ${window.RACO_NUMERIC_COLS.map(col => `<option value="${col}">${col}</option>`).join('')}
          </select>
        </div>
        <button class="viz-btn" data-action="render" data-widget-id="${widgetId}">Render</button>
        <button class="viz-btn viz-btn-secondary" data-action="png" data-widget-id="${widgetId}">PNG</button>
      </div>
      <div class="viz-widget-body">
        <canvas></canvas>
      </div>
    `;

    // Add to canvas
    document.getElementById(CANVAS_ID).appendChild(widget);

    // Remove placeholder if first widget
    const placeholder = document.querySelector('.canvas-placeholder');
    if (placeholder) placeholder.style.display = 'none';

    // Store widget state
    widgets.set(widgetId, {
      type: chartType,
      xCol,
      yCol,
      chart: null
    });

    // Setup event listeners
    setupWidgetEvents(widget, widgetId);

    // Render chart immediately
    renderChart(widgetId);

    // Update visualization tab
    updateVizSummary();
  }

  // ===== WIDGET EVENTS =====
  function setupWidgetEvents(widget, widgetId) {
    const header = widget.querySelector('.viz-widget-header');
    const closeBtn = widget.querySelector('.viz-widget-close');
    const renderBtn = widget.querySelector('[data-action="render"]');
    const pngBtn = widget.querySelector('[data-action="png"]');
    const xSelect = widget.querySelector('.widget-xaxis');
    const ySelect = widget.querySelector('.widget-yaxis');

    // Widget repositioning (drag header to move)
    let isDragging = false;
    let offset = { x: 0, y: 0 };

    header.addEventListener('mousedown', e => {
      isDragging = true;
      const rect = widget.getBoundingClientRect();
      offset.x = e.clientX - rect.left;
      offset.y = e.clientY - rect.top;
      widget.classList.add('dragging');
    });

    document.addEventListener('mousemove', e => {
      if (!isDragging) return;
      const canvas = document.getElementById(CANVAS_ID);
      const rect = canvas.getBoundingClientRect();
      const x = e.clientX - rect.left - offset.x;
      const y = e.clientY - rect.top - offset.y;
      widget.style.left = Math.max(0, x) + 'px';
      widget.style.top = Math.max(0, y) + 'px';
    });

    document.addEventListener('mouseup', () => {
      isDragging = false;
      widget.classList.remove('dragging');
    });

    // Close widget
    closeBtn.addEventListener('click', () => removeWidget(widgetId));

    // Render chart
    renderBtn.addEventListener('click', () => renderChart(widgetId));
    pngBtn.addEventListener('click', () => downloadChartPng(widgetId));

    // Update on axis change
    xSelect.addEventListener('change', () => {
      widgets.get(widgetId).xCol = xSelect.value;
      renderChart(widgetId);
    });

    ySelect.addEventListener('change', () => {
      widgets.get(widgetId).yCol = ySelect.value;
      renderChart(widgetId);
    });

    // Set current values
    xSelect.value = widgets.get(widgetId).xCol;
    ySelect.value = widgets.get(widgetId).yCol;
  }

  // ===== RENDER CHART =====
  function renderChart(widgetId) {
    const widget = document.getElementById(widgetId);
    if (!widget) return;

    const state = widgets.get(widgetId);
    const canvas = widget.querySelector('canvas');
    const body = widget.querySelector('.viz-widget-body');

    // Show loading
    body.innerHTML = `
      <div class="viz-loading">
        <div class="spinner"></div>
        <div style="font-size: 10px;">Rendering...</div>
      </div>
    `;

    // Destroy old chart
    if (state.chart) {
      state.chart.destroy();
      state.chart = null;
    }

    // Get chart data via AJAX
    fetchChartData(state.type, state.xCol, state.yCol)
      .then(data => {
        // Recreate canvas (Chart.js quirk)
        body.innerHTML = '<canvas></canvas>';
        const newCanvas = body.querySelector('canvas');

        // Create new chart
        const chart = createChartInstance(newCanvas, state.type, data);
        state.chart = chart;
      })
      .catch(error => {
        body.innerHTML = `<div style="padding: 20px; text-align: center; color: #e11d48; font-size: 11px;">Error: ${error.message}</div>`;
        console.error('Chart render error:', error);
      });
  }

  // ===== FETCH CHART DATA =====
  function fetchChartData(chartType, xCol, yCol) {
    // Group data based on chart type
    if (chartType === 'scatter') {
      return Promise.resolve(prepareScatterData(xCol, yCol));
    } else if (chartType === 'histogram') {
      return Promise.resolve(prepareHistogramData(yCol));
    } else if (chartType === 'pie') {
      return Promise.resolve(preparePieData(xCol, yCol));
    } else {
      // bar, line
      return Promise.resolve(prepareGroupedData(xCol, yCol));
    }
  }

  // ===== DATA PREPARATION =====
  function prepareGroupedData(xCol, yCol) {
    const map = new Map();
    window.RACO_DATASET_ROWS.forEach(row => {
      const x = String(row[xCol] || '(blank)');
      const y = Number(row[yCol]) || 0;
      map.set(x, (map.get(x) || 0) + y);
    });
    return {
      labels: Array.from(map.keys()),
      data: Array.from(map.values())
    };
  }

  function prepareScatterData(xCol, yCol) {
    const data = window.RACO_DATASET_ROWS
      .map(row => ({
        x: Number(row[xCol]) || 0,
        y: Number(row[yCol]) || 0
      }))
      .filter(d => !isNaN(d.x) && !isNaN(d.y));
    return { labels: [], data };
  }

  function preparePieData(xCol, yCol) {
    const map = new Map();
    window.RACO_DATASET_ROWS.forEach(row => {
      const x = String(row[xCol] || '(blank)');
      const y = Number(row[yCol]) || 0;
      map.set(x, (map.get(x) || 0) + y);
    });
    return {
      labels: Array.from(map.keys()),
      data: Array.from(map.values())
    };
  }

  function prepareHistogramData(yCol) {
    const values = window.RACO_DATASET_ROWS
      .map(row => Number(row[yCol]) || 0)
      .filter(v => !isNaN(v));

    if (!values.length) return { labels: [], data: [] };

    const bins = 10;
    const min = Math.min(...values);
    const max = Math.max(...values);
    const step = (max - min) / bins || 1;

    const histogram = Array(bins).fill(0);
    values.forEach(v => {
      const i = Math.min(bins - 1, Math.floor((v - min) / step));
      histogram[i]++;
    });

    const labels = histogram.map((_, i) => {
      const a = (min + i * step).toFixed(1);
      const b = (min + (i + 1) * step).toFixed(1);
      return `${a} - ${b}`;
    });

    return { labels, data: histogram };
  }

  // ===== CREATE CHART INSTANCE =====
  function createChartInstance(canvas, type, data) {
    const ctx = canvas.getContext('2d');
    const colors = ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#8b5cf6', '#f97316'];

    const chartType = type === 'scatter' ? 'scatter' : (type === 'histogram' ? 'bar' : type);

    return new Chart(ctx, {
      type: chartType,
      data: {
        labels: data.labels,
        datasets: [{
          label: 'Value',
          data: data.data,
          backgroundColor: colors.slice(0, Math.min(colors.length, data.labels.length || 1)),
          borderColor: '#6366f1',
          borderWidth: 1,
          borderRadius: 4,
          fill: type === 'line',
          tension: 0.3
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            display: type !== 'pie' && type !== 'scatter',
            position: 'top'
          },
          tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            padding: 10,
            titleFont: { size: 12 },
            bodyFont: { size: 11 },
            cornerRadius: 4
          }
        },
        scales: chartType === 'pie' || chartType === 'doughnut' ? undefined : {
          y: { beginAtZero: true }
        }
      }
    });
  }

  // ===== DOWNLOAD PNG =====
  function downloadChartPng(widgetId) {
    const state = widgets.get(widgetId);
    if (!state || !state.chart) return;

    const canvas = state.chart.canvas;
    const link = document.createElement('a');
    link.href = canvas.toDataURL('image/png');
    link.download = `chart-${Date.now()}.png`;
    link.click();
  }

  // ===== REMOVE WIDGET =====
  function removeWidget(widgetId) {
    const widget = document.getElementById(widgetId);
    if (!widget) return;

    const state = widgets.get(widgetId);
    if (state && state.chart) {
      state.chart.destroy();
    }

    widget.remove();
    widgets.delete(widgetId);

    // Show placeholder if no widgets
    if (widgets.size === 0) {
      const placeholder = document.querySelector('.canvas-placeholder');
      if (placeholder) placeholder.style.display = 'block';
    }

    updateVizSummary();
  }

  // ===== UPDATE VISUALIZATION SUMMARY =====
  function updateVizSummary() {
    const summary = document.getElementById('vizSummary');
    if (!summary) return;

    if (widgets.size === 0) {
      summary.innerHTML = '<div class="summary-empty">No charts created yet</div>';
    } else {
      const items = Array.from(widgets.entries()).map(([id, state]) => {
        return `
          <div class="widget-summary-item">
            <div class="widget-summary-title">${state.type.toUpperCase()}</div>
            <div class="widget-summary-meta">${state.xCol} vs ${state.yCol}</div>
          </div>
        `;
      });
      summary.innerHTML = items.join('');
    }
  }

  // ===== TAB SWITCHING =====
  function initTabs() {
    document.querySelectorAll(TAB_BUTTON_SELECTOR).forEach(btn => {
      btn.addEventListener('click', () => {
        const tabName = btn.dataset.tab;

        // Deactivate all tabs
        document.querySelectorAll(TAB_BUTTON_SELECTOR).forEach(b => b.classList.remove('active'));
        document.querySelectorAll(TAB_CONTENT_SELECTOR).forEach(tc => tc.classList.remove('active'));

        // Activate selected tab
        btn.classList.add('active');
        const panel = document.getElementById(tabName + '-tab');
        if (panel) panel.classList.add('active');

        // Refresh charts when switching back to canvas
        if (tabName === 'visualization') {
          setTimeout(() => {
            widgets.forEach((state, id) => {
              if (state.chart) state.chart.resize();
            });
          }, 100);
        }
      });
    });
  }

  // ===== INITIALIZATION =====
  function init() {
    if (!window.RACO_COLUMNS || !window.RACO_DATASET_ROWS) {
      console.error('RACO data not loaded');
      return;
    }

    initPaletteDrag();
    initCanvasDrop();
    initTabs();

    console.log('Visualization dashboard initialized');
  }

  // Start when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
