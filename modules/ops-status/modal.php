<div class="modal-overlay hidden" id="opsModal">

  <div class="modal modal-sm">

    <div class="modal-head">
      <div>
        <div class="modal-title" id="ops-modal-title">Operational Status</div>
        <div class="modal-sub" id="ops-modal-sub">Update live ops information</div>
      </div>

      <button
        type="button"
        id="ops-close-btn"
        class="modal-close"
      >
        <i data-lucide="x"></i>
      </button>
    </div>

    <div class="modal-body">

      <input
        type="hidden"
        id="ops-id"
      >

      <div class="form-group">
        <label class="form-label">Operational Message</label>

        <input
          type="text"
          id="ops-message"
          class="form-input"
          placeholder="Ops status update, e.g. WHMCS ticket queue under monitoring"
        >
      </div>

      <div class="form-group">
        <label class="form-label">Severity</label>

        <select
          id="ops-severity"
          class="form-select"
        >
          <option value="info">Info</option>
          <option value="warning">Warning</option>
          <option value="critical">Critical</option>
          <option value="solved">Solved</option>
        </select>
      </div>

    </div>

    <div class="modal-foot">

      <button
        type="button"
        id="ops-new-btn"
        class="btn btn-ghost"
      >
        Add New
      </button>

      <button
        type="button"
        id="ops-archive-btn"
        class="btn btn-danger"
      >
        Archive
      </button>

      <button
        type="button"
        id="ops-save-btn"
        class="btn btn-primary"
      >
        Save
      </button>

    </div>

  </div>

</div>
