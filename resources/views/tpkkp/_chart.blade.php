{{-- Pemuat Chart.js sekali per halaman + tema seragam --}}
@once
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  window.eqChartSiap = function (cb) {
    if (typeof Chart === 'undefined') return setTimeout(function () { window.eqChartSiap(cb); }, 120);
    if (!window.__eqTema) {
      Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
      Chart.defaults.font.size   = 11;
      Chart.defaults.color       = '#78716c';
      Chart.defaults.animation.duration = 700;
      Chart.defaults.plugins.legend.labels.boxWidth  = 10;
      Chart.defaults.plugins.legend.labels.boxHeight = 10;
      Chart.defaults.plugins.legend.labels.usePointStyle = true;
      Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(27,32,36,.94)';
      Chart.defaults.plugins.tooltip.padding = 10;
      Chart.defaults.plugins.tooltip.cornerRadius = 8;
      Chart.defaults.plugins.tooltip.titleFont = { weight: '700' };
      window.__eqTema = true;
    }
    cb();
  };
  window.eqWarnaLevel = ['#E5484D','#F5760A','#C7DE30','#1EE699','#47CEFF'];
</script>
@endonce
