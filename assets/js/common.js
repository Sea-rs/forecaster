window.ForecasterUI = window.ForecasterUI || {};

window.ForecasterUI.bindModal = (modal) => {
  if (!modal || modal.dataset.boundModal === '1') {
    return;
  }

  const closeModal = () => {
    modal.classList.add('is-hidden');
  };

  const openModal = () => {
    modal.classList.remove('is-hidden');
  };

  modal.querySelectorAll('[data-modal-close]').forEach((el) => {
    el.addEventListener('click', closeModal);
  });

  modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      closeModal();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !modal.classList.contains('is-hidden')) {
      closeModal();
    }
  });

  modal.dataset.boundModal = '1';
  modal._openModal = openModal;
  modal._closeModal = closeModal;
};

window.ForecasterUI.openModal = (modal) => {
  if (!modal) {
    return;
  }

  window.ForecasterUI.bindModal(modal);
  if (typeof modal._openModal === 'function') {
    modal._openModal();
  }
};

window.ForecasterUI.closeModal = (modal) => {
  if (!modal) {
    return;
  }

  if (typeof modal._closeModal === 'function') {
    modal._closeModal();
    return;
  }

  modal.classList.add('is-hidden');
};

(() => {
  let activeCell = null;

  const normalizeValue = (value) => {
    const trimmed = String(value ?? '').replace(/,/g, '').trim();
    if (trimmed === '') {
      return '';
    }

    if (!/^-?\d+(?:\.\d+)?$/.test(trimmed)) {
      return null;
    }

    return String(Math.round(Number(trimmed)));
  };

  const formatValue = (value) => {
    if (value === '') {
      return '';
    }

    return new Intl.NumberFormat('ja-JP', {
      maximumFractionDigits: 0,
    }).format(Number(value));
  };

  const finishEdit = (cell) => {
    if (!cell || cell.dataset.editing !== '1') {
      return;
    }

    const originalValue = String(cell.dataset.originalValue ?? '');
    const currentValue = normalizeValue(cell.textContent);
    const nextValue = currentValue === null || currentValue === '' ? originalValue : currentValue;

    cell.textContent = formatValue(nextValue);
    cell.dataset.rawValue = nextValue;
    cell.classList.toggle('is-edited', nextValue !== originalValue);
    cell.contentEditable = 'false';
    delete cell.dataset.editing;

    document.dispatchEvent(new CustomEvent('forecaster:cell-updated'));

    if (activeCell === cell) {
      activeCell = null;
    }
  };

  const beginEdit = (cell) => {
    if (activeCell && activeCell !== cell) {
      finishEdit(activeCell);
    }

    activeCell = cell;
    cell.dataset.editing = '1';
    cell.contentEditable = 'true';
    cell.textContent = normalizeValue(cell.dataset.rawValue) ?? String(cell.dataset.originalValue ?? '');

    const selection = window.getSelection();
    const range = document.createRange();
    range.selectNodeContents(cell);
    selection.removeAllRanges();
    selection.addRange(range);

    cell.focus();
  };

  document.addEventListener('dblclick', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement) || !target.classList.contains('editable-amount')) {
      return;
    }

    event.preventDefault();
    beginEdit(target);
  });

  document.addEventListener('focusout', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement) || !target.classList.contains('editable-amount')) {
      return;
    }

    finishEdit(target);
  });

  document.addEventListener('keydown', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement) || !target.classList.contains('editable-amount')) {
      return;
    }

    if (event.key === 'Enter') {
      event.preventDefault();
      target.blur();
    }
  });

  document.addEventListener('mousedown', (event) => {
    if (!activeCell) {
      return;
    }

    if (activeCell.contains(event.target)) {
      return;
    }

    finishEdit(activeCell);
  }, true);
})();

