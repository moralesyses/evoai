/*
 * Backoffice tab 5 — Reports (reference component "kn").
 * Report type uses the shared tab helper (group "rep"); this file handles the
 * period toggle (Last 7 Days / Last 30 Days / This Year) and the income type
 * filter (All / Pairing / Direct) by re-rendering the bar charts from the
 * datasets precomputed in views/backoffice/reports.php (#bo-rep-data).
 */
(function () {
  'use strict';

  var wrap = document.querySelector('[data-bo-reports]');
  if (!wrap) return;

  var data = {};
  try {
    data = JSON.parse((document.getElementById('bo-rep-data') || {}).textContent || '{}');
  } catch (e) {}

  var state = { period: '7days', filter: 'all' };
  var filters = wrap.querySelector('[data-rep-filters]');

  function barsHtml(bars, topCls, bottomCls) {
    return (bars || [])
      .map(function (b) {
        return (
          '<div class="flex-1 flex flex-col items-center h-full justify-end group">' +
          '<div class="text-[10px] text-zinc-400 font-mono opacity-0 group-hover:opacity-100 transition-opacity mb-1 font-bold">' + EVO.esc(b.tip) + '</div>' +
          '<div class="w-full max-w-[48px] rounded-t-xl overflow-hidden flex flex-col transition-all duration-300 group-hover:brightness-110" style="height: ' + (+b.n || 0) + '%">' +
          '<div class="' + EVO.esc(topCls) + '" style="height: ' + (+b.r || 0) + '%" title="' + EVO.esc(b.titleTop) + '"></div>' +
          '<div class="' + EVO.esc(bottomCls) + '" style="height: ' + (+b.i || 0) + '%" title="' + EVO.esc(b.titleBottom) + '"></div>' +
          '</div>' +
          '<div class="text-[10px] text-zinc-400 font-semibold mt-2 truncate max-w-[50px] text-center">' + EVO.esc(b.label) + '</div>' +
          '</div>'
        );
      })
      .join('');
  }

  function renderChart(name, set) {
    var box = wrap.querySelector('[data-rep-bars="' + name + '"]');
    if (!box || !set) return;
    box.innerHTML = barsHtml(set.bars, box.getAttribute('data-top'), box.getAttribute('data-bottom'));
  }

  function setText(key, text) {
    var el = wrap.querySelector('[data-rep="' + key + '"]');
    if (el && text != null) el.textContent = text;
  }

  function render() {
    var income = ((data.income || {})[state.period] || {})[state.filter];
    var network = (data.network || {})[state.period];
    if (income) {
      renderChart('income', income);
      setText('high', income.high);
      setText('highSub', income.highSub);
      setText('avg', income.avg);
    }
    if (network) {
      renderChart('network', network);
      setText('growth', network.growth);
      setText('growthSub', network.growthSub);
    }
  }

  function press(attr, btn) {
    EVO.qsa('[' + attr + ']', wrap).forEach(function (b) {
      var on = b === btn;
      EVO.setActive(b, on);
      b.removeAttribute('aria-selected');
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  wrap.addEventListener('click', function (ev) {
    var p = ev.target.closest('[data-rep-period]');
    if (p) {
      state.period = p.getAttribute('data-rep-period');
      press('data-rep-period', p);
      render();
      return;
    }
    var f = ev.target.closest('[data-rep-filter]');
    if (f) {
      state.filter = f.getAttribute('data-rep-filter');
      press('data-rep-filter', f);
      render();
    }
  });

  // The income type filter is only shown on the income report (reference: n === 'income').
  document.addEventListener('evo:tab', function (ev) {
    if (!ev.detail || ev.detail.group !== 'rep' || !filters) return;
    filters.hidden = ev.detail.id !== 'income';
  });
})();
