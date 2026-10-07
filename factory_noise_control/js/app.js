/**
 * Factory Acoustic Monitoring and Material Compliance System
 * Frontend Application Controller (app.js)
 * 
 * Provides:
 * - Dynamic KPI computation and state management
 * - Real-time acoustic telemetry table rendering and multi-criteria filtering
 * - Zone health matrix visualization
 * - Strict client-side validation for Maintenance Dispatch form
 * - Accessible Modal and Toast notification interactions
 */

document.addEventListener('DOMContentLoaded', () => {
  // Ensure mock data is loaded
  if (!window.AcousticData) {
    console.error("Critical: AcousticData store is not initialized.");
    return;
  }

  const { zones, logs, maintenance } = window.AcousticData;

  // --- DOM Element References ---
  const kpiAvgNoiseEl = document.getElementById('kpi-avg-noise');
  const kpiCriticalZonesEl = document.getElementById('kpi-critical-zones');
  const kpiComplianceRateEl = document.getElementById('kpi-compliance-rate');
  const kpiMaintenanceCountEl = document.getElementById('kpi-maintenance-count');

  const zoneGridEl = document.getElementById('zone-grid');
  const tableBodyEl = document.getElementById('acoustic-table-body');
  const searchInputEl = document.getElementById('search-logs-input');
  const filterZoneEl = document.getElementById('filter-zone-select');
  const filterStatusEl = document.getElementById('filter-status-select');

  // Modal & Form Elements
  const modalOverlay = document.getElementById('maintenance-modal');
  const btnOpenModal = document.getElementById('btn-open-maintenance-modal');
  const btnCloseModal = document.getElementById('btn-close-modal');
  const btnCancelModal = document.getElementById('btn-cancel-modal');
  const maintenanceForm = document.getElementById('maintenance-form');
  const formZoneSelect = document.getElementById('maint-zone');

  // Form Fields for validation
  const fieldZone = document.getElementById('maint-zone');
  const fieldTag = document.getElementById('maint-equipment-tag');
  const fieldPriority = document.getElementById('maint-priority');
  const fieldReporter = document.getElementById('maint-reporter');
  const fieldDescription = document.getElementById('maint-description');

  const toastContainer = document.getElementById('toast-container');
  const liveClockEl = document.getElementById('live-system-clock');

  // --- 1. Live System Clock ---
  function updateClock() {
    const now = new Date();
    const formatted = now.toISOString().replace('T', ' ').substring(0, 19) + ' UTC';
    if (liveClockEl) {
      liveClockEl.textContent = formatted;
    }
  }
  setInterval(updateClock, 1000);
  updateClock();

  // --- 2. Populate Zone Dropdowns ---
  function initZoneDropdowns() {
    // Filter dropdown in table controls
    if (filterZoneEl) {
      zones.forEach(z => {
        const opt = document.createElement('option');
        opt.value = z.id;
        opt.textContent = `${z.name} (${z.code})`;
        filterZoneEl.appendChild(opt);
      });
    }

    // Modal select dropdown
    if (formZoneSelect) {
      zones.forEach(z => {
        const opt = document.createElement('option');
        opt.value = z.id;
        opt.textContent = `${z.name} [Limit: ${z.maxLimitDb} dB]`;
        formZoneSelect.appendChild(opt);
      });
    }
  }

  // --- 3. Dynamic KPI Calculation ---
  function refreshKPIs() {
    if (!logs.length) return;

    // 1. Average dB
    const sumDb = logs.reduce((acc, curr) => acc + curr.decibels, 0);
    const avgDb = (sumDb / logs.length).toFixed(1);
    kpiAvgNoiseEl.textContent = avgDb;

    // 2. Critical Breaches (Distinct zones with current readings >= 85 dB)
    const criticalZoneIds = new Set(
      logs.filter(l => l.complianceStatus === 'CRITICAL' || l.decibels >= 85.0).map(l => l.zoneId)
    );
    kpiCriticalZonesEl.textContent = criticalZoneIds.size;

    // 3. Material Compliance Rate
    const compliantCount = logs.filter(l => l.complianceStatus === 'COMPLIANT').length;
    const rate = ((compliantCount / logs.length) * 100).toFixed(1);
    kpiComplianceRateEl.textContent = `${rate}%`;

    // 4. Pending Maintenance Tickets
    const pendingOrders = maintenance.filter(m => m.status !== 'RESOLVED').length;
    kpiMaintenanceCountEl.textContent = pendingOrders;

    // Sidebar badge update
    const navBadge = document.getElementById('sidebar-alert-badge');
    if (navBadge) {
      navBadge.textContent = criticalZoneIds.size;
    }
  }

  // --- 4. Render Zone Health Cards ---
  function renderZoneMatrix() {
    if (!zoneGridEl) return;
    zoneGridEl.innerHTML = '';

    zones.forEach(zone => {
      const zoneLogs = logs.filter(l => l.zoneId === zone.id);
      const latestLog = zoneLogs[zoneLogs.length - 1] || { decibels: 70.0, complianceStatus: 'COMPLIANT' };

      const isCritical = latestLog.decibels >= zone.maxLimitDb;
      const isElevated = latestLog.decibels >= zone.maxLimitDb - 5 && !isCritical;
      
      let badgeClass = 'badge-compliant';
      let statusLabel = 'Normal';
      let fillColor = 'var(--status-compliant)';

      if (isCritical) {
        badgeClass = 'badge-critical';
        statusLabel = 'Critical';
        fillColor = 'var(--status-critical)';
      } else if (isElevated) {
        badgeClass = 'badge-elevated';
        statusLabel = 'Elevated';
        fillColor = 'var(--status-elevated)';
      }

      const card = document.createElement('div');
      card.className = 'zone-box';
      card.innerHTML = `
        <div class="zone-box-top">
          <div>
            <div class="zone-title">${zone.name}</div>
            <div class="zone-subtitle mono">${zone.code} &bull; ${zone.area}</div>
          </div>
          <span class="badge ${badgeClass}">${statusLabel}</span>
        </div>
        <div class="progress-bar">
          <div class="progress-fill" style="width: ${zone.healthScore}%; background: ${fillColor};"></div>
        </div>
        <div class="zone-box-meta">
          <span>Current: <strong class="mono">${latestLog.decibels.toFixed(1)} dB</strong> / Limit: ${zone.maxLimitDb} dB</span>
          <span>Health: ${zone.healthScore}%</span>
        </div>
        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 0.4rem;">
          Dampener: ${zone.dampenerType}
        </div>
      `;
      zoneGridEl.appendChild(card);
    });
  }

  // --- 5. Render Acoustic Compliance Data Table ---
  function renderAcousticTable() {
    if (!tableBodyEl) return;

    const searchTerm = (searchInputEl?.value || '').toLowerCase().trim();
    const zoneFilter = filterZoneEl?.value || 'ALL';
    const statusFilter = filterStatusEl?.value || 'ALL';

    const filteredLogs = logs.filter(log => {
      // Zone match
      if (zoneFilter !== 'ALL' && log.zoneId !== parseInt(zoneFilter, 10)) {
        return false;
      }
      // Status match
      if (statusFilter !== 'ALL' && log.complianceStatus !== statusFilter) {
        return false;
      }
      // Search query match
      if (searchTerm) {
        const matchesTag = log.sensorTag.toLowerCase().includes(searchTerm);
        const matchesZone = log.zoneName.toLowerCase().includes(searchTerm);
        const matchesMaterial = log.materialStatus.toLowerCase().includes(searchTerm);
        if (!matchesTag && !matchesZone && !matchesMaterial) {
          return false;
        }
      }
      return true;
    });

    if (filteredLogs.length === 0) {
      tableBodyEl.innerHTML = `
        <tr>
          <td colspan="7" style="text-align: center; padding: 2rem; color: var(--text-dim);">
            No acoustic telemetry records match the specified filters.
          </td>
        </tr>
      `;
      return;
    }

    tableBodyEl.innerHTML = filteredLogs.map(log => {
      let statusBadge = 'badge-compliant';
      let dbColor = 'var(--status-compliant)';
      let dbBg = 'var(--status-compliant-bg)';

      if (log.complianceStatus === 'CRITICAL') {
        statusBadge = 'badge-critical';
        dbColor = 'var(--status-critical)';
        dbBg = 'var(--status-critical-bg)';
      } else if (log.complianceStatus === 'ELEVATED') {
        statusBadge = 'badge-elevated';
        dbColor = 'var(--status-elevated)';
        dbBg = 'var(--status-elevated-bg)';
      }

      return `
        <tr>
          <td><span class="mono-font" style="font-weight: 600; color: var(--accent-cyan);">${log.sensorTag}</span></td>
          <td>
            <div style="font-weight: 600;">${log.zoneName}</div>
            <div style="font-size: 0.72rem; color: var(--text-dim);">Sensor ID: #${log.id}</div>
          </td>
          <td>
            <span class="db-pill" style="color: ${dbColor}; background: ${dbBg};">
              ${log.decibels.toFixed(1)} dB
            </span>
          </td>
          <td class="mono-font" style="font-size: 0.8rem; color: var(--text-muted);">${log.frequencyHz} Hz</td>
          <td>
            <div style="font-size: 0.82rem;">${log.materialStatus}</div>
          </td>
          <td><span class="badge-pill ${statusBadge}">${log.complianceStatus}</span></td>
          <td class="mono-font" style="font-size: 0.75rem; color: var(--text-dim);">${log.timestamp}</td>
        </tr>
      `;
    }).join('');
  }

  // Event Listeners for Filters
  searchInputEl?.addEventListener('input', renderAcousticTable);
  filterZoneEl?.addEventListener('change', renderAcousticTable);
  filterStatusEl?.addEventListener('change', renderAcousticTable);

  // --- 6. Modal Drawer Controls ---
  function openModal() {
    modalOverlay.classList.add('active');
    fieldZone.focus();
  }

  function closeModal() {
    modalOverlay.classList.remove('active');
    resetFormErrors();
    maintenanceForm.reset();
  }

  btnOpenModal?.addEventListener('click', openModal);
  btnCloseModal?.addEventListener('click', closeModal);
  btnCancelModal?.addEventListener('click', closeModal);

  modalOverlay?.addEventListener('click', (e) => {
    if (e.target === modalOverlay) closeModal();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modalOverlay.classList.contains('active')) {
      closeModal();
    }
  });

  // --- 7. Strict Client-Side Form Validation ---
  function setFieldError(field, message) {
    field.classList.add('is-invalid');
    const feedbackEl = field.nextElementSibling;
    if (feedbackEl && feedbackEl.classList.contains('invalid-feedback')) {
      feedbackEl.textContent = message;
    }
  }

  function clearFieldError(field) {
    field.classList.remove('is-invalid');
  }

  function resetFormErrors() {
    [fieldZone, fieldTag, fieldPriority, fieldReporter, fieldDescription].forEach(f => {
      if (f) clearFieldError(f);
    });
  }

  // Clear errors when the user begins typing/interacting
  [fieldZone, fieldTag, fieldPriority, fieldReporter, fieldDescription].forEach(field => {
    if (field) {
      field.addEventListener('input', () => clearFieldError(field));
      field.addEventListener('change', () => clearFieldError(field));
    }
  });

  function validateMaintenanceForm() {
    let isValid = true;
    resetFormErrors();

    // 1. Zone Selection
    if (!fieldZone.value || fieldZone.value === "") {
      setFieldError(fieldZone, "Please select an affected industrial zone.");
      isValid = false;
    }

    // 2. Equipment / Sensor Tag (format: 3-8 alphanumeric, hyphen, 2-8 alphanumeric)
    // Examples: STAMP-HYD-01, CNC-SPINDLE-4, TURB-GEN-01
    const tagVal = fieldTag.value.trim().toUpperCase();
    const tagRegex = /^[A-Z0-9]{3,10}-[A-Z0-9]{2,10}(-[A-Z0-9]{2,10})?$/;
    if (!tagVal) {
      setFieldError(fieldTag, "Equipment tag is required.");
      isValid = false;
    } else if (!tagRegex.test(tagVal)) {
      setFieldError(fieldTag, "Tag must follow format 'LOC-EQUIP-01' (3-10 chars with hyphens).");
      isValid = false;
    }

    // 3. Priority Selection
    const validPriorities = ['LOW', 'MEDIUM', 'HIGH', 'EMERGENCY'];
    if (!validPriorities.includes(fieldPriority.value)) {
      setFieldError(fieldPriority, "Please designate a valid severity priority.");
      isValid = false;
    }

    // 4. Reporter Name
    const reporterVal = fieldReporter.value.trim();
    if (!reporterVal) {
      setFieldError(fieldReporter, "Reporter name / Employee ID is required.");
      isValid = false;
    } else if (reporterVal.length < 3) {
      setFieldError(fieldReporter, "Reporter name must be at least 3 characters.");
      isValid = false;
    }

    // 5. Issue Description
    const descVal = fieldDescription.value.trim();
    if (!descVal) {
      setFieldError(fieldDescription, "Acoustic issue description is required.");
      isValid = false;
    } else if (descVal.length < 15) {
      setFieldError(fieldDescription, `Description must be at least 15 characters (currently ${descVal.length}).`);
      isValid = false;
    }

    return isValid;
  }

  // --- 8. Form Submission Handler ---
  maintenanceForm?.addEventListener('submit', (e) => {
    // Strictly prevent browser page reloads
    e.preventDefault();

    if (!validateMaintenanceForm()) {
      return;
    }

    // Build new Maintenance Order record
    const selectedZoneId = parseInt(fieldZone.value, 10);
    const selectedZone = zones.find(z => z.id === selectedZoneId);
    const newOrderId = 500 + maintenance.length + 1;

    const newRequest = {
      id: newOrderId,
      zoneId: selectedZoneId,
      equipmentTag: fieldTag.value.trim().toUpperCase(),
      priority: fieldPriority.value,
      issueDescription: fieldDescription.value.trim(),
      reportedBy: fieldReporter.value.trim(),
      status: 'PENDING',
      createdAt: new Date().toISOString().replace('T', ' ').substring(0, 19)
    };

    // Store to active in-memory dataset
    maintenance.unshift(newRequest);

    // If High or Emergency, simulate an elevated noise log alert in telemetry
    if (newRequest.priority === 'EMERGENCY' || newRequest.priority === 'HIGH') {
      const simulatedLog = {
        id: 100 + logs.length + 1,
        sensorTag: `MIC-${newRequest.equipmentTag}`,
        zoneId: selectedZoneId,
        zoneName: selectedZone ? selectedZone.name : "Factory Floor",
        decibels: newRequest.priority === 'EMERGENCY' ? 91.5 : 86.8,
        frequencyHz: 120,
        materialStatus: `Work Order #${newOrderId}: Dispatched`,
        complianceStatus: 'CRITICAL',
        timestamp: newRequest.createdAt
      };
      logs.unshift(simulatedLog);
    }

    // Refresh UI components dynamically
    refreshKPIs();
    renderZoneMatrix();
    renderAcousticTable();

    // Notify user with elegant toast notification
    showToast(`Work Order #${newOrderId} successfully created for ${selectedZone ? selectedZone.code : 'zone'}. Tech team alerted!`, 'success');

    // Close and reset modal
    closeModal();
  });

  // --- 9. Toast Notification Engine ---
  function showToast(message, type = 'info') {
    if (!toastContainer) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type === 'success' ? 'toast-success' : type === 'error' ? 'toast-error' : ''}`;
    toast.innerHTML = `
      <div>${message}</div>
      <button style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1rem;">&times;</button>
    `;

    toast.querySelector('button').addEventListener('click', () => {
      toast.remove();
    });

    toastContainer.appendChild(toast);

    // Auto-remove after 4.5 seconds
    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(100%)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 4500);
  }

  // --- 10. Initial Application Boot ---
  initZoneDropdowns();
  refreshKPIs();
  renderZoneMatrix();
  renderAcousticTable();

  console.log("Factory Acoustic Monitoring & Material Compliance System initialized successfully.");
});
