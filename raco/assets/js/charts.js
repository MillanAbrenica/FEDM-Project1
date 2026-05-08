(function () {
  var chart = null;

  function groupedData(rows, xKey, yKey) {
    var map = new Map();
    rows.forEach(function (r) {
      var x = r[xKey] == null ? '(blank)' : String(r[xKey]);
      var y = Number(r[yKey]);
      if (!map.has(x)) map.set(x, 0);
      map.set(x, map.get(x) + (isNaN(y) ? 0 : y));
    });
    return { labels: Array.from(map.keys()), values: Array.from(map.values()) };
  }

  function histogram(values, bins) {
    if (!values.length) return { labels: [], values: [] };
    var min = Math.min.apply(null, values);
    var max = Math.max.apply(null, values);
    var step = (max - min) / bins || 1;
    var out = Array(bins).fill(0);
    values.forEach(function (v) {
      var i = Math.min(bins - 1, Math.floor((v - min) / step));
      out[i] += 1;
    });
    var labels = out.map(function (_, i) {
      var a = (min + i * step).toFixed(2);
      var b = (min + (i + 1) * step).toFixed(2);
      return a + ' - ' + b;
    });
    return { labels: labels, values: out };
  }

  window.racoRenderChart = function (rows, type, xKey, yKey) {
    var canvas = document.getElementById('mainChart');
    if (!canvas || !rows || !rows.length || !xKey || !yKey) return;
    if (chart) chart.destroy();

    var labels = [];
    var values = [];
    var realType = type;

    if (type === 'histogram') {
      var nums = rows.map(function (r) { return Number(r[yKey]); }).filter(function (v) { return !isNaN(v); });
      var hist = histogram(nums, 10);
      labels = hist.labels;
      values = hist.values;
      realType = 'bar';
    } else if (type === 'scatter') {
      labels = [];
      values = rows.map(function (r) { return { x: Number(r[xKey]) || 0, y: Number(r[yKey]) || 0 }; });
    } else if (type === 'pie') {
      var pie = groupedData(rows, xKey, yKey);
      labels = pie.labels;
      values = pie.values;
    } else {
      var g = groupedData(rows, xKey, yKey);
      labels = g.labels;
      values = g.values;
    }

    chart = new Chart(canvas.getContext('2d'), {
      type: realType,
      data: {
        labels: labels,
        datasets: [{
          label: yKey,
          data: values,
          backgroundColor: ['#4f6ef7', '#2e7d32', '#f39c12', '#8e44ad', '#e74c3c', '#1abc9c', '#2980b9'],
          borderColor: '#3f5ce6',
          borderWidth: 1,
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: true } },
        scales: realType === 'pie' ? undefined : { y: { beginAtZero: true } }
      }
    });
  };

  // Create a chart instance into a specific canvas element and return the Chart instance.
  window.racoCreateChart = function (canvasElem, rows, type, xKey, yKey) {
    if (!canvasElem || !rows || !rows.length || !xKey || !yKey) return null;

    var labels = [];
    var values = [];
    var realType = type;

    if (type === 'histogram') {
      var nums = rows.map(function (r) { return Number(r[yKey]); }).filter(function (v) { return !isNaN(v); });
      var hist = histogram(nums, 10);
      labels = hist.labels;
      values = hist.values;
      realType = 'bar';
    } else if (type === 'scatter') {
      labels = [];
      values = rows.map(function (r) { return { x: Number(r[xKey]) || 0, y: Number(r[yKey]) || 0 }; });
    } else if (type === 'pie') {
      var pie = groupedData(rows, xKey, yKey);
      labels = pie.labels;
      values = pie.values;
    } else {
      var g = groupedData(rows, xKey, yKey);
      labels = g.labels;
      values = g.values;
    }

    try {
      var ctx = (canvasElem.getContext) ? canvasElem.getContext('2d') : canvasElem;
      return new Chart(ctx, {
        type: realType,
        data: {
          labels: labels,
          datasets: [{
            label: yKey,
            data: values,
            backgroundColor: ['#4f6ef7', '#2e7d32', '#f39c12', '#8e44ad', '#e74c3c', '#1abc9c', '#2980b9'],
            borderColor: '#3f5ce6',
            borderWidth: 1,
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          layout: { padding: 8 },
          plugins: { legend: { display: true } },
          scales: realType === 'pie' ? undefined : { y: { beginAtZero: true } }
        }
      });
    } catch (e) {
      console.error('racoCreateChart error', e);
      return null;
    }
  };

  window.racoSaveChartPng = function () {
    var canvas = document.getElementById('mainChart');
    if (!canvas) return;
    var a = document.createElement('a');
    a.href = canvas.toDataURL('image/png');
    a.download = 'raco_chart.png';
    a.click();
  };
})();
