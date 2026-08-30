(function () {
  const page = document.querySelector('[data-infra-configurator-page]');
  if (!page) return;

  const panels = {
    DEDICATED_SERVER: ['Dedicated Server configurator', 'Manual CPU, RAM, storage, and RAID fields arrive in Phase 2.'],
    VIRTUAL_MACHINE: ['Virtual Machine configurator', 'Manual vCPU, RAM, storage tier, and OS fields arrive in Phase 2.'],
    COLOCATION: ['Colocation configurator', 'Manual data center, rack U, ampere, setup, and deposit fields arrive in Phase 2.'],
    CUSTOM_SERVICE: ['Custom Service configurator', 'Manual service, quantity, billing, and price fields arrive in Phase 2.'],
  };

  const title = page.querySelector('[data-infra-panel-title]');
  const copy = page.querySelector('[data-infra-panel-copy]');
  const buttons = Array.from(page.querySelectorAll('[data-infra-product]'));

  buttons.forEach((button) => {
    button.addEventListener('click', () => {
      const selected = button.getAttribute('data-infra-product') || 'DEDICATED_SERVER';
      const panel = panels[selected] || panels.DEDICATED_SERVER;

      buttons.forEach((item) => {
        const active = item === button;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-pressed', active ? 'true' : 'false');
      });

      if (title) title.textContent = panel[0];
      if (copy) copy.textContent = panel[1];
      if (window.lucide) window.lucide.createIcons();
    });
  });
})();