// Quarter and half-year aggregate calculation
(() => {
  const monthLabels = ['4月', '5月', '6月', '7月', '8月', '9月', '10月', '11月', '12月', '1月', '2月', '3月'];

  const quarterMapping = {
    '1q': [0, 1, 2],      // 4月, 5月, 6月
    '2q': [3, 4, 5],      // 7月, 8月, 9月
    '3q': [6, 7, 8],      // 10月, 11月, 12月
    '4q': [9, 10, 11],    // 1月, 2月, 3月
  };

  const halfYearMapping = {
    '1h': [0, 1, 2, 3, 4, 5],        // 4月-9月
    '2h': [6, 7, 8, 9, 10, 11],      // 10月-3月
  };

  const formatValue = (value) => {
    if (value === '' || value === 0) {
      return '0';
    }
    return new Intl.NumberFormat('ja-JP', {
      maximumFractionDigits: 0,
    }).format(Number(value));
  };

  const calculateQuarterTotal = (jobStatus, jobName, quarter, metric) => {
    const indices = quarterMapping[quarter];
    if (!indices) {
      return 0;
    }

    let total = 0;
    indices.forEach((idx) => {
      const monthLabel = monthLabels[idx];
      const cell = document.querySelector(
        `.editable-amount[data-job-status="${jobStatus}"][data-job-name="${jobName}"][data-month="${monthLabel}"][data-metric="${metric}"]`
      );
      if (cell) {
        const rawValue = cell.dataset.rawValue || '0';
        const normalized = String(rawValue).replace(/,/g, '').trim();
        total += Number(normalized) || 0;
      }
    });

    return total;
  };

  const calculateHalfYearTotal = (jobStatus, jobName, half, metric) => {
    const indices = halfYearMapping[half];
    if (!indices) {
      return 0;
    }

    let total = 0;
    indices.forEach((idx) => {
      const monthLabel = monthLabels[idx];
      const cell = document.querySelector(
        `.editable-amount[data-job-status="${jobStatus}"][data-job-name="${jobName}"][data-month="${monthLabel}"][data-metric="${metric}"]`
      );
      if (cell) {
        const rawValue = cell.dataset.rawValue || '0';
        const normalized = String(rawValue).replace(/,/g, '').trim();
        total += Number(normalized) || 0;
      }
    });

    return total;
  };

  const updateAggregates = () => {
    // Update all quarter values
    document.querySelectorAll('.quarter-value').forEach((cell) => {
      const quarter = cell.dataset.quarter;
      const jobStatus = cell.dataset.jobStatus;
      const jobName = cell.dataset.jobName;
      const metric = cell.dataset.metric;

      if (quarter && jobStatus && jobName && metric) {
        const total = calculateQuarterTotal(jobStatus, jobName, quarter, metric);
        cell.textContent = formatValue(total);
      }
    });

    // Update all half-year values
    document.querySelectorAll('.half-year-value').forEach((cell) => {
      const half = cell.dataset.half;
      const jobStatus = cell.dataset.jobStatus;
      const jobName = cell.dataset.jobName;
      const metric = cell.dataset.metric;

      if (half && jobStatus && jobName && metric) {
        const total = calculateHalfYearTotal(jobStatus, jobName, half, metric);
        cell.textContent = formatValue(total);
      }
    });

    // Update overall totals and each half-year total.
    ['uriage', 'syauri'].forEach((metric) => {
      const target = document.querySelector(`.total-value[data-total-metric="${metric}"]`);
      const firstHalfTarget = document.querySelector(`.total-value[data-total-metric="${metric}"][data-total-half="1h"]`);
      const secondHalfTarget = document.querySelector(`.total-value[data-total-metric="${metric}"][data-total-half="2h"]`);
      if (!target && !firstHalfTarget && !secondHalfTarget) {
        return;
      }

      let firstHalfTotal = 0;
      let secondHalfTotal = 0;

      document.querySelectorAll(`.half-year-value[data-metric="${metric}"]`).forEach((cell) => {
        const normalized = String(cell.textContent ?? '').replace(/,/g, '').trim();
        const value = Number(normalized) || 0;
        if (cell.dataset.half === '1h') {
          firstHalfTotal += value;
        }
        if (cell.dataset.half === '2h') {
          secondHalfTotal += value;
        }
      });

      if (target) {
        target.textContent = formatValue(firstHalfTotal + secondHalfTotal);
      }
      if (firstHalfTarget) {
        firstHalfTarget.textContent = formatValue(firstHalfTotal);
      }
      if (secondHalfTarget) {
        secondHalfTarget.textContent = formatValue(secondHalfTotal);
      }
    });
  };

  window.ForecasterUI.updateAggregates = updateAggregates;

  // Initial calculation on page load
  updateAggregates();

  // Listen for changes on editable cells
  document.addEventListener('blur', (event) => {
    if (event.target.classList.contains('editable-amount')) {
      // Use a small delay to allow finishEdit to complete
      setTimeout(updateAggregates, 0);
    }
  }, true);

  document.addEventListener('forecaster:cell-updated', () => {
    updateAggregates();
  });
})();
