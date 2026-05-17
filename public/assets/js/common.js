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
  const editableCells = Array.from(document.querySelectorAll('.editable-amount'));

  if (editableCells.length === 0) {
    return;
  }

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

  editableCells.forEach((cell) => {
    cell.addEventListener('dblclick', (event) => {
      event.preventDefault();
      beginEdit(cell);
    });

    cell.addEventListener('blur', () => {
      finishEdit(cell);
    });

    cell.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        cell.blur();
      }
    });
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
