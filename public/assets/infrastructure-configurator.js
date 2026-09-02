(function () {
  const page = document.querySelector('[data-infra-configurator-page]');
  if (!page) return;

  const data = window.TRACS_INFRA_CONFIGURATOR_DATA || {};
  const rates = data.rate_cards || {};
  const state = { product: 'DEDICATED_SERVER' };

  const buttons = Array.from(page.querySelectorAll('[data-infra-product]'));
  const panels = Array.from(page.querySelectorAll('[data-infra-panel]'));
  const recurringLines = page.querySelector('[data-infra-summary-recurring]');
  const oneTimeLines = page.querySelector('[data-infra-summary-onetime]');
  const recurringEmpty = page.querySelector('[data-infra-recurring-empty]');
  const oneTimeEmpty = page.querySelector('[data-infra-onetime-empty]');
  const mrcTotal = page.querySelector('[data-infra-summary-mrc]');
  const otcTotal = page.querySelector('[data-infra-summary-otc]');
  const taxTotal = page.querySelector('[data-infra-summary-tax]');
  const mrcFinalTotal = page.querySelector('[data-infra-summary-mrc-final]');
  const firstInvoiceTotal = page.querySelector('[data-infra-summary-first-invoice]');
  const generateButton = page.querySelector('[data-infra-generate]');
  const smartStatus = page.querySelector('[data-infra-smart-status]');
  const assumptionsBox = page.querySelector('[data-infra-assumptions]');
  const assumptionList = page.querySelector('[data-infra-assumption-list]');
  const recommendationBox = page.querySelector('[data-infra-recommendation]');
  const recommendationLines = page.querySelector('[data-infra-recommendation-lines]');
  const addonInputs = Array.from(page.querySelectorAll('[data-infra-addon]'));

  function input(name) {
    return page.querySelector(`[data-infra-input="${name}"]`);
  }

  function numberValue(name, fallback) {
    const value = Number(input(name)?.value);
    return Number.isFinite(value) && value > 0 ? value : fallback;
  }

  function optionalNumber(name) {
    const raw = input(name)?.value;
    if (!raw && raw !== '0') return null;
    const value = Number(raw);
    return Number.isFinite(value) && value >= 0 ? value : null;
  }

  function option(list, id) {
    return (list || []).find((item) => item.id === id) || (list || [])[0] || {};
  }

  function setInput(name, value) {
    const node = input(name);
    if (node && value !== null && arguments.length > 1) node.value = value;
  }

  function money(value) {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      maximumFractionDigits: 0,
    }).format(Math.max(0, value || 0)).replace(/\s/g, '');
  }

  function esc(value) {
    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    return String(value ?? '').replace(/[&<>"']/g, (char) => map[char]);
  }

  function capacityLabel(gb) {
    return gb >= 1000 ? `${(gb / 1000).toFixed(gb % 1000 === 0 ? 0 : 2)} TB` : `${gb} GB`;
  }

  function parseRequirement(text) {
    const source = String(text || '').toLowerCase().trim();
    const parsed = {
      product_type: null,
      minimum_storage_gb: null,
      ram_gb: null,
      vcpu: null,
      storage_tier: null,
      os: null,
      data_center_id: null,
      rack_u: null,
      ampere: null,
      budget_monthly_idr: null,
    };

    if (!source) return parsed;

    if (/\b(colo|colocation)\b/.test(source)) parsed.product_type = 'COLOCATION';
    else if (/\b(vm|virtual machine)\b/.test(source)) parsed.product_type = 'VIRTUAL_MACHINE';
    else if (/\b(server|dedicated)\b/.test(source)) parsed.product_type = 'DEDICATED_SERVER';

    if (/\bwindows\b/.test(source)) parsed.os = 'windows-standard';
    else if (/\blinux\b/.test(source)) parsed.os = 'linux';

    if (/\bnvme\b/.test(source)) parsed.storage_tier = 'nvme';
    else if (/\bssd\b/.test(source)) parsed.storage_tier = 'standard';

    const cpu = source.match(/\b(\d+)\s*(?:v?cpu|core|cores)\b/);
    if (cpu) parsed.vcpu = Number(cpu[1]);

    Array.from(source.matchAll(/(rp\.?\s*)?(\d{1,3}(?:[.,]\d{3})+|\d+(?:[.,]\d+)?)\s*(juta|jt|mio)?\b/g)).some((match) => {
      if (!match[1] && !match[3]) return false;
      let budget = Number(String(match[2]).replace(/[,.]/g, ''));
      if (match[3] && budget < 1000000) budget *= 1000000;
      parsed.budget_monthly_idr = budget;
      return true;
    });

    const ramExplicit = source.match(/\b(\d+)\s*gb\s*ram\b/);
    const ramAfterCore = source.match(/\b\d+\s*(?:v?cpu|core|cores)\s+(\d+)\s*gb\b/);
    if (ramExplicit) parsed.ram_gb = Number(ramExplicit[1]);
    else if (parsed.product_type === 'VIRTUAL_MACHINE' && ramAfterCore) parsed.ram_gb = Number(ramAfterCore[1]);

    Array.from(source.matchAll(/\b(\d+(?:\.\d+)?)\s*(tb|gb)\b/g)).forEach((match) => {
      const gb = match[2] === 'tb' ? Math.round(Number(match[1]) * 1000) : Math.round(Number(match[1]));
      const after = source.slice(match.index + match[0].length, match.index + match[0].length + 16);
      if (match[2] === 'gb' && gb === parsed.ram_gb) return;
      if (match[2] === 'tb' || parsed.budget_monthly_idr || /\b(ssd|sas|nvme|storage|disk)\b/.test(after)) parsed.minimum_storage_gb = gb;
    });

    if (parsed.product_type === 'COLOCATION') {
      const centers = { bogor: 'bogor', idc: 'idc', dci: 'dci', bali: 'bali', drb: 'drb-bddc', bddc: 'drb-bddc' };
      Object.keys(centers).some((key) => {
        if (!source.includes(key)) return false;
        parsed.data_center_id = centers[key];
        return true;
      });
      const rack = source.match(/\b(\d+)\s*u\b/);
      const ampere = source.match(/\b(\d+)\s*(?:a|ampere)\b/);
      if (rack) parsed.rack_u = Number(rack[1]);
      if (ampere) parsed.ampere = Number(ampere[1]);
    }

    if (!parsed.product_type && parsed.minimum_storage_gb && parsed.budget_monthly_idr) {
      parsed.product_type = 'VIRTUAL_MACHINE';
    }

    return parsed;
  }

  function storageCapacity(storage, raidId) {
    const drives = Number(storage.drives || 0);
    const driveGb = Number(storage.drive_gb || 0);
    const rawGb = drives * driveGb;
    let usableGb = Math.floor(rawGb * 0.5);

    if (raidId === 'RAID_5') usableGb = drives > 1 ? (drives - 1) * driveGb : 0;
    if (raidId === 'RAID_6') usableGb = drives > 2 ? (drives - 2) * driveGb : 0;
    if (raidId === 'NONE' || raidId === 'RAID_0') usableGb = rawGb;

    return { rawGb, usableGb };
  }

  function line(label, amount) {
    return { label, amount: Math.max(0, amount || 0) };
  }

  function dedicatedSummary() {
    const card = rates.dedicated_server || {};
    const cpu = option(card.cpu_options, input('dedicatedCpu')?.value);
    const ram = option(card.ram_options, input('dedicatedRam')?.value);
    const storage = option(card.storage_options, input('dedicatedStorage')?.value);
    const raid = option(card.raid_options, input('dedicatedRaid')?.value);
    const capacity = storageCapacity(storage, raid.id);

    const raw = page.querySelector('[data-infra-dedicated-raw]');
    const usable = page.querySelector('[data-infra-dedicated-usable]');
    if (raw) raw.textContent = capacityLabel(capacity.rawGb);
    if (usable) usable.textContent = capacityLabel(capacity.usableGb);

    return {
      recurring: [
        line(cpu.label || 'CPU', cpu.mrc),
        line(ram.label || 'RAM', ram.mrc),
        line(`${storage.label || 'Storage'} with ${raid.label || 'RAID'}`, storage.mrc),
      ],
      oneTime: [],
    };
  }

  function vmSummary() {
    const card = rates.virtual_machine || {};
    const tier = option(card.storage_tiers, input('vmTier')?.value);
    const os = option(card.os_options, input('vmOs')?.value);
    const vcpu = numberValue('vmVcpu', 1);
    const ramGb = numberValue('vmRam', 1);
    const storageGb = numberValue('vmStorage', 1);

    return {
      recurring: [
        line(`${vcpu} vCPU`, vcpu * Number(card.vcpu_price || 0)),
        line(`${ramGb} GB RAM`, ramGb * Number(card.ram_price_per_gb || 0)),
        line(`${storageGb} GB ${tier.label || 'Storage'}`, storageGb * Number(tier.price_per_gb || 0)),
        line(os.label || 'Operating System', os.mrc),
      ],
      oneTime: [],
    };
  }

  function colocationSummary() {
    const dc = option(rates.colocation?.data_centers, input('coloDc')?.value);
    const rackU = numberValue('coloRack', 1);
    const ampere = numberValue('coloAmpere', 1);
    const mrc = rackU * Number(dc.rack_u_mrc || 0) + ampere * Number(dc.ampere_mrc || 0);
    const setupFee = Number(dc.setup_fee || 0);
    const deposit = mrc * Number(dc.deposit_months || 0);

    const setup = page.querySelector('[data-infra-colo-setup]');
    const depositNode = page.querySelector('[data-infra-colo-deposit]');
    if (setup) setup.textContent = money(setupFee);
    if (depositNode) depositNode.textContent = money(deposit);

    return {
      recurring: [
        line(`${rackU}U rack space at ${dc.label || 'Data Center'}`, rackU * Number(dc.rack_u_mrc || 0)),
        line(`${ampere}A power allocation`, ampere * Number(dc.ampere_mrc || 0)),
      ],
      oneTime: [
        line('Setup fee', setupFee),
        line(`${dc.deposit_months || 0} month deposit`, deposit),
      ],
    };
  }

  function customSummary() {
    const service = option(rates.custom_service?.reference_services, input('customService')?.value);
    const name = (input('customName')?.value || service.label || 'Custom Service').trim();
    const quantity = numberValue('customQty', 1);
    const unitPrice = Math.max(0, Number(input('customPrice')?.value) || Number(service.unit_price || 0));
    const total = quantity * unitPrice;
    const billing = input('customBilling')?.value || 'one_time';
    const charge = line(`${quantity} x ${name}`, total);
    const finalPrice = page.querySelector('[data-infra-custom-final]');
    if (finalPrice) finalPrice.textContent = money(total);

    return {
      recurring: billing === 'monthly' ? [charge] : [],
      oneTime: billing === 'monthly' ? [] : [charge],
    };
  }

  function selectedAddons() {
    const addons = rates.shared_addons || [];
    const selected = addonInputs
      .filter((node) => node.checked)
      .map((node) => option(addons, node.value))
      .filter((addon) => addon.id);

    return {
      recurring: selected.filter((addon) => addon.billing === 'monthly').map((addon) => line(addon.label, addon.unit_price)),
      oneTime: selected.filter((addon) => addon.billing !== 'monthly').map((addon) => line(addon.label, addon.unit_price)),
    };
  }

  function pickRamId(requestedGb) {
    const ramOptions = rates.dedicated_server?.ram_options || [];
    if (!requestedGb) return ramOptions[0]?.id;
    return (ramOptions.find((ram) => Number(ram.gb || 0) >= requestedGb) || ramOptions[ramOptions.length - 1] || {}).id;
  }

  function dedicatedMrc(cpu, ram, storage) {
    return Number(cpu.mrc || 0) + Number(ram.mrc || 0) + Number(storage.mrc || 0);
  }

  function recommendDedicated(parsed) {
    const cpuOptions = rates.dedicated_server?.cpu_options || [];
    const ramOptions = rates.dedicated_server?.ram_options || [];
    const storageOptions = rates.dedicated_server?.storage_options || [];
    const requested = Number(parsed.minimum_storage_gb || 0);
    const budget = Number(parsed.budget_monthly_idr || 0);
    const preferredType = parsed.storage_tier === 'nvme' ? 'NVMe' : 'SSD';
    const matches = [];

    cpuOptions.forEach((cpu) => {
      ramOptions.forEach((ram) => {
        if (parsed.ram_gb && Number(ram.gb || 0) < Number(parsed.ram_gb)) return;
        storageOptions.forEach((storage) => {
          const capacity = storageCapacity(storage, 'RAID_1');
          const mrc = dedicatedMrc(cpu, ram, storage);
          if (preferredType && String(storage.type || '').toLowerCase() !== preferredType.toLowerCase()) return;
          if (requested > 0 && capacity.usableGb < requested) return;
          if (budget > 0 && mrc > budget) return;
          matches.push({ cpu, ram, storage, capacity, mrc });
        });
      });
    });

    matches.sort((a, b) => {
      if (requested > 0) return (a.mrc - b.mrc) || (a.capacity.usableGb - b.capacity.usableGb);
      return (b.mrc - a.mrc) || (Number(b.ram.gb || 0) - Number(a.ram.gb || 0)) || (b.capacity.usableGb - a.capacity.usableGb);
    });

    if (!matches.length) {
      return { error: 'No valid configuration found. Please adjust the requirement.', assumptions: [] };
    }

    const best = matches[0];
    return {
      values: {
        dedicatedCpu: best.cpu.id,
        dedicatedRam: best.ram.id,
        dedicatedStorage: best.storage.id,
        dedicatedRaid: 'RAID_1',
      },
      details: [
        ['CPU', best.cpu.label],
        ['RAM', best.ram.label],
        ['Storage', best.storage.label],
        ['Usable', capacityLabel(best.capacity.usableGb)],
        ['MRC', money(best.mrc)],
      ],
      assumptions: [
        !parsed.ram_gb && !budget ? 'RAM defaulted to 64 GB.' : null,
        !parsed.ram_gb && budget ? 'RAM optimized within the monthly budget.' : null,
        !parsed.storage_tier ? 'Storage type defaulted to SSD.' : null,
        'RAID defaulted to RAID 1.',
        budget ? 'CPU optimized within the monthly budget.' : 'CPU defaulted to 2 x Intel Xeon E5-2620 v4.',
      ].filter(Boolean),
    };
  }

  function recommendVm(parsed) {
    const card = rates.virtual_machine || {};
    const tier = option(card.storage_tiers, parsed.storage_tier || 'standard');
    const os = option(card.os_options, parsed.os || 'linux');
    const budget = Number(parsed.budget_monthly_idr || 0);
    const storageGb = Number(parsed.minimum_storage_gb || 100);
    const vcpuChoices = parsed.vcpu ? [Number(parsed.vcpu)] : [2, 4, 8, 16, 32, 64];
    const ramChoices = parsed.ram_gb ? [Number(parsed.ram_gb)] : [4, 8, 16, 32, 64, 128, 256, 512];
    const matches = [];

    vcpuChoices.forEach((vcpu) => {
      ramChoices.forEach((ramGb) => {
        const mrc = vcpu * Number(card.vcpu_price || 0)
          + ramGb * Number(card.ram_price_per_gb || 0)
          + storageGb * Number(tier.price_per_gb || 0)
          + Number(os.mrc || 0);
        if (budget > 0 && mrc > budget) return;
        matches.push({ vcpu, ramGb, storageGb, tier, os, mrc });
      });
    });

    matches.sort((a, b) => (b.mrc - a.mrc) || (b.vcpu - a.vcpu) || (b.ramGb - a.ramGb));
    const best = matches[0];
    if (!best) return { error: 'No valid configuration found. Please adjust the requirement.', assumptions: [] };

    return {
      values: {
        vmVcpu: best.vcpu,
        vmRam: best.ramGb,
        vmStorage: best.storageGb,
        vmTier: best.tier.id,
        vmOs: best.os.id,
      },
      details: [
        ['vCPU', best.vcpu],
        ['RAM', `${best.ramGb} GB`],
        ['Storage', `${best.storageGb} GB ${best.tier.label}`],
        ['OS', best.os.label],
        ['MRC', money(best.mrc)],
      ],
      assumptions: [
        !parsed.vcpu && budget ? 'vCPU optimized within the monthly budget.' : null,
        !parsed.ram_gb && budget ? 'RAM optimized within the monthly budget.' : null,
        !parsed.minimum_storage_gb ? 'Storage defaulted to 100 GB.' : null,
        !parsed.os ? 'Operating system defaulted to Linux.' : null,
      ].filter(Boolean),
    };
  }

  function applySmartRequirement() {
    const parsed = parseRequirement(input('smartRequirement')?.value);
    const assumptions = [];

    if (!parsed.product_type) {
      parsed.product_type = state.product;
      const label = buttons.find((button) => button.getAttribute('data-infra-product') === state.product)?.querySelector('span')?.textContent || 'the selected product';
      assumptions.push(`Product type defaulted to ${label}.`);
    }

    if (!parsed.product_type) {
      setSmartResult('No valid configuration found. Please adjust the requirement.', true, [], []);
      return;
    }

    if (generateButton) {
      generateButton.disabled = true;
      generateButton.textContent = 'Generating';
    }

    if (parsed.product_type === 'DEDICATED_SERVER') {
      const recommended = recommendDedicated(parsed);
      if (recommended.error) {
        setSmartResult(recommended.error, true, [], []);
      } else {
        Object.entries(recommended.values).forEach(([name, value]) => setInput(name, value));
        selectProduct('DEDICATED_SERVER');
        setSmartResult('Generated a dedicated server starting point.', false, assumptions.concat(recommended.assumptions), recommended.details);
      }
    } else if (parsed.product_type === 'VIRTUAL_MACHINE') {
      const recommended = recommendVm(parsed);
      if (recommended.error) {
        setSmartResult(recommended.error, true, [], []);
      } else {
        Object.entries(recommended.values).forEach(([name, value]) => setInput(name, value));
        selectProduct('VIRTUAL_MACHINE');
        setSmartResult('Generated a virtual machine starting point.', false, assumptions.concat(recommended.assumptions), recommended.details);
      }
    } else if (parsed.product_type === 'COLOCATION') {
      setInput('coloDc', parsed.data_center_id || 'bogor');
      setInput('coloRack', parsed.rack_u || 1);
      setInput('coloAmpere', parsed.ampere || 1);
      if (!parsed.data_center_id) assumptions.push('Data center defaulted to Bogor DC.');
      if (!parsed.rack_u) assumptions.push('Rack U defaulted to 1.');
      if (!parsed.ampere) assumptions.push('Ampere defaulted to 1.');
      selectProduct('COLOCATION');
      setSmartResult('Generated a colocation starting point.', false, assumptions, [
        ['Data Center', option(rates.colocation?.data_centers, input('coloDc')?.value).label || 'Bogor DC'],
        ['Rack U', input('coloRack')?.value || 1],
        ['Ampere', input('coloAmpere')?.value || 1],
        ['MRC', mrcTotal?.textContent || money(0)],
      ]);
    }

    if (generateButton) {
      generateButton.disabled = false;
      generateButton.textContent = 'Generate';
    }
  }

  function setSmartResult(message, isError, assumptions, details) {
    if (smartStatus) {
      smartStatus.textContent = message;
      smartStatus.classList.toggle('is-error', Boolean(isError));
    }
    if (assumptionsBox && assumptionList) {
      assumptionList.innerHTML = (assumptions || []).map((item) => `<li>${esc(item)}</li>`).join('');
      assumptionsBox.hidden = !assumptions || assumptions.length === 0;
    }
    if (recommendationBox && recommendationLines) {
      recommendationLines.innerHTML = (details || []).map((item) => `
        <div class="infra-recommendation-line">
          <span>${esc(item[0])}</span>
          <strong>${esc(item[1])}</strong>
        </div>
      `).join('');
      recommendationBox.hidden = !details || details.length === 0;
    }
  }

  function renderLines(target, emptyNode, items) {
    if (!target) return;
    target.innerHTML = items.map((item) => `
      <div class="infra-summary-line">
        <span>${esc(item.label)}</span>
        <strong>${money(item.amount)}</strong>
      </div>
    `).join('');
    if (emptyNode) emptyNode.hidden = items.length > 0;
  }

  function recalculate() {
    const summary = state.product === 'VIRTUAL_MACHINE'
      ? vmSummary()
      : state.product === 'COLOCATION'
        ? colocationSummary()
        : state.product === 'CUSTOM_SERVICE'
          ? customSummary()
          : dedicatedSummary();
    const addons = selectedAddons();
    summary.recurring = summary.recurring.concat(addons.recurring);
    summary.oneTime = summary.oneTime.concat(addons.oneTime);
    const calculatedMrc = summary.recurring.reduce((total, item) => total + item.amount, 0);
    const calculatedOtc = summary.oneTime.reduce((total, item) => total + item.amount, 0);
    const overrideMrc = optionalNumber('overrideMrc');
    const overrideOtc = optionalNumber('overrideOtc');
    const mrc = overrideMrc ?? calculatedMrc;
    const otc = overrideOtc ?? calculatedOtc;
    const taxRate = Number(data.price_book?.tax_rate || 0);
    const tax = (mrc + otc) * taxRate;

    renderLines(recurringLines, recurringEmpty, summary.recurring.filter((item) => item.amount > 0));
    renderLines(oneTimeLines, oneTimeEmpty, summary.oneTime.filter((item) => item.amount > 0));
    if (mrcTotal) mrcTotal.textContent = money(mrc);
    if (otcTotal) otcTotal.textContent = money(otc);
    if (taxTotal) taxTotal.textContent = money(tax);
    if (mrcFinalTotal) mrcFinalTotal.textContent = money(mrc + (mrc * taxRate));
    if (firstInvoiceTotal) firstInvoiceTotal.textContent = money(mrc + otc + tax);
  }

  function selectProduct(selected) {
    state.product = selected || 'DEDICATED_SERVER';

    buttons.forEach((item) => {
      const active = item.getAttribute('data-infra-product') === state.product;
      item.classList.toggle('is-active', active);
      item.setAttribute('aria-pressed', active ? 'true' : 'false');
    });

    panels.forEach((panel) => {
      panel.classList.toggle('is-active', panel.getAttribute('data-infra-panel') === state.product);
    });

    recalculate();
    if (window.lucide) window.lucide.createIcons();
  }

  buttons.forEach((button) => {
    button.addEventListener('click', () => selectProduct(button.getAttribute('data-infra-product')));
  });

  if (generateButton) {
    generateButton.addEventListener('click', applySmartRequirement);
  }

  page.addEventListener('input', recalculate);
  page.addEventListener('change', (event) => {
    const service = rates.custom_service?.reference_services || [];
    if (event.target === input('customService')) {
      const selected = option(service, event.target.value);
      if (input('customName')) input('customName').value = selected.label || '';
      if (input('customPrice')) input('customPrice').value = selected.unit_price || 0;
    }
    recalculate();
  });

  selectProduct(buttons.find((button) => button.classList.contains('is-active'))?.getAttribute('data-infra-product'));
})();
