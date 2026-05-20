(() => {
  const form = document.getElementById('diff-select-form');
  if (!form) {
    return;
  }

  const rawMap = form.getAttribute('data-register-map') || '{}';
  let registerMap = {};

  try {
    registerMap = JSON.parse(rawMap);
  } catch {
    registerMap = {};
  }

  const yearSelect = document.getElementById('diff-year');
  const newSelect = document.getElementById('diff-new');
  const oldSelect = document.getElementById('diff-old');

  if (!yearSelect || !newSelect || !oldSelect) {
    return;
  }

  const refill = (target, names, selectedName) => {
    target.innerHTML = '';

    names.forEach((name) => {
      const option = document.createElement('option');
      option.value = name;
      option.textContent = name;
      option.selected = name === selectedName;
      target.appendChild(option);
    });

    if (target.options.length > 0 && target.selectedIndex < 0) {
      target.selectedIndex = 0;
    }
  };

  yearSelect.addEventListener('change', () => {
    const names = registerMap[yearSelect.value] || [];
    const nextNew = names[0] || '';
    const nextOld = names[1] || (names[0] || '');

    refill(newSelect, names, nextNew);
    refill(oldSelect, names, nextOld);
  });
})();
