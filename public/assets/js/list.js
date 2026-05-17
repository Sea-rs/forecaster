(() => {
  const form = document.getElementById('save-form');
  const cellEditsInput = document.getElementById('cell-edits-input');
  const modal = document.getElementById('save-result-modal');
  const messageEl = document.getElementById('save-result-message');
  const linksEl = document.getElementById('save-result-links');
  const saveNameInput = document.getElementById('save-register-name');

  if (!form || !cellEditsInput || !modal || !messageEl || !linksEl || !saveNameInput) {
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

  const buildEditsJson = () => {
    document.querySelectorAll('.editable-amount[data-editing="1"]').forEach((cell) => {
      cell.blur();
    });

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

    const edits = {};

    document.querySelectorAll('.editable-amount').forEach((cell) => {
      const jobName = cell.dataset.jobName;
      const jobStatus = cell.dataset.jobStatus || 'その他';
      const month = cell.dataset.month;
      const normalizedFromText = normalizeValue(cell.textContent);
      const rawValue = normalizedFromText ?? String(cell.dataset.rawValue ?? '0');

      if (!jobName || !month || !jobStatus) {
        return;
      }

      if (!edits[jobStatus]) {
        edits[jobStatus] = {};
      }
      if (!edits[jobStatus][jobName]) {
        edits[jobStatus][jobName] = {};
      }

      edits[jobStatus][jobName][month] = rawValue;
      cell.dataset.rawValue = rawValue;
    });

    return JSON.stringify(edits);
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    cellEditsInput.value = buildEditsJson();

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
