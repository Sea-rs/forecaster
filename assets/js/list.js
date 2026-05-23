(() => {
  const form = document.getElementById('save-form');
  const cellEditsInput = document.getElementById('cell-edits-input');
  const addedJobsInput = document.getElementById('added-jobs-input');
  const modal = document.getElementById('save-result-modal');
  const messageEl = document.getElementById('save-result-message');
  const linksEl = document.getElementById('save-result-links');
  const saveNameInput = document.getElementById('save-register-name');
  const addJobNameInput = document.getElementById('add-job-name');
  const addJobButton = document.getElementById('add-job-button');
  const addedJobsList = document.getElementById('added-jobs-list');
  const addJobMonthInputs = Array.from(document.querySelectorAll('.add-job-month-input'));
  const monthLabels = ['4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月', '1月', '2月', '3月'];
  const pendingAddedJobs = [];
  const addJobStatusInput = document.getElementById('add-job-status');
  const statusOrder = ['固定', '按分', '変動', 'その他'];
  let nextTempId = 1;

  if (!form || !cellEditsInput || !addedJobsInput || !modal || !messageEl || !linksEl || !saveNameInput) {
    return;
  }

  if (window.ForecasterUI) {
    window.ForecasterUI.bindModal(modal);
  }

  const openResultModal = (ok, message, viewUrl = '') => {
    messageEl.className = ok ? 'msg ok' : 'msg ng';
    messageEl.textContent = message;
    linksEl.innerHTML = '';

    if (ok && viewUrl !== '') {
      const link = document.createElement('a');
      link.href = viewUrl;
      link.textContent = '保存先を見る';
      linksEl.appendChild(link);
    }

    if (window.ForecasterUI) {
      window.ForecasterUI.openModal(modal);
    } else {
      modal.classList.remove('is-hidden');
    }
  };

  const normalizeValue = (value) => {
    const trimmed = String(value ?? '').replace(/,/g, '').trim();
    if (trimmed === '') {
      return '0';
    }

    if (!/^-?\d+(?:\.\d+)?$/.test(trimmed)) {
      return null;
    }

    return String(Math.round(Number(trimmed)));
  };

  const formatMoney = (value) => {
    return new Intl.NumberFormat('ja-JP', {
      maximumFractionDigits: 0,
    }).format(Number(value));
  };

  const escapeHtml = (value) => {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  const renderAddedJobs = () => {
    if (!addedJobsList) {
      return;
    }

    if (pendingAddedJobs.length === 0) {
      addedJobsList.innerHTML = '<p class="muted">追加候補はありません。</p>';
      return;
    }

    const rows = pendingAddedJobs.map((job, index) => {
      const monthSummary = monthLabels.flatMap((month) => {
        const uriage = Number((job.months[month] || {}).uriage || 0);
        const syauri = Number((job.months[month] || {}).syauri || 0);
        const items = [];
        if (uriage !== 0) {
          items.push(`${month} 売上: ${formatMoney(uriage)}`);
        }
        if (syauri !== 0) {
          items.push(`${month} 社売: ${formatMoney(syauri)}`);
        }
        return items;
      }).join(', ');

      return `
        <div class="added-job-item">
          <div class="added-job-meta">
            <strong>${escapeHtml(job.jobName)}</strong>
            <span>${escapeHtml(job.status)} / ${escapeHtml(monthSummary || '全月 0')}</span>
          </div>
          <button type="button" class="danger-btn added-job-remove" data-remove-index="${index}" data-remove-id="${job.id}">削除</button>
        </div>
      `;
    });

    addedJobsList.innerHTML = rows.join('');
  };

  const createAmountCell = (jobName, jobStatus, month, metric, rawValue) => {
    const cell = document.createElement('td');
    cell.className = 'amount-cell editable-amount';
    cell.dataset.originalValue = String(rawValue);
    cell.dataset.rawValue = String(rawValue);
    cell.dataset.jobName = jobName;
    cell.dataset.jobStatus = jobStatus;
    cell.dataset.month = month;
    cell.dataset.metric = metric;
    cell.textContent = formatMoney(Number(rawValue));
    return cell;
  };

  const createAggregateCell = (type, key, jobName, jobStatus, metric) => {
    const cell = document.createElement('td');
    cell.className = type === 'quarter' ? 'quarter-value' : 'half-year-value';
    if (type === 'quarter') {
      cell.dataset.quarter = key;
    } else {
      cell.dataset.half = key;
    }
    cell.dataset.jobName = jobName;
    cell.dataset.jobStatus = jobStatus;
    cell.dataset.metric = metric;
    cell.textContent = '0';
    return cell;
  };

  const findOrCreateStatusSeparator = (tbody, status) => {
    const separators = Array.from(tbody.querySelectorAll('tr.status-separator'));
    for (const row of separators) {
      const label = row.textContent ? row.textContent.trim() : '';
      if (label === status) {
        return row;
      }
    }

    const row = document.createElement('tr');
    row.className = 'status-separator';
    const td = document.createElement('td');
    const headerCols = document.querySelectorAll('thead tr th').length;
    td.colSpan = headerCols;
    td.textContent = status;
    row.appendChild(td);

    const currentIndex = statusOrder.indexOf(status);
    let inserted = false;
    separators.forEach((sep) => {
      if (inserted) {
        return;
      }
      const label = sep.textContent ? sep.textContent.trim() : '';
      const idx = statusOrder.indexOf(label);
      if (idx !== -1 && idx > currentIndex) {
        tbody.insertBefore(row, sep);
        inserted = true;
      }
    });

    if (!inserted) {
      tbody.appendChild(row);
    }

    return row;
  };

  const appendAddedJobToTable = (job) => {
    const tbody = document.querySelector('table tbody');
    if (!tbody) {
      return;
    }

    const separator = findOrCreateStatusSeparator(tbody, job.status);
    const insertAfter = separator;

    const createMetricRow = (metric) => {
      const row = document.createElement('tr');
      row.className = metric === 'uriage' ? 'metric-row metric-uriage-row' : 'metric-row metric-syauri-row';
      row.dataset.addedJobId = String(job.id);

      if (metric === 'uriage') {
        const nameCell = document.createElement('td');
        nameCell.className = 'metric-name-cell';
        nameCell.rowSpan = 2;
        nameCell.textContent = job.jobName;
        row.appendChild(nameCell);
      }

      const labelCell = document.createElement('td');
      labelCell.className = 'metric-label-cell';
      labelCell.textContent = metric === 'uriage' ? '売上' : '社売';
      row.appendChild(labelCell);

      monthLabels.forEach((month, i) => {
        const value = (job.months[month] || {})[metric] || '0';
        row.appendChild(createAmountCell(job.jobName, job.status, month, metric, value));

        if (i === 2) {
          row.appendChild(createAggregateCell('quarter', '1q', job.jobName, job.status, metric));
        }
        if (i === 5) {
          row.appendChild(createAggregateCell('quarter', '2q', job.jobName, job.status, metric));
          row.appendChild(createAggregateCell('half', '1h', job.jobName, job.status, metric));
        }
        if (i === 8) {
          row.appendChild(createAggregateCell('quarter', '3q', job.jobName, job.status, metric));
        }
        if (i === 11) {
          row.appendChild(createAggregateCell('quarter', '4q', job.jobName, job.status, metric));
          row.appendChild(createAggregateCell('half', '2h', job.jobName, job.status, metric));
        }
      });

      return row;
    };

    const uriageRow = createMetricRow('uriage');
    const syauriRow = createMetricRow('syauri');

    if (insertAfter.nextSibling) {
      tbody.insertBefore(uriageRow, insertAfter.nextSibling);
      tbody.insertBefore(syauriRow, uriageRow.nextSibling);
    } else {
      tbody.appendChild(uriageRow);
      tbody.appendChild(syauriRow);
    }

    const countEl = document.querySelector('.muted');
    if (countEl && countEl.textContent && countEl.textContent.includes('ジョブ件数:')) {
      const metricRows = tbody.querySelectorAll('tr.metric-uriage-row').length;
      countEl.textContent = `ジョブ件数: ${metricRows} 件`;
    }

    if (window.ForecasterUI && typeof window.ForecasterUI.updateAggregates === 'function') {
      window.ForecasterUI.updateAggregates();
    }
  };

  const removeAddedJobFromTable = (addedJobId) => {
    if (!addedJobId) {
      return;
    }

    document.querySelectorAll(`tr[data-added-job-id="${addedJobId}"]`).forEach((row) => {
      row.remove();
    });

    const tbody = document.querySelector('table tbody');
    if (tbody) {
      tbody.querySelectorAll('tr.status-separator').forEach((sep) => {
        let next = sep.nextElementSibling;
        let hasRows = false;
        while (next && !next.classList.contains('status-separator')) {
          if (next.classList.contains('metric-row')) {
            hasRows = true;
            break;
          }
          next = next.nextElementSibling;
        }
        if (!hasRows) {
          sep.remove();
        }
      });

      const countEl = document.querySelector('.muted');
      if (countEl && countEl.textContent && countEl.textContent.includes('ジョブ件数:')) {
        const metricRows = tbody.querySelectorAll('tr.metric-uriage-row').length;
        countEl.textContent = `ジョブ件数: ${metricRows} 件`;
      }
    }

    if (window.ForecasterUI && typeof window.ForecasterUI.updateAggregates === 'function') {
      window.ForecasterUI.updateAggregates();
    }
  };

  const buildAddedJobsJson = () => {
    return JSON.stringify(pendingAddedJobs);
  };

  const buildEditsJson = () => {
    document.querySelectorAll('.editable-amount[data-editing="1"]').forEach((cell) => {
      cell.blur();
    });

    const edits = {};

    document.querySelectorAll('.editable-amount').forEach((cell) => {
      const jobName = cell.dataset.jobName;
      const jobStatus = cell.dataset.jobStatus || 'その他';
      const month = cell.dataset.month;
      const metric = cell.dataset.metric || 'seikyu';
      const normalizedFromText = normalizeValue(cell.textContent);
      const rawValue = normalizedFromText ?? String(cell.dataset.rawValue ?? '0');

      if (!jobName || !month || !jobStatus || !metric) {
        return;
      }

      if (!edits[jobStatus]) {
        edits[jobStatus] = {};
      }
      if (!edits[jobStatus][jobName]) {
        edits[jobStatus][jobName] = {};
      }
      if (!edits[jobStatus][jobName][month] || typeof edits[jobStatus][jobName][month] !== 'object') {
        edits[jobStatus][jobName][month] = {};
      }

      edits[jobStatus][jobName][month][metric] = rawValue;
      cell.dataset.rawValue = rawValue;
    });

    return JSON.stringify(edits);
  };

  if (addJobButton && addJobNameInput && addJobMonthInputs.length > 0) {
    renderAddedJobs();

    addJobButton.addEventListener('click', () => {
      const jobStatus = addJobStatusInput ? String(addJobStatusInput.value || '') : '';
      const jobName = addJobNameInput.value.trim();
      if (!statusOrder.includes(jobStatus) || jobStatus === 'その他') {
        openResultModal(false, '区分は「固定」「按分」「変動」から選択してください。');
        return;
      }
      if (jobName === '') {
        openResultModal(false, '追加するジョブ名を入力してください。');
        return;
      }

      const months = {};
      let hasNonZero = false;

      for (const input of addJobMonthInputs) {
        const month = input.dataset.month || '';
        const metric = input.dataset.metric || '';
        const normalized = normalizeValue(input.value);

        if (!month || !['uriage', 'syauri'].includes(metric) || normalized === null) {
          openResultModal(false, '月ごとの売上/社売には数値を入力してください。');
          return;
        }

        if (!months[month]) {
          months[month] = {
            uriage: '0',
            syauri: '0',
          };
        }
        months[month][metric] = normalized;
        input.value = normalized;

        if (Number(normalized) !== 0) {
          hasNonZero = true;
        }
      }

      if (!hasNonZero) {
        openResultModal(false, '少なくとも1ヶ月の金額を0以外で入力してください。');
        return;
      }

      const addedJob = {
        id: String(nextTempId++),
        status: jobStatus,
        jobName,
        months,
      };

      pendingAddedJobs.push(addedJob);
      appendAddedJobToTable(addedJob);

      addJobNameInput.value = '';
      addJobMonthInputs.forEach((input) => {
        input.value = '0';
      });

      renderAddedJobs();
    });

    if (addedJobsList) {
      addedJobsList.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
          return;
        }

        const index = target.dataset.removeIndex;
        const id = target.dataset.removeId;
        if (typeof index === 'undefined') {
          return;
        }

        const removeIndex = Number(index);
        if (!Number.isInteger(removeIndex) || removeIndex < 0 || removeIndex >= pendingAddedJobs.length) {
          return;
        }

        pendingAddedJobs.splice(removeIndex, 1);
        removeAddedJobFromTable(id || '');
        renderAddedJobs();
      });
    }
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    cellEditsInput.value = buildEditsJson();
    addedJobsInput.value = buildAddedJobsJson();

    const formData = new FormData(form);
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) {
      submitButton.disabled = true;
    }

    try {
      const response = await fetch(window.location.pathname + window.location.search, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData,
      });

      let data = null;
      try {
        data = await response.json();
      } catch (_error) {
        data = null;
      }

      if (!data || typeof data.ok !== 'boolean') {
        openResultModal(false, '保存結果の取得に失敗しました。');
        return;
      }

      openResultModal(data.ok, data.message ?? '保存しました。', data.viewUrl ?? '');

      if (data.ok && saveNameInput.value.trim() !== '') {
        const hiddenSource = form.querySelector('input[name="save_source_register"]');
        if (hiddenSource) {
          hiddenSource.value = saveNameInput.value.trim();
        }
      }
    } catch (_error) {
      openResultModal(false, '通信エラーが発生しました。時間をおいて再度お試しください。');
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
      }
    }
  });
})();
