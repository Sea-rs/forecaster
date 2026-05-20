(() => {
  document.querySelectorAll('.job-card-toggle').forEach((toggle) => {
    toggle.addEventListener('click', () => {
      const bodyId = toggle.getAttribute('aria-controls');
      if (!bodyId) {
        return;
      }

      const body = document.getElementById(bodyId);
      if (!body) {
        return;
      }

      const icon = toggle.querySelector('.toggle-icon');
      const label = toggle.querySelector('.toggle-label');
      const isOpen = !body.hidden;

      body.hidden = isOpen;
      toggle.setAttribute('aria-expanded', String(!isOpen));

      if (icon) {
        icon.textContent = isOpen ? '▶' : '▼';
      }
      if (label) {
        label.textContent = isOpen ? '詳細を表示する' : '詳細を閉じる';
      }
    });
  });
})();
