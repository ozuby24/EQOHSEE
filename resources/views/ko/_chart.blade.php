@once
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  window.koChartSiap = function (cb) {
    if (typeof Chart === 'undefined') return setTimeout(function () { window.koChartSiap(cb); }, 120);
    if (!window.__koTema) {
      Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
      Chart.defaults.font.size = 11;
      Chart.defaults.color = '#78716c';
      Chart.defaults.plugins.legend.labels.usePointStyle = true;
      Chart.defaults.plugins.legend.labels.boxWidth = 10;
      Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(27,32,36,.94)';
      Chart.defaults.plugins.tooltip.cornerRadius = 8;
      Chart.defaults.plugins.tooltip.padding = 10;
      window.__koTema = true;
    }
    cb();
  };
</script>
@endonce
