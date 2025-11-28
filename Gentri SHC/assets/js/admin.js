document.querySelectorAll(".nav-link[data-target]").forEach(link => {
  link.addEventListener("click", e => {
    e.preventDefault();
    const target = e.currentTarget.getAttribute("data-target");

    document.querySelectorAll(".page-section").forEach(sec => sec.classList.add("hidden"));
    document.getElementById(target).classList.remove("hidden");

    document.querySelectorAll(".nav-link[data-target]").forEach(l => l.classList.remove("active"));
    e.currentTarget.classList.add("active");

    // Lazy refresh when switching sections
    if (target === 'departments') refreshDepartments();
    if (target === 'staffs') refreshStaffs();
    if (target === 'doctors') refreshDoctors();
    if (target === 'patients') loadPatients();
    if (target === 'queues') refreshQueuePanels();
    if (target === 'dashboard') updateStaffDashboardMetrics();
  });
});

// Simple API helpers
async function apiGet(url) {
  const res = await fetch(url, { credentials: 'same-origin' });
  const json = await res.json().catch(() => ({}));
  if (!res.ok || !json.ok) throw new Error(json.error || `GET ${url} failed`);
  return json.data;
}

async function apiPost(url, data) {
  const body = new URLSearchParams(data || {});
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body,
    credentials: 'same-origin'
  });
  const json = await res.json().catch(() => ({}));
  if (!res.ok || !json.ok) throw new Error(json.error || `POST ${url} failed`);
  return json.data;
}

async function refreshQueuePanels() {
  try {
    const isDoctor = document.body && document.body.classList.contains('doctor');
    const getCookie = (n) => (document.cookie.split('; ').find(r => r.startsWith(n+'='))||'').split('=')[1] || '';
    const doctorDeptId = isDoctor ? Number(getCookie('doctor_department_id') || '0') : 0;
    const departmentsAll = await apiGet('backend/departments.php');
    const departments = (isDoctor && doctorDeptId > 0) ? (departmentsAll || []).filter(d => Number(d.department_id) === doctorDeptId) : departmentsAll;
    const adminContainer = document.querySelector('#queues .queue-panels');
    const staffContainer = document.querySelector('#queues .queue-panels-container');
    const slugify = (name) => (name || '').toLowerCase().replace(/\s+/g,'-');

    const ensureAdminGroup = (name) => {
      if (!adminContainer) return;
      const slug = slugify(name);
      if (document.getElementById(`${slug}-priorityServing`) || document.querySelector(`.queue-dept-group[data-dept="${slug}"]`)) return;
      const wrap = document.createElement('div');
      wrap.className = 'queue-dept-group';
      wrap.setAttribute('data-dept', slug);
      wrap.innerHTML = `
        <h3 class="queue-dept-title">${name}</h3>
        <div class="queue-panel-row">
          <div class="queue-panel" data-lane="${slug}-priority">
            <h4 class="panel-title">Priority Lane</h4>
            <p class="waiting-text">There are <span id="${slug}-priorityWaiting" class="waiting-count">0</span> visitors waiting</p>
            <div class="serving-box">
              <div class="serving-label">Currently serving</div>
              <div id="${slug}-priorityServing" class="serving-display">—</div>
            </div>
            <div class="queue-actions">
              <button class="queue-btn queue-btn-primary invite-next" data-target="${slug}-priority">Invite next</button>
              <button class="queue-btn queue-btn-secondary invite-by-number" data-target="${slug}-priority">Invite by number</button>
              <button class="queue-btn queue-btn-danger remove-by-number" data-target="${slug}-priority">Remove by number</button>
              <button class="queue-btn queue-btn-danger remove-visitors" data-target="${slug}-priority">Remove visitors</button>
            </div>
          </div>
          <div class="queue-panel" data-lane="${slug}-regular">
            <h4 class="panel-title">Regular Lane</h4>
            <p class="waiting-text">There are <span id="${slug}-regularWaiting" class="waiting-count">0</span> visitors waiting</p>
            <div class="serving-box">
              <div class="serving-label">Currently serving</div>
              <div id="${slug}-regularServing" class="serving-display">—</div>
            </div>
            <div class="queue-actions">
              <button class="queue-btn queue-btn-primary invite-next" data-target="${slug}-regular">Invite next</button>
              <button class="queue-btn queue-btn-secondary invite-by-number" data-target="${slug}-regular">Invite by number</button>
              <button class="queue-btn queue-btn-danger remove-by-number" data-target="${slug}-regular">Remove by number</button>
              <button class="queue-btn queue-btn-danger remove-visitors" data-target="${slug}-regular">Remove visitors</button>
            </div>
          </div>
        </div>`;
      adminContainer.appendChild(wrap);
    };

    const staffSlugFor = (name) => { const s = slugify(name); return s === 'animal-bite' ? 'animal' : s; };
    const staffTargetSlugFor = (name) => { const s = slugify(name); return s === 'animal-bite' ? 'animal-bite' : s; };
    const ensureStaffGroup = (name) => {
      if (!staffContainer) return;
      const isDoctor = document.body && document.body.classList.contains('doctor');
      const slug = staffSlugFor(name);
      const targetSlug = staffTargetSlugFor(name);
      if (document.getElementById(`${slug}-priority-serving`) || document.querySelector(`.department-group[data-dept="${slug}"]`)) return;
      const wrap = document.createElement('div');
      wrap.className = 'department-group';
      wrap.setAttribute('data-dept', slug);
      const actionsPriority = isDoctor
        ? `<div class="actions"><button class="btn btn-end" id="${slug}-priority-end-btn">End Session</button></div>`
        : `<div class="actions"><button class="btn invite-next" data-target="${targetSlug}-priority">Invite Next</button><button class="btn invite-by-number" data-target="${targetSlug}-priority">Invite by Number</button></div>`;
      const actionsRegular = isDoctor
        ? `<div class="actions"><button class="btn btn-end" id="${slug}-regular-end-btn">End Session</button></div>`
        : `<div class="actions"><button class="btn invite-next" data-target="${targetSlug}-regular">Invite Next</button><button class="btn invite-by-number" data-target="${targetSlug}-regular">Invite by Number</button></div>`;
      wrap.innerHTML = `
        <h4 class="department-title">${name}</h4>
        <div class="two-column-row">
          <div class="queue-panel" id="${slug}-priority-panel">
            <h5>Priority Lane</h5>
            <p class="queue-count">Waiting: <span id="${slug}-priority-waiting-count">0</span></p>
            <p class="serving">Currently serving: <span id="${slug}-priority-serving">—</span></p>
            ${actionsPriority}
          </div>
          <div class="queue-panel" id="${slug}-regular-panel">
            <h5>Regular Lane</h5>
            <p class="queue-count">Waiting: <span id="${slug}-regular-waiting-count">0</span></p>
            <p class="serving">Currently serving: <span id="${slug}-regular-serving">—</span></p>
            ${actionsRegular}
          </div>
        </div>`;
      staffContainer.appendChild(wrap);
      const groups = Array.from(staffContainer.querySelectorAll('.department-group'))
        .filter(g => (g.querySelector('.department-title')?.textContent || '').trim().toLowerCase() === (name || '').toLowerCase());
      groups.slice(1).forEach(g => g.remove());
    };

    (departments || []).forEach(d => { ensureAdminGroup(d.department_name); ensureStaffGroup(d.department_name); });

    if (isDoctor && doctorDeptId > 0) {
      const assignedName = (departments[0] && departments[0].department_name) ? String(departments[0].department_name) : '';
      document.querySelectorAll('#queues .department-group, #queues .queue-dept-group').forEach(el => {
        const t = (el.querySelector('.department-title, .queue-dept-title')?.textContent || '').trim().toLowerCase();
        if (assignedName && t !== assignedName.toLowerCase()) { el.style.display = 'none'; }
      });
    }

    const queue = await apiGet('backend/list_queue.php' + (isDoctor && doctorDeptId>0 ? `?department_id=${doctorDeptId}` : ''));
    const byDept = {};
    (queue || []).forEach(item => {
      const dept = (item.department_name || '').toLowerCase();
      const lane = (item.priority === 'Priority') ? 'priority' : 'regular';
      byDept[dept] = byDept[dept] || { priority: [], regular: [] };
      byDept[dept][lane].push(item);
    });
    function setText(id, val) { const el = document.getElementById(id); if (el) el.textContent = String(val); }
    (departments || []).forEach(d => {
      const deptNameLower = (d.department_name || '').toLowerCase();
      const lanes = byDept[deptNameLower] || { priority: [], regular: [] };
      const priWaiting = lanes.priority.filter(x => (x.status || '').toLowerCase() === 'waiting').length;
      const regWaiting = lanes.regular.filter(x => (x.status || '').toLowerCase() === 'waiting').length;
      const priServing = lanes.priority.filter(x => (x.status || '').toLowerCase() === 'called').map(x => x.queue_number).sort((a,b)=>a-b)[0] ?? '—';
      const regServing = lanes.regular.filter(x => (x.status || '').toLowerCase() === 'called').map(x => x.queue_number).sort((a,b)=>a-b)[0] ?? '—';
      const slug = deptNameLower.replace(/\s+/g,'-');
      const altSlug = slug === 'animal-bite' ? 'animal' : slug;
      setText(`${slug}-priority-waiting-count`, priWaiting);
      setText(`${slug}-regular-waiting-count`, regWaiting);
      setText(`${slug}-priority-serving`, priServing);
      setText(`${slug}-regular-serving`, regServing);
      setText(`${altSlug}-priority-waiting-count`, priWaiting);
      setText(`${altSlug}-regular-waiting-count`, regWaiting);
      setText(`${altSlug}-priority-serving`, priServing);
      setText(`${altSlug}-regular-serving`, regServing);
      setText(`${slug}-priorityWaiting`, priWaiting);
      setText(`${slug}-regularWaiting`, regWaiting);
      setText(`${slug}-priorityServing`, priServing);
      setText(`${slug}-regularServing`, regServing);
      const calledPriCount = (byDept[deptNameLower]?.priority||[]).filter(x => (x.status||'').toLowerCase()==='called').length;
      const calledRegCount = (byDept[deptNameLower]?.regular||[]).filter(x => (x.status||'').toLowerCase()==='called').length;
      const anyPriWait = (byDept[deptNameLower]?.priority||[]).some(x => (x.status||'').toLowerCase()==='waiting');
      const anyRegWait = (byDept[deptNameLower]?.regular||[]).some(x => (x.status||'').toLowerCase()==='waiting');
      let nextHint = '—';
      if (!anyPriWait && anyRegWait) nextHint = 'Regular';
      else if (anyPriWait && !anyRegWait) nextHint = 'Priority';
      else if (anyPriWait || anyRegWait) nextHint = ((calledPriCount - calledRegCount) >= 2) ? 'Regular' : 'Priority';
      setText(`${slug}-next-hint`, nextHint);
      setText(`${altSlug}-next-hint`, nextHint);
      [document.getElementById(`${slug}-priorityWaiting`), document.getElementById(`${slug}-priority-waiting-count`), document.getElementById(`${altSlug}-priority-waiting-count`)].forEach(el => { if (el) { el.classList.remove('badge-priority'); if (priWaiting > 0) el.classList.add('badge-priority'); } });
      [document.getElementById(`${slug}-regularWaiting`), document.getElementById(`${slug}-regular-waiting-count`), document.getElementById(`${altSlug}-regular-waiting-count`)].forEach(el => { if (el) { el.classList.add('badge-regular'); } });
    });
    const anyPriority = Object.values(byDept).some(v => (v.priority||[]).some(x => (x.status||'').toLowerCase() === 'waiting'));
    try {
      let alert = document.getElementById('priorityAlert');
      if (!alert) {
        alert = document.createElement('div');
        alert.id = 'priorityAlert';
        alert.className = 'priority-alert';
        alert.textContent = 'Priority patients waiting';
        const host = document.querySelector('#queues');
        if (host) host.insertBefore(alert, host.firstChild);
      }
      alert.classList.toggle('show', !!anyPriority);
    } catch(_) {}
    Object.keys(byDept).forEach((deptKey)=>{
      const lanes = byDept[deptKey] || { priority: [], regular: [] };
      const priWaiting = lanes.priority.filter(x => (x.status || '').toLowerCase() === 'waiting').length;
      const regWaiting = lanes.regular.filter(x => (x.status || '').toLowerCase() === 'waiting').length;
      const priServing = lanes.priority.filter(x => (x.status || '').toLowerCase() === 'called').map(x => x.queue_number).sort((a,b)=>a-b)[0] ?? '—';
      const regServing = lanes.regular.filter(x => (x.status || '').toLowerCase() === 'called').map(x => x.queue_number).sort((a,b)=>a-b)[0] ?? '—';
      const slug = deptKey.replace(/\s+/g,'-');
      const altSlug = slug === 'animal-bite' ? 'animal' : slug;
      setText(`${slug}-priority-waiting-count`, priWaiting);
      setText(`${slug}-regular-waiting-count`, regWaiting);
      setText(`${slug}-priority-serving`, priServing);
      setText(`${slug}-regular-serving`, regServing);
      setText(`${altSlug}-priority-waiting-count`, priWaiting);
      setText(`${altSlug}-regular-waiting-count`, regWaiting);
      setText(`${altSlug}-priority-serving`, priServing);
      setText(`${altSlug}-regular-serving`, regServing);
      setText(`${slug}-priorityWaiting`, priWaiting);
      setText(`${slug}-regularWaiting`, regWaiting);
      setText(`${slug}-priorityServing`, priServing);
      setText(`${slug}-regularServing`, regServing);
    });
    const docTbody = document.getElementById('doctorPatientsTableBody');
    if (docTbody) {
      docTbody.innerHTML = '';
      (queue || []).forEach((q) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${q.case_number || ''}</td>
          <td>${q.last_name || ''}</td>
          <td>${q.first_name || ''}</td>
          <td>${q.department_name || ''}</td>
          <td>${q.visit_type || ''}</td>
          <td>${q.client_type || ''}</td>
          <td>${(q.created_at || '').toString().slice(0,10)}</td>
        `;
        docTbody.appendChild(tr);
      });
    }
  } catch (err) {
    console.warn('Failed to refresh queue panels', err);
  }
}

async function updateStaffDashboardMetrics() {
  try {
    const stats = await apiGet('backend/get_queue_stats.php');
    const served = (stats.status_counts || []).find(s => (s.status || '').toLowerCase() === 'completed')?.count || 0;
    const waiting = (stats.status_counts || []).find(s => (s.status || '').toLowerCase() === 'waiting')?.count || 0;
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = String(val); };
    set('patientsServedCount', served);
    set('patientsWaitingCount', waiting);
  } catch (_) {}
}

function setupAgeAutoCalc(){
  const wire = (dobId, ageId) => {
    const dob = document.getElementById(dobId);
    const age = document.getElementById(ageId);
    if (!dob || !age) return;
    const calc = () => {
      const v = dob.value;
      const d = new Date(v);
      if (isNaN(d)) return;
      const t = new Date();
      let a = t.getFullYear() - d.getFullYear();
      const m = t.getMonth() - d.getMonth();
      if (m < 0 || (m === 0 && t.getDate() < d.getDate())) a--;
      age.value = String(Math.max(0, a));
    };
    dob.addEventListener('change', calc);
    dob.addEventListener('input', calc);
    if (dob.value) calc();
  };
  wire('dob','age');            // Add Patient form
  wire('editDateOfBirth','editAge'); // Edit Patient form
}

document.addEventListener('DOMContentLoaded', () => { try { refreshQueuePanels(); updateStaffDashboardMetrics(); setupMaintenanceUI(); setupMaintenanceOverlay(); setupAgeAutoCalc(); } catch (_) {} });

// Wire queue action buttons (Next / Call Again / Skip)
(function setupQueueActions(){
  const deptNameMap = { medical: 'Medical', opd: 'OPD', dental: 'Dental', animal: 'Animal Bite' };
  async function handleQueueAction(deptName, lane, type) {
    try {
      const isDoctor = document.body && document.body.classList.contains('doctor');
      const getCookie = (n) => (document.cookie.split('; ').find(r => r.startsWith(n+'='))||'').split('=')[1] || '';
      const doctorDeptId = isDoctor ? Number(getCookie('doctor_department_id') || '0') : 0;
      const queue = await apiGet('backend/list_queue.php' + (isDoctor && doctorDeptId>0 ? `?department_id=${doctorDeptId}` : ''));
      const filtered = (queue || []).filter(q => ((q.department_name || '').trim().toLowerCase() === deptName.trim().toLowerCase()) && (lane === 'priority' ? q.priority === 'Priority' : q.priority === 'Regular'));
      const rank = (q)=> ((q.priority === 'Emergency' || q.priority === 'Priority') ? 1 : 2);
      const priWaiting = (queue || []).filter(q => ((q.department_name||'').trim().toLowerCase()===deptName.trim().toLowerCase()) && (q.status||'').toLowerCase()==='waiting' && (q.priority==='Emergency' || q.priority==='Priority')).sort((a,b)=> (new Date(a.created_at)-new Date(b.created_at)) || ((a.queue_number||0)-(b.queue_number||0)));
      const regWaiting = (queue || []).filter(q => ((q.department_name||'').trim().toLowerCase()===deptName.trim().toLowerCase()) && (q.status||'').toLowerCase()==='waiting' && (q.priority==='Regular')).sort((a,b)=> (new Date(a.created_at)-new Date(b.created_at)) || ((a.queue_number||0)-(b.queue_number||0)));
      const called = (queue || []).filter(q => ((q.department_name||'').trim().toLowerCase()===deptName.trim().toLowerCase()) && (q.status||'').toLowerCase()==='called').sort((a,b)=> (a.queue_number||0)-(b.queue_number||0));
      const calledPriCount = called.filter(q => (q.priority==='Emergency' || q.priority==='Priority')).length;
      const calledRegCount = called.filter(q => q.priority==='Regular').length;
      const chooseNext = () => {
        if (!priWaiting.length && regWaiting.length) return regWaiting[0];
        if (priWaiting.length && !regWaiting.length) return priWaiting[0];
        if (!priWaiting.length && !regWaiting.length) return null;
        return (calledPriCount - calledRegCount) >= 2 ? regWaiting[0] : priWaiting[0];
      };
      let target = null;
      if (type === 'end_session') {
        const current = called[0] || null;
        if (current) {
          const docIdDone = Number((document.cookie.split('; ').find(r => r.startsWith('doctor_id='))||'').split('=')[1] || localStorage.getItem('currentDoctorId') || 0);
          if (docIdDone > 0) {
            await apiPost('backend/update_queue.php', { queue_id: current.queue_id, action: 'assign_doctor', assigned_doctor_id: docIdDone });
          }
          await apiPost('backend/update_queue.php', { queue_id: current.queue_id, action: 'complete' });
        }
        const nextCandidate = chooseNext();
        if (nextCandidate) {
          const next = nextCandidate;
          await apiPost('backend/update_queue.php', { queue_id: next.queue_id, action: 'call' });
          const docIdNext = Number((document.cookie.split('; ').find(r => r.startsWith('doctor_id='))||'').split('=')[1] || localStorage.getItem('currentDoctorId') || 0);
          if (docIdNext > 0) {
            await apiPost('backend/update_queue.php', { queue_id: next.queue_id, action: 'assign_doctor', assigned_doctor_id: docIdNext });
          }
          try { const bc = new BroadcastChannel('queue-updates'); bc.postMessage({ type: 'queue_update' }); bc.close(); } catch(_) {}
          await refreshQueuePanels();
          showToast('Session ended. Next patient called.', 'success');
          return;
        } else {
          try { const bc = new BroadcastChannel('queue-updates'); bc.postMessage({ type: 'queue_update' }); bc.close(); } catch(_) {}
          await refreshQueuePanels();
          showToast('No more patients in the queue', 'success');
          return;
        }
      }
      if (type === 'next') target = chooseNext();
      else if (type === 'call_again') target = called[0] || chooseNext();
      else if (type === 'skip') target = chooseNext();
      if (!target) { showToast('No patient available for this lane', 'error'); return; }
      if (type === 'skip') {
        await apiPost('backend/update_queue.php', { queue_id: target.queue_id, action: 'cancel' });
      } else {
        await apiPost('backend/update_queue.php', { queue_id: target.queue_id, action: 'call' });
        const docId = Number((document.cookie.split('; ').find(r => r.startsWith('doctor_id='))||'').split('=')[1] || localStorage.getItem('currentDoctorId') || 0);
        if (docId > 0) {
          await apiPost('backend/update_queue.php', { queue_id: target.queue_id, action: 'assign_doctor', assigned_doctor_id: docId });
        }
      }
      try { const bc = new BroadcastChannel('queue-updates'); bc.postMessage({ type: 'queue_update' }); bc.close(); } catch(_) {}
      await refreshQueuePanels();
      showToast(type === 'skip' ? 'Skipped' : 'Updated queue', 'success');
    } catch (err) {
      console.warn('Queue action failed', err);
      showToast('Failed to update queue: ' + (err?.message || 'Unknown error'), 'error', 2500);
    }
  }
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.queue-panel .actions .btn');
    if (!btn) return;
    const panel = btn.closest('.queue-panel');
    const pid = panel && panel.id ? panel.id : '';
    let slug = '', lane = '';
    let m = pid.match(/^([a-z-]+)-(priority|regular)-panel$/i);
    if (m) { slug = m[1]; lane = m[2].toLowerCase(); }
    else {
      m = pid.match(/^([a-z-]+)-merged-panel$/i);
      if (!m) return;
      slug = m[1];
      lane = 'priority';
    }
    const deptName = deptNameMap[slug] || slug;
    let type = '';
    if (btn.id.endsWith('-next-btn')) type = 'next';
    else if (btn.id.endsWith('-call-btn')) type = 'call_again';
    else if (btn.id.endsWith('-end-btn')) type = 'end_session';
    else if (btn.id.endsWith('-skip-btn')) type = 'skip';
    if (type) handleQueueAction(deptName, lane, type);
  });

  // Staff: invite next / invite by number
  document.addEventListener('click', async (e) => {
    const nextBtn = e.target.closest('.invite-next');
    const byNumBtn = e.target.closest('.invite-by-number');
    const removeBtn = e.target.closest('.remove-by-number');
    const targetAttr = (nextBtn || byNumBtn || removeBtn)?.getAttribute('data-target');
    if (!targetAttr) return;
    const parts = targetAttr.split('-');
    const lane = parts.pop();
    const slug = parts.join('-').toLowerCase();
    const deptName = { 'medical':'Medical', 'opd':'OPD', 'dental':'Dental', 'animal-bite':'Animal Bite' }[slug] || slug;
    try {
      const queue = await apiGet('backend/list_queue.php');
      const filtered = (queue || []).filter(q => ((q.department_name || '').trim().toLowerCase() === deptName.trim().toLowerCase()) && (lane === 'priority' ? q.priority === 'Priority' : q.priority === 'Regular'));
      const rank = (q)=> ((q.priority === 'Emergency' || q.priority === 'Priority') ? 1 : 2);
      let target = null;
      let action = '';
      if (nextBtn) {
        const priWaiting = (queue || []).filter(q => ((q.department_name||'').trim().toLowerCase()===deptName.trim().toLowerCase()) && (q.status||'').toLowerCase()==='waiting' && (q.priority==='Emergency' || q.priority==='Priority')).sort((a,b)=> (new Date(a.created_at)-new Date(b.created_at)) || ((a.queue_number||0)-(b.queue_number||0)));
        const regWaiting = (queue || []).filter(q => ((q.department_name||'').trim().toLowerCase()===deptName.trim().toLowerCase()) && (q.status||'').toLowerCase()==='waiting' && (q.priority==='Regular')).sort((a,b)=> (new Date(a.created_at)-new Date(b.created_at)) || ((a.queue_number||0)-(b.queue_number||0)));
        const calledDept = (queue || []).filter(q => ((q.department_name||'').trim().toLowerCase()===deptName.trim().toLowerCase()) && (q.status||'').toLowerCase()==='called');
        const calledPriCount = calledDept.filter(q => (q.priority==='Emergency' || q.priority==='Priority')).length;
        const calledRegCount = calledDept.filter(q => q.priority==='Regular').length;
        const chooseNext = () => { if (!priWaiting.length && regWaiting.length) return regWaiting[0]; if (priWaiting.length && !regWaiting.length) return priWaiting[0]; if (!priWaiting.length && !regWaiting.length) return null; return (calledPriCount - calledRegCount) >= 2 ? regWaiting[0] : priWaiting[0]; };
        target = chooseNext();
        action = 'call';
      } else if (byNumBtn) {
        const numStr = prompt('Enter ticket number to invite');
        const num = parseInt(numStr || '', 10);
        if (!num || isNaN(num)) { showToast('Invalid number', 'error', 2000); return; }
        target = filtered.find(q => Number(q.queue_number) === num) || null;
        action = 'call';
      } else if (removeBtn) {
        const numStr = prompt('Enter ticket number to remove');
        const num = parseInt(numStr || '', 10);
        if (!num || isNaN(num)) { showToast('Invalid number', 'error', 2000); return; }
        target = filtered.find(q => Number(q.queue_number) === num) || null;
        action = 'cancel';
      }
      if (!target) { showToast('No matching patient found', 'error', 2000); return; }
      await apiPost('backend/update_queue.php', { queue_id: target.queue_id, action });
      try { const bc = new BroadcastChannel('queue-updates'); bc.postMessage({ type: 'queue_update' }); bc.close(); } catch(_) {}
      await refreshQueuePanels();
      if (nextBtn && target && target.priority === 'Regular') { showToast('Serving a regular to maintain 2:1 ratio', 'success'); }
      showToast(action === 'cancel' ? 'Removed' : 'Invited', 'success');
    } catch (err) {
      console.warn('Staff queue action failed', err);
      showToast('Failed: ' + (err?.message || 'Unknown error'), 'error', 2500);
    }
  });
})();

function setupMaintenanceUI(){
  const logTbody = document.getElementById('maintenanceLogTableBody');
  function appendLog(action, status){
    if (!logTbody) return;
    const tr = document.createElement('tr');
    const d = new Date();
    tr.innerHTML = `<td>${d.toISOString().slice(0,10)}</td><td>${action}</td><td>${status}</td>`;
    logTbody.prepend(tr);
  }
  async function postJSON(url, payload){ return apiPost(url, payload); }
  const diag = document.getElementById('runDiagnosticsBtn');
  const upd = document.getElementById('applyUpdateBtn');
  const bkp = document.getElementById('backupDataBtn');
  const restBtn = document.getElementById('restoreDataBtn');
  const schedEl = document.getElementById('backupScheduleInfo');
  const sel = document.getElementById('maintenanceSelect');
  function render(on){ if (sel) sel.value = on ? '1' : '0'; }
  (async ()=>{ try { const status = await apiGet('backend/maintenance_status.php'); render(!!(status && status.maintenance)); } catch(_){} })();
  (async ()=>{ try { const s = await apiGet('backend/backup_status.php'); if (schedEl) { schedEl.textContent = `Next scheduled backup: ${s?.next_due || '—'} (every 2 weeks)`; } } catch(_){} })();
  async function applySelection(value){
    try {
      const onVal = value === '1' ? 1 : 0; // ON enables, OFF disables
      await postJSON('backend/toggle_maintenance.php', { on: onVal });
      const status = await apiGet('backend/maintenance_status.php');
      const actual = !!(status && status.maintenance);
      render(actual); // force dropdown to actual server state
      showToast(actual ? 'Maintenance enabled' : 'Maintenance disabled', actual ? 'error' : 'success');
      try { const bc = new BroadcastChannel('maintenance-updates'); bc.postMessage({ on: actual }); bc.close(); } catch(_) {}
    } catch(e){
      // On failure, re-check and force UI to server state to avoid mismatch
      try {
        const status = await apiGet('backend/maintenance_status.php');
        render(!!(status && status.maintenance));
      } catch(_) {}
      showToast(e?.message || 'Toggle failed','error');
    }
  }
  if (diag) diag.addEventListener('click', async ()=>{ try { await postJSON('backend/run_diagnostics.php',{}); showToast('Diagnostics completed','success'); appendLog('Diagnostics','Success'); } catch(e){ showToast('Diagnostics failed','error'); appendLog('Diagnostics','Issues Found'); } });
  if (upd) upd.addEventListener('click', async ()=>{ try { await postJSON('backend/apply_update.php',{}); showToast('Update installed','success'); appendLog('Security Patch','Installed'); } catch(e){ showToast('Update failed','error'); appendLog('Update','Failed'); } });
  if (bkp) bkp.addEventListener('click', async ()=>{ try { const r = await postJSON('backend/backup_data.php',{ mode:'incremental' }); showToast('Backup completed','success'); appendLog('System Backup', r?.message || 'Success'); const s = await apiGet('backend/backup_status.php'); if (schedEl) { schedEl.textContent = `Next scheduled backup: ${s?.next_due || '—'} (every 2 weeks)`; } } catch(e){ showToast('Backup failed','error'); appendLog('Backup','Failed'); } });
  if (restBtn) restBtn.addEventListener('click', ()=>{
    const overlay = document.createElement('div');
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;z-index:10000';
    overlay.innerHTML = '<div class="card" style="background:#fff;color:#111;width:min(520px,calc(100% - 32px));border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.25);border:1px solid #e6e8eb;padding:20px;text-align:center">'+
      '<h3 style="margin:0 0 8px;color:#1f2937">Restore from backup</h3>'+
      '<p style="margin:0 0 12px;color:#6b7280">Select a backup file (.json or .json.gz) to restore.</p>'+
      '<input type="file" id="restorePicker" accept=".json,.json.gz" style="margin:8px 0 12px" />'+
      '<div style="display:flex;gap:10px;justify-content:center"><button id="restoreConfirm" class="btn-add-staff">Restore</button><button id="restoreCancel" class="btn-add-staff">Cancel</button></div>'+
    '</div>';
    document.body.appendChild(overlay);
    const picker = overlay.querySelector('#restorePicker');
    overlay.querySelector('#restoreCancel').addEventListener('click', ()=> overlay.remove());
    overlay.querySelector('#restoreConfirm').addEventListener('click', async ()=>{
      try {
        if (!picker || !picker.files || !picker.files[0]) { showToast('Select a backup file first','error'); return; }
        const fd = new FormData(); fd.append('file', picker.files[0]);
        const r = await fetch('backend/restore_data.php', { method: 'POST', body: fd });
        const j = await r.json().catch(()=>null);
        if (j && j.ok) { showToast('Restore completed','success'); appendLog('Restore','Success'); overlay.remove(); }
        else { showToast(j?.error || 'Restore failed','error'); appendLog('Restore','Failed'); }
      } catch(e){ showToast('Restore failed','error'); appendLog('Restore','Failed'); }
    });
  });
  if (sel) sel.addEventListener('change', (e)=> applySelection(e.target.value));
}

function setupMaintenanceOverlay(){
  const isAdmin = document.body.classList.contains('admin');
  if (isAdmin) return;
  let overlay = document.getElementById('maintenanceOverlay');
  if (!overlay){
    overlay = document.createElement('div');
    overlay.id='maintenanceOverlay'; overlay.className='maintenance-overlay';
    overlay.innerHTML = `<div class="card"><h3>System Maintenance</h3><p>The system is currently undergoing maintenance. Please try again later.</p></div>`;
    document.body.appendChild(overlay);
  }
  async function refresh(){
    try {
      const status = await apiGet('backend/maintenance_status.php');
      overlay.style.display = (status && status.maintenance) ? 'flex' : 'none';
    } catch(_){ overlay.style.display='none'; }
  }
  try { const bc = new BroadcastChannel('maintenance-updates'); bc.onmessage = refresh; } catch(_) {}
  refresh();
}

// ---------------- Departments wiring ----------------
async function refreshDepartments() {
  const tbody = document.querySelector('#departments table tbody');
  if (!tbody) return;
  try {
    const result = await apiGet('backend/list_departments.php');
    const rows = result || [];
    tbody.innerHTML = '';
    rows.forEach((r, idx) => {
      const tr = document.createElement('tr');
      tr.dataset.departmentId = String(r.department_id);
      tr.innerHTML = `
        <td>${idx + 1}</td>
        <td>${r.department_name || ''}</td>
        <td>${r.department_description || ''}</td>
        <td>${formatDateFrom(r.created_at) || ''}</td>
        <td>${formatDateFrom(r.updated_at) || ''}</td>
        <td class="actions-cell">
          <button class="btn-icon btn-edit" aria-label="Edit"><i class="fas fa-edit"></i></button>
          <button class="btn-icon btn-delete" aria-label="Delete"><i class="fas fa-trash-alt"></i></button>
        </td>
      `;
      tbody.appendChild(tr);
    });
    updateDashboardCounts();
  } catch (err) {
    console.error(err);
    // keep existing rows if any
  }
}

function formatDateFrom(val) {
  if (!val) return '';
  // Expecting MySQL timestamp, keep as is or convert to MM/DD/YYYY
  try {
    const d = new Date(val);
    if (!isNaN(d)) return formatDate(d);
  } catch (_) {}
  return val;
}

// Delegate edit/delete actions for departments
document.addEventListener('click', async (e) => {
  const t = e.target;
  if (!(t instanceof HTMLElement)) return;
  // Find if within departments section
  const actionBtn = t.closest('#departments .btn-icon');
  if (!actionBtn) return;
  const tr = t.closest('tr');
  const id = tr && tr.dataset.departmentId ? Number(tr.dataset.departmentId) : 0;
  if (id <= 0) return;

  if (actionBtn.classList.contains('btn-edit')) {
    // Open modal with prefilled fields for department
    openEditRecordModal({
      kind: 'department',
      id,
      fields: {
        department_name: tr.children[1]?.textContent?.trim() || '',
        department_description: tr.children[2]?.textContent?.trim() || ''
      }
    });
    return;
  }

  if (actionBtn.classList.contains('btn-delete')) {
    openDeleteModal('department', id, async () => {
      await apiPost('backend/delete_department.php', { department_id: id });
      await refreshDepartments();
    });
    return;
  }
});

// Select-all handling for Staff List
document.addEventListener('change', (e) => {
  const t = e.target;
  if (!(t instanceof HTMLInputElement)) return;
  if (t.id === 'selectAll') {
    document.querySelectorAll('#staffs .row-select').forEach(cb => {
      if (cb instanceof HTMLInputElement) cb.checked = t.checked;
    });
  }
});

// Inline actions: edit/delete via direct icons
document.addEventListener('click', (e) => {
  const el = e.target;
  if (!(el instanceof HTMLElement)) return;
  // No dropdowns anymore; inline handlers are defined below.
});

function toggleForm(formId) {
  document.getElementById(formId).classList.toggle("hidden");
}

document.addEventListener("click", (e) => {
  const target = e.target;
  if (!(target instanceof HTMLElement)) return;

  // Skip generic inline edit/delete for wired sections (Departments, Staffs, Doctors, Patients)
  const wiredSection = target.closest('#departments, #staffs, #doctors, #patients');
  if (wiredSection) return;

  // Handle clicks on the button or the inner <i> icon
  const editBtn = target.closest("button.btn-edit");
  const deleteBtn = target.closest("button.btn-delete");

  if (editBtn) {
    const row = editBtn.closest("tr");
    if (row) openEditModal(row);
    return;
  }

  if (deleteBtn) {
    const row = deleteBtn.closest("tr");
    if (row && confirm("Delete this record?")) {
      row.remove();
    }
    return;
  }

  if (target.classList.contains("modal-close") || target.classList.contains("modal-cancel")) {
    const modalOverlay = target.closest(".modal-overlay");
    if (modalOverlay && modalOverlay.id === "editModal") closeEditModal();
    if (modalOverlay && modalOverlay.id === "addPatientModal") closeAddPatientModal();
    if (modalOverlay && modalOverlay.id === "addStaffModal") closeAddStaffModal();
    if (modalOverlay && modalOverlay.id === "addDepartmentModal") closeAddDepartmentModal();
    if (modalOverlay && modalOverlay.id === "addDoctorModal") closeAddDoctorModal();
    if (modalOverlay && modalOverlay.id === "adminProfileModal") closeAdminProfileModal();
    if (modalOverlay && modalOverlay.id === "staffProfileModal") closeStaffProfileModal();
  }
});

// Toast utility for logout messages
function showToast(message, type = 'success', duration = 1500) {
  let container = document.getElementById('toastContainer');
  if (!container) { container = document.createElement('div'); container.id = 'toastContainer'; container.className = 'toast-container'; document.body.appendChild(container); }
  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.textContent = message;
  container.appendChild(el);
  requestAnimationFrame(() => el.classList.add('show'));
  setTimeout(() => {
    el.classList.remove('show');
    setTimeout(() => { el.remove(); if (!container.children.length) container.remove(); }, 200);
  }, duration);
}

// Delete success dialog with check icon
function showDeleteSuccessDialog({ title = 'Successfully Deleted', subtitle = 'The record has been permanently removed', duration = 1500 } = {}) {
  const overlay = document.createElement('div');
  overlay.className = 'delete-success-overlay';
  overlay.innerHTML = `
    <div class="delete-success-card">
      <div class="delete-success-icon">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="10" stroke="#34a853" stroke-width="2" fill="none" opacity="0.15"/>
          <path d="M7 12l3 3 7-7" stroke="#34a853" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h2>${title}</h2>
      <p>${subtitle}</p>
    </div>`;
  document.body.appendChild(overlay);
  requestAnimationFrame(() => overlay.classList.add('show'));
  setTimeout(() => {
    overlay.classList.remove('show');
    setTimeout(() => overlay.remove(), 200);
  }, duration);
}

// Centered logout dialog
function showLogoutDialog({ title = 'You have been logged out', subtitle = 'Thank you', redirect = 'login.php', delay = 1200 } = {}) {
  const overlay = document.createElement('div');
  overlay.className = 'logout-overlay';
  overlay.innerHTML = `
    <div class="logout-card">
      <div class="logout-icon">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="10" stroke="#34a853" stroke-width="2" fill="none" opacity="0.15"/>
          <path d="M7 12l3 3 7-7" stroke="#34a853" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h2>${title}</h2>
      <p>${subtitle}</p>
    </div>`;
  document.body.appendChild(overlay);
  requestAnimationFrame(() => overlay.classList.add('show'));
  setTimeout(() => {
    window.location.href = redirect;
  }, delay);
}

// Logout handler: show toast and redirect to unified login
(function setupLogout(){
  // Force all logout anchors to point to login.php as a non-JS fallback
  document.querySelectorAll('[data-action="logout"]').forEach(a => { try { a.setAttribute('href', 'login.php'); } catch(_) {} });

  const links = document.querySelectorAll('.nav-link[data-action="logout"], .item[data-action="logout"]');
  links.forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const redirect = 'login.php';
      showLogoutDialog({ title: 'You have been logged out', subtitle: 'Thank you', redirect, delay: 1200 });
    });
  });
})();

// User Menu: populate name, toggle dropdown and submenus, and handle logout
(function setupUserMenu(){
  const container = document.querySelector('.user-menu');
  if (!container) return;

  const nameEl = container.querySelector('#userNameLabel');
  const currentPage = (window.location.pathname.split('/').pop() || '').toLowerCase();
  const defaultRole = currentPage.includes('staff') ? 'Staff' : (currentPage.includes('doctor') ? 'Doctor' : 'Admin');
  const storedName = localStorage.getItem('currentUserName');
  if (nameEl) nameEl.textContent = storedName || defaultRole;

  const toggle = container.querySelector('.user-toggle');
  const dropdown = container.querySelector('.user-dropdown');
  if (toggle && dropdown) {
    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      dropdown.classList.toggle('hidden');
      toggle.setAttribute('aria-expanded', dropdown.classList.contains('hidden') ? 'false' : 'true');
    });
    document.addEventListener('click', () => dropdown.classList.add('hidden'));
  }

  // Submenu toggles
  container.querySelectorAll('.has-submenu > .item').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const wrap = btn.parentElement;
      if (!wrap) return;
      wrap.classList.toggle('open');
    });
  });

  // Logout in dropdown
  container.querySelectorAll('[data-action="logout"]').forEach(el => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      const redirect = 'login.php';
      showLogoutDialog({ title: 'You have been logged out', subtitle: 'Thank you', redirect, delay: 1200 });
    });
  });

  // Profile trigger for Admin
  const profileTrigger = container.querySelector('[data-action="profile"]');
  if (profileTrigger) {
    profileTrigger.addEventListener('click', (e) => {
      e.preventDefault();
      const current = (window.location.pathname.split('/').pop() || '').toLowerCase();
      if (current === 'admin_index.html') openAdminProfileModal();
      else if (current === 'staff_index.html') openStaffProfileModal();
    });
  }
})();

// Queue update broadcast listener
try {
  const bc = new BroadcastChannel('queue-updates');
  bc.onmessage = (ev) => {
    if (ev && ev.data && ev.data.type === 'queue_update') {
      try { refreshQueuePanels(); updateStaffDashboardMetrics(); } catch (_) { window.location.reload(); }
    }
  };
  // Also auto-refresh every 20s while queues section is visible
  setInterval(() => {
    const queuesVisible = !document.getElementById('queues')?.classList.contains('hidden');
    const dashboardVisible = !document.getElementById('dashboard')?.classList.contains('hidden');
    if (queuesVisible) { try { refreshQueuePanels(); } catch (_) {} }
    if (dashboardVisible) { try { updateStaffDashboardMetrics(); } catch (_) {} }
  }, 20000);
} catch (_) {}

// ----- Admin Profile Modal -----
function setAdminProfileReadOnly(readonly) {
  ['adminFirstName','adminLastName','adminEmail'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.readOnly = readonly;
      el.disabled = false; // keep focusable but readOnly controls editing
    }
  });
  const saveBtn = document.getElementById('adminProfileSaveBtn');
  if (saveBtn) saveBtn.disabled = readonly;
}

function populateAdminProfile() {
  const storedName = localStorage.getItem('currentUserName') || '';
  const [firstGuess, lastGuess] = storedName.split(' ');
  const firstName = localStorage.getItem('adminFirstName') || firstGuess || '';
  const lastName = localStorage.getItem('adminLastName') || lastGuess || '';
  const email = localStorage.getItem('adminEmail') || '';
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
  set('adminFirstName', firstName);
  set('adminLastName', lastName);
  set('adminEmail', email);
}

function openAdminProfileModal() {
  const modal = document.getElementById('adminProfileModal');
  if (!modal) return;
  populateAdminProfile();
  setAdminProfileReadOnly(false);
  modal.classList.remove('hidden');

  const editBtn = document.getElementById('adminProfileEditBtn');
  if (editBtn) {
    editBtn.onclick = () => setAdminProfileReadOnly(false);
  }

  const form = document.getElementById('adminProfileForm');
  if (form) {
    form.onsubmit = (e) => {
      e.preventDefault();
      const fn = document.getElementById('adminFirstName')?.value.trim() || '';
      const ln = document.getElementById('adminLastName')?.value.trim() || '';
      const em = document.getElementById('adminEmail')?.value.trim() || '';
      if (!fn || !ln || !em) { showToast('First, Last, and Email are required.', 'error', 2500); return; }
      try {
        localStorage.setItem('adminFirstName', fn);
        localStorage.setItem('adminLastName', ln);
        localStorage.setItem('adminEmail', em);
        // Update display name for header label
        localStorage.setItem('currentUserName', `${fn} ${ln}`.trim());
      } catch (_) {}
      setAdminProfileReadOnly(false);
      showToast('Profile updated', 'success', 1500);
      closeAdminProfileModal();
      // Refresh header label if present
      const nameEl = document.getElementById('userNameLabel');
      if (nameEl) nameEl.textContent = `${fn} ${ln}`.trim() || 'Admin';
    };
  }

  // Close handlers
  modal.addEventListener('click', (e) => { if (e.target === modal) closeAdminProfileModal(); });
}

function closeAdminProfileModal() {
  const modal = document.getElementById('adminProfileModal');
  if (modal) modal.classList.add('hidden');
}

// ----- Staff Profile Modal -----
function setStaffProfileReadOnly(readonly) {
  const ids = ['staffFirstName','staffLastName','staffMiddleName','staffEmail','staffAddress','staffContactNumber','staffCity','staffProvince'];
  ids.forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    if (el.tagName === 'SELECT') {
      el.disabled = readonly;
    } else {
      el.readOnly = readonly;
      el.disabled = false;
    }
  });
  const saveBtn = document.getElementById('staffProfileSaveBtn');
  if (saveBtn) saveBtn.disabled = readonly;
}

function populateStaffProfile() {
  const storedName = localStorage.getItem('currentUserName') || '';
  const parts = storedName.split(' ');
  const firstGuess = parts[0] || '';
  const lastGuess = parts.slice(1).join(' ') || '';
  const get = (k, d='') => localStorage.getItem(k) || d;
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
  set('staffFirstName', get('staffFirstName', firstGuess));
  set('staffLastName',  get('staffLastName',  lastGuess));
  set('staffMiddleName',get('staffMiddleName',''));
  set('staffEmail',     get('staffEmail',''));
  set('staffAddress',  get('staffAddress',''));
  set('staffContactNumber', get('staffContactNumber',''));
  set('staffCity',     get('staffCity',''));
  // Province defaults to Cavite; fall back to legacy 'staffState' if present
  set('staffProvince', get('staffProvince', get('staffState','Cavite')) || 'Cavite');
}

function openStaffProfileModal() {
  const modal = document.getElementById('staffProfileModal');
  if (!modal) return;
  populateStaffProfile();
  setStaffProfileReadOnly(false);
  modal.classList.remove('hidden');

  const editBtn = document.getElementById('staffProfileEditBtn');
  if (editBtn) editBtn.onclick = () => setStaffProfileReadOnly(false);

  const form = document.getElementById('staffProfileForm');
  if (form) {
    form.onsubmit = (e) => {
      e.preventDefault();
      const fn = document.getElementById('staffFirstName')?.value.trim() || '';
      const ln = document.getElementById('staffLastName')?.value.trim() || '';
      const mn = document.getElementById('staffMiddleName')?.value.trim() || '';
      const em = document.getElementById('staffEmail')?.value.trim() || '';
      const addr = document.getElementById('staffAddress')?.value.trim() || '';
      const contact = document.getElementById('staffContactNumber')?.value.trim() || '';
      const city = document.getElementById('staffCity')?.value.trim() || '';
      const province = document.getElementById('staffProvince')?.value.trim() || 'Cavite';
      if (!fn || !ln || !em) { showToast('First, Last, and Email are required.', 'error', 2500); return; }
      try {
        localStorage.setItem('staffFirstName', fn);
        localStorage.setItem('staffLastName', ln);
        localStorage.setItem('staffMiddleName', mn);
        localStorage.setItem('staffEmail', em);
        localStorage.setItem('staffAddress', addr);
        localStorage.setItem('staffContactNumber', contact);
        localStorage.setItem('staffCity', city);
        localStorage.setItem('staffProvince', province);
        localStorage.setItem('currentUserName', `${fn} ${ln}`.trim());
      } catch (_) {}
      setStaffProfileReadOnly(false);
      showToast('Profile updated', 'success', 1500);
      closeStaffProfileModal();
      const nameEl = document.getElementById('userNameLabel');
      if (nameEl) nameEl.textContent = `${fn} ${ln}`.trim() || 'Staff';
    };
  }

  modal.addEventListener('click', (e) => { if (e.target === modal) closeStaffProfileModal(); });
}

function closeStaffProfileModal() {
  const modal = document.getElementById('staffProfileModal');
  if (modal) modal.classList.add('hidden');
}

let currentEditRow = null;
let currentEditCtx = null;
function openEditRecordModal(ctx) {
  // ctx: { kind: 'department'|'staff'|'doctor', id: number, fields: object }
  currentEditCtx = ctx;
  const fieldsWrap = document.querySelector('#editModal .modal-fields');
  if (!fieldsWrap) return;
  fieldsWrap.innerHTML = '';

  const modal = document.getElementById('editModal');
  const title = modal?.querySelector('.modal-header h3');
  if (title) title.textContent = `Edit ${ctx.kind.charAt(0).toUpperCase() + ctx.kind.slice(1)}`;

  const addText = (label, id, val='') => {
    const group = document.createElement('div'); group.className = 'form-group';
    const lab = document.createElement('label'); lab.htmlFor = id; lab.textContent = label;
    const input = document.createElement('input'); input.type = 'text'; input.id = id; input.value = val || '';
    group.appendChild(lab); group.appendChild(input); fieldsWrap.appendChild(group);
  };

  const addEmail = (val='') => addText('Email', 'editEmail', val || '');

  if (ctx.kind === 'department') {
    addText('Department Name', 'editDeptName', ctx.fields.department_name);
    addText('Description', 'editDeptDesc', ctx.fields.department_description);
  } else if (ctx.kind === 'staff') {
    addText('Last Name', 'editLastName', ctx.fields.last_name);
    addText('First Name', 'editFirstName', ctx.fields.first_name);
    addText('Middle Name', 'editMiddleName', ctx.fields.middle_name);
    addEmail(ctx.fields.email);
  } else if (ctx.kind === 'doctor') {
    addText('Last Name', 'editDocLastName', ctx.fields.last_name);
    addText('First Name', 'editDocFirstName', ctx.fields.first_name);
    addText('Middle Name', 'editDocMiddleName', ctx.fields.middle_name);
    addEmail(ctx.fields.email);
    // Department select
    const group = document.createElement('div'); group.className = 'form-group';
    const lab = document.createElement('label'); lab.htmlFor = 'editDocDepartment'; lab.textContent = 'Department';
    const sel = document.createElement('select'); sel.id = 'editDocDepartment';
    group.appendChild(lab); group.appendChild(sel); fieldsWrap.appendChild(group);
    // Load department options
    (async () => {
      try {
        const result = await apiGet('backend/list_departments.php');
        const deps = result || [];
        sel.innerHTML = '<option value="">Select department</option>' + deps.map(d => `<option value="${d.department_id}">${d.department_name}</option>`).join('');
        // Attempt to select the current dept by name if present
        const curName = ctx.fields.department_name || '';
        if (curName) {
          const opt = Array.from(sel.options).find(o => o.textContent === curName);
          if (opt) sel.value = opt.value;
        }
      } catch (err) {
        console.warn('Failed to load departments', err);
      }
    })();
  }

  if (modal) modal.classList.remove('hidden');
}

function closeEditModal() {
  const modal = document.getElementById("editModal");
  if (modal) modal.classList.add("hidden");
  currentEditRow = null;
}

// Add Patient modal helpers
function openAddPatientModal() {
  const modal = document.getElementById("addPatientModal");
  if (modal) modal.classList.remove("hidden");
  loadPatientDepartmentOptions();
  const caseInput = document.getElementById('caseNumber');
  if (caseInput) { caseInput.readOnly = true; caseInput.value = generateCaseNumber(); }
  // Ensure age auto-calculation is wired when opening the modal
  try { setupAgeAutoCalc(); } catch (_) {}
}

function closeAddPatientModal() {
  const modal = document.getElementById("addPatientModal");
  if (modal) modal.classList.add("hidden");
}

// Edit Patient modal helpers
async function openEditPatientModal(patientId) {
  try {
    const patients = await apiGet('backend/list_patients.php');
    const patient = patients.find(p => p.patient_id == patientId);
    if (!patient) { showToast('Patient not found', 'error', 2000); return; }
    document.getElementById('editPatientId').value = patient.patient_id;
    document.getElementById('editCaseNumber').value = patient.case_number || '';
    document.getElementById('editPhilhealthId').value = patient.philhealth_id || '';
    document.getElementById('editLastNameFull').value = patient.last_name || '';
    document.getElementById('editFirstNameFull').value = patient.first_name || '';
    document.getElementById('editMiddleNameFull').value = patient.middle_name || '';
    document.getElementById('editExtensionName').value = patient.extension_name || '';
    document.getElementById('editAge').value = patient.age || '';
    document.getElementById('editDateOfBirth').value = patient.date_of_birth || '';
    document.getElementById('editSex').value = patient.sex || '';
    document.getElementById('editPhone').value = patient.phone || '';
    document.getElementById('editAddress').value = patient.address || '';
    document.getElementById('editEmergencyContact').value = patient.emergency_contact || '';
    document.getElementById('editEmergencyPhone').value = patient.emergency_phone || '';
    const modal = document.getElementById('editPatientModal');
    if (modal) modal.classList.remove('hidden');
    try { setupAgeAutoCalc(); } catch (_) {}
  } catch (error) {
    console.error('Error loading patient data:', error);
    showToast('Failed to load patient data', 'error', 2500);
  }
}

function closeEditPatientModal() {
  const modal = document.getElementById("editPatientModal");
  if (modal) modal.classList.add("hidden");
}

// Add Staff modal helpers
function openAddStaffModal() {
  const modal = document.getElementById("addStaffModal");
  if (modal) modal.classList.remove("hidden");
}

function closeAddStaffModal() {
  const modal = document.getElementById("addStaffModal");
  if (modal) modal.classList.add("hidden");
}

// Close Add Staff modal when clicking the overlay background
const addStaffModalEl = document.getElementById('addStaffModal');
if (addStaffModalEl) {
  addStaffModalEl.addEventListener('click', (e) => {
    if (e.target === addStaffModalEl) {
      closeAddStaffModal();
    }
  });
}

// Expose opener globally for inline button handlers
if (typeof window !== 'undefined') {
  window.openAddStaffModal = openAddStaffModal;
}

// Add Department modal helpers
function openAddDepartmentModal() {
  const modal = document.getElementById('addDepartmentModal');
  if (modal) modal.classList.remove('hidden');
}
function closeAddDepartmentModal() {
  const modal = document.getElementById('addDepartmentModal');
  if (modal) modal.classList.add('hidden');
}
const addDepartmentModalEl = document.getElementById('addDepartmentModal');
if (addDepartmentModalEl) {
  addDepartmentModalEl.addEventListener('click', (e) => {
    if (e.target === addDepartmentModalEl) closeAddDepartmentModal();
  });
}
if (typeof window !== 'undefined') {
  window.openAddDepartmentModal = openAddDepartmentModal;
}

// Add Doctor modal helpers
function openAddDoctorModal() {
  const modal = document.getElementById('addDoctorModal');
  if (modal) modal.classList.remove('hidden');
  // Populate department options when opening the doctor modal
  loadDepartmentOptions();
}
function closeAddDoctorModal() {
  const modal = document.getElementById('addDoctorModal');
  if (modal) modal.classList.add('hidden');
}
const addDoctorModalEl = document.getElementById('addDoctorModal');
if (addDoctorModalEl) {
  addDoctorModalEl.addEventListener('click', (e) => {
    if (e.target === addDoctorModalEl) closeAddDoctorModal();
  });
}
if (typeof window !== 'undefined') {
  window.openAddDoctorModal = openAddDoctorModal;
}

// Close Add Patient modal when clicking the overlay background
const addPatientModalEl = document.getElementById('addPatientModal');
if (addPatientModalEl) {
  addPatientModalEl.addEventListener('click', (e) => {
    if (e.target === addPatientModalEl) {
      closeAddPatientModal();
    }
  });
}

// Expose Add Patient modal opener globally for inline HTML triggers
if (typeof window !== 'undefined') {
  window.openAddPatientModal = openAddPatientModal;
}

// Close Edit Patient modal when clicking the overlay background
const editPatientModalEl = document.getElementById('editPatientModal');
if (editPatientModalEl) {
  editPatientModalEl.addEventListener('click', (e) => {
    if (e.target === editPatientModalEl) {
      closeEditPatientModal();
    }
  });
}

// Handle Edit Patient modal form submit
const editPatientForm = document.getElementById('editPatientForm');
if (editPatientForm) {
  editPatientForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const patientId = document.getElementById('editPatientId').value;
    const formData = {
      patient_id: patientId,
      philhealth_id: document.getElementById('editPhilhealthId').value.trim(),
      first_name: document.getElementById('editFirstNameFull').value.trim(),
      last_name: document.getElementById('editLastNameFull').value.trim(),
      middle_name: document.getElementById('editMiddleNameFull').value.trim(),
      extension_name: document.getElementById('editExtensionName').value.trim(),
      age: document.getElementById('editAge').value,
      date_of_birth: document.getElementById('editDateOfBirth').value,
      sex: document.getElementById('editSex').value,
      phone: document.getElementById('editPhone').value.trim(),
      address: document.getElementById('editAddress').value.trim(),
      emergency_contact: document.getElementById('editEmergencyContact').value.trim(),
      emergency_phone: document.getElementById('editEmergencyPhone').value.trim()
    };
    if (!formData.first_name || !formData.last_name || !formData.age || !formData.date_of_birth || !formData.sex) { showToast('Please complete First Name, Last Name, Age, Date of Birth, and Sex.', 'error', 3000); return; }
    try {
      await apiPost('backend/update_patient.php', formData);
      closeEditPatientModal();
      loadPatients();
      showToast('Patient updated successfully', 'success', 2000);
    } catch (error) {
      console.error('Error updating patient:', error);
      showToast('Failed to update patient: ' + error.message, 'error', 2500);
    }
  });
}

// Load patients from backend
async function loadPatients() {
  try {
    const patients = await apiGet('backend/list_patients.php');
    const tbody = document.getElementById('patientsTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    patients.forEach((patient, index) => {
      const tr = document.createElement('tr');
      tr.setAttribute('data-patient-id', patient.patient_id);
      tr.innerHTML = `
        <td>${index + 1}</td>
        <td>${patient.last_name || ''}</td>
        <td>${patient.first_name || ''}</td>
        <td>${patient.middle_name || ''}</td>
        <td>${patient.department_name || ''}</td>
        <td>${patient.visit_type || ''}</td>
        <td>${patient.client_type || ''}</td>
        <td class="actions-cell">
          <button class="btn-icon btn-edit" aria-label="Edit"><i class="fas fa-edit"></i></button>
          <button class="btn-icon btn-delete" aria-label="Delete"><i class="fas fa-trash-alt"></i></button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  } catch (error) {
    console.error('Error loading patients:', error);
  }
}

// Handle Add Patient modal form submit -> append to patient list
const addPatientForm = document.getElementById('addPatientForm');
if (addPatientForm) {
  addPatientForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const last = document.getElementById('lastNameFull')?.value.trim() || '';
    const first = document.getElementById('firstNameFull')?.value.trim() || '';
    const middle = document.getElementById('middleNameFull')?.value.trim() || '';
    let departmentId = document.getElementById('department')?.value.trim() || '';
    const visit = document.getElementById('visitType')?.value.trim() || '';
    const priority = document.getElementById('clientType')?.value.trim() || '';
    const sexVal = document.querySelector('input[name="sex"]:checked')?.value || '';
    const dobVal = document.getElementById('dob')?.value || '';
    let ageVal = document.getElementById('age')?.value || '';

    if (!last || !first || !departmentId || !visit || !priority) { showToast('Please complete First Name, Last Name, Department, Visit Type, and Client Type.', 'error', 3000); return; }
    if (!sexVal) { showToast('Please select Sex.', 'error', 2500); return; }
    if (!dobVal && !ageVal) { showToast('Please enter Date of Birth or Age.', 'error', 2500); return; }
    const pmhBoxes = Array.from(document.querySelectorAll('input[name="pmh[]"], input[name="pmh"]'));
    const anyChecked = pmhBoxes.some(x => x.checked);
    const pmhOthersVal = (document.getElementById('pmhOthers')?.value || '').trim();
    if (!anyChecked && pmhOthersVal === '') { showToast('Review of systems form is required.', 'error', 3500); return; }

    try {
      if (!/^\d+$/.test(departmentId)) {
        departmentId = await resolveDepartmentIdIfNeeded(departmentId);
        if (!departmentId) { throw new Error('Invalid department selection. Please pick a valid department.'); }
      }
      if (!ageVal && dobVal) {
        const d = new Date(dobVal);
        const today = new Date();
        let a = today.getFullYear() - d.getFullYear();
        const m = today.getMonth() - d.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < d.getDate())) a--;
        ageVal = String(a);
      }
      const patientData = {
        first_name: first,
        last_name: last,
        middle_name: middle,
        department_id: departmentId,
        visit_type: visit,
        client_type: priority,
        age: ageVal,
        sex: sexVal,
        philhealth_id: document.getElementById('philHealthId')?.value || '',
        extension_name: document.getElementById('extensionName')?.value || '',
        date_of_birth: dobVal
      };

      const resp = await apiPost('backend/add_patient.php', patientData);
      const shouldPrint = !!document.getElementById('printTicket')?.checked;
      
      closeAddPatientModal();
      addPatientForm.reset();
      
      if (shouldPrint) {
        try {
          const data = resp?.data || resp || {};
          const w = window.open('', '_blank', 'width=480,height=640');
          if (w && data.queue_number) {
            const dt = new Date();
            w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Queue Ticket</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#f7f9ff;margin:0;padding:24px} .ticket{max-width:360px;margin:0 auto;background:#fff;border:1px solid #e6e9f0;border-radius:16px;box-shadow:0 12px 28px rgba(17,24,39,0.12);padding:18px;text-align:center} .num{font-size:92px;font-weight:800;letter-spacing:8px;margin:8px 0;color:#111} .meta{margin-top:8px;color:#374151;font-size:14px} .meta .pill{display:inline-block;margin:2px 4px;padding:6px 10px;border-radius:999px;background:#eef2ff;color:#1e3a8a} .footer{margin-top:14px;font-size:12px;color:#6b7280} @media print{body{background:#fff;padding:0} .ticket{box-shadow:none;border:0}}</style></head><body><div class="ticket"><div class="num">${String(data.queue_number||'').padStart(3,'0')}</div><div class="meta"><span class="pill">Case: ${data.case_number||'-'}</span><span class="pill">Priority: ${data.priority||'Regular'}</span></div><div class="footer">${dt.toLocaleDateString()} ${dt.toLocaleTimeString()}</div></div></body></html>`);
            w.document.close();
            setTimeout(()=>{ try{ w.focus(); w.print(); }catch(_){} try{ w.close(); }catch(_){} }, 300);
          }
        } catch(_) {}
      }
      try { const bc = new BroadcastChannel('queue-updates'); bc.postMessage({ type: 'queue_update' }); bc.close(); } catch(_) {}
      
      // Reload patients to show the new addition
      await loadPatients();
      updateDashboardCounts();
      showToast('Patient added successfully.', 'success', 2000);
      
    } catch (error) {
      console.error('Error adding patient:', error);
      showToast(error.message || 'Error adding patient. Please try again.', 'error', 3000);
    }
  });
}

function generateCaseNumber() {
  const d = new Date();
  const y = d.getFullYear();
  const m = String(d.getMonth()+1).padStart(2,'0');
  const day = String(d.getDate()).padStart(2,'0');
  const hh = String(d.getHours()).padStart(2,'0');
  const mm = String(d.getMinutes()).padStart(2,'0');
  const ss = String(d.getSeconds()).padStart(2,'0');
  const rnd = Math.floor(Math.random()*1000).toString().padStart(3,'0');
  return `CN-${y}${m}${day}-${hh}${mm}${ss}-${rnd}`;
}

const editForm = document.getElementById("editForm");
if (editForm) {
  editForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!currentEditCtx) { closeEditModal(); return; }
    try {
      if (currentEditCtx.kind === 'department') {
        const name = document.getElementById('editDeptName')?.value.trim() || '';
        const desc = document.getElementById('editDeptDesc')?.value.trim() || '';
        await apiPost('backend/update_department.php', { department_id: currentEditCtx.id, department_name: name, department_description: desc });
        await refreshDepartments();
      } else if (currentEditCtx.kind === 'staff') {
        const ln = document.getElementById('editLastName')?.value.trim() || '';
        const fn = document.getElementById('editFirstName')?.value.trim() || '';
        const mn = document.getElementById('editMiddleName')?.value.trim() || '';
        const em = document.getElementById('editEmail')?.value.trim() || '';
        await apiPost('backend/update_staff.php', { staff_id: currentEditCtx.id, last_name: ln, first_name: fn, middle_name: mn, email: em });
        await refreshStaffs();
      } else if (currentEditCtx.kind === 'doctor') {
        const ln = document.getElementById('editDocLastName')?.value.trim() || '';
        const fn = document.getElementById('editDocFirstName')?.value.trim() || '';
        const mn = document.getElementById('editDocMiddleName')?.value.trim() || '';
        const em = document.getElementById('editEmail')?.value.trim() || '';
        const dep = document.getElementById('editDocDepartment')?.value || '';
        await apiPost('backend/update_doctor.php', { doctor_id: currentEditCtx.id, last_name: ln, first_name: fn, middle_name: mn, email: em, department_id: dep });
        await refreshDoctors();
      }
      closeEditModal();
    } catch (err) {
      showToast(err.message || 'Update failed', 'error', 2500);
    }
  });
}

function updateDashboardCounts() {
  const getCount = (sel) => {
    const tbody = document.querySelector(`${sel} tbody`);
    return tbody ? tbody.querySelectorAll('tr').length : 0;
  };
  const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = String(val); };
  set('deptCount', getCount('#departments'));
  set('docCount', getCount('#doctors'));
  set('staffCount', getCount('#staffs'));
  set('patientCount', getCount('#patients'));
  // Average waiting time is not yet computed; leave as em dash
  const avgEl = document.getElementById('avgWaitTimeCount');
  if (avgEl && !avgEl.textContent) avgEl.textContent = '—';
}

function observeCounts() {
  ['#departments','#doctors','#staffs','#patients'].forEach(sel => {
    const tbody = document.querySelector(`${sel} tbody`);
    if (!tbody) return;
    const obs = new MutationObserver(() => updateDashboardCounts());
    obs.observe(tbody, { childList: true });
  });
}

updateDashboardCounts();
observeCounts();

function formatDate(d = new Date()) {
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  const yyyy = d.getFullYear();
  return `${mm}/${dd}/${yyyy}`;
}

const deptForm = document.getElementById('deptForm');
if (deptForm) {
  deptForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('deptName').value.trim();
    const desc = document.getElementById('deptDesc').value.trim();
    if (!name || !desc) { showToast('Department name and description are required', 'error', 2500); return; }
    try {
      await apiPost('backend/add_department.php', { department_name: name, department_description: desc });
      deptForm.reset();
      closeAddDepartmentModal();
      await refreshDepartments();
      showToast('Department added', 'success', 1500);
    } catch (err) {
      showToast(err.message || 'Add department failed', 'error', 2500);
    }
  });
}

// Initial load
refreshDepartments();

// ---------------- Staffs wiring ----------------
async function refreshStaffs() {
  const tbody = document.querySelector('#staffs table tbody');
  if (!tbody) return;
  try {
    const rows = await apiGet('backend/list_staffs.php');
    tbody.innerHTML = '';
    rows.forEach((r, idx) => {
      const tr = document.createElement('tr');
      tr.dataset.staffId = String(r.user_id);
      tr.innerHTML = `
        <td>${idx + 1}</td>
        <td>${r.last_name || ''}</td>
        <td>${r.first_name || ''}</td>
        <td>${r.middle_name || ''}</td>
        <td>${r.email || ''}</td>
        <td>********</td>
        <td class="actions-cell">
          <button class="btn-icon btn-edit" aria-label="Edit"><i class="fas fa-edit"></i></button>
          <button class="btn-icon btn-delete" aria-label="Delete"><i class="fas fa-trash-alt"></i></button>
        </td>
      `;
      tbody.appendChild(tr);
    });
    updateDashboardCounts();
  } catch (err) {
    console.error(err);
  }
}

// Delegate edit/delete actions for staffs
document.addEventListener('click', async (e) => {
  const t = e.target;
  if (!(t instanceof HTMLElement)) return;
  const actionBtn = t.closest('#staffs .btn-icon');
  if (!actionBtn) return;
  const tr = t.closest('tr');
  const id = tr && tr.dataset.staffId ? Number(tr.dataset.staffId) : 0;
  if (id <= 0) return;

  if (actionBtn.classList.contains('btn-edit')) {
    openEditRecordModal({
      kind: 'staff',
      id,
      fields: {
        last_name: tr.children[1]?.textContent?.trim() || '',
        first_name: tr.children[2]?.textContent?.trim() || '',
        middle_name: tr.children[3]?.textContent?.trim() || '',
        email: tr.children[4]?.textContent?.trim() || ''
      }
    });
    return;
  }

  if (actionBtn.classList.contains('btn-delete')) {
    openDeleteModal('staff', id, async () => {
      await apiPost('backend/delete_staff.php', { staff_id: id });
      await refreshStaffs();
    });
    return;
  }
});

const staffForm = document.getElementById('staffForm');
if (staffForm) {
  staffForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const last = document.getElementById('lastName').value.trim();
    const first = document.getElementById('firstName').value.trim();
    const middle = document.getElementById('middleName').value.trim();
    const email = document.getElementById('email').value.trim();
    const pass = document.getElementById('staffPassword')?.value.trim() || '';
    const pass2 = document.getElementById('staffPasswordConfirm')?.value.trim() || '';
    if (!last || !first || !email) { showToast('Last, First, and Email are required', 'error', 2500); return; }
    if (pass || pass2) { if (pass !== pass2) { showToast('Passwords do not match', 'error', 2500); return; } }
    try {
      await apiPost('backend/add_staff.php', { lastName: last, firstName: first, middleName: middle, email, password: pass });
      staffForm.reset();
      closeAddStaffModal();
      await refreshStaffs();
      showToast('Staff added', 'success', 1500);
    } catch (err) {
      showToast(err.message || 'Add staff failed', 'error', 2500);
    }
  });
}

// Initial load
refreshStaffs();
refreshDoctors();

// Load department options into the doctor form select
async function loadDepartmentOptions() {
  const sel = document.getElementById('docDepartment');
  if (!sel) return;
  try {
    const departments = await apiGet('backend/list_departments.php');
    const current = sel.value;
    sel.innerHTML = '<option value="">Select department</option>' + departments.map(d => `<option value="${d.department_id}">${d.department_name}</option>`).join('');
    if (current) sel.value = current;
  } catch (err) {
    console.warn('Failed to load departments', err);
  }
}

// Load department options into patient form
async function loadPatientDepartmentOptions() {
  const sel = document.getElementById('department');
  if (!sel) return;
  try {
    const departments = await apiGet('backend/list_departments.php');
    const current = sel.value;
    sel.innerHTML = '<option value="">Select department</option>' + departments.map(d => `<option value="${d.department_id}">${d.department_name}</option>`).join('');
    if (current) sel.value = current;
  } catch (err) {
    console.warn('Failed to load departments', err);
  }
}

async function resolveDepartmentIdIfNeeded(val) {
  if (!val) return '';
  if (/^\d+$/.test(val)) return val;
  try {
    const depts = await apiGet('backend/list_departments.php');
    const m = depts.find(d => (d.department_name || '').toLowerCase() === val.toLowerCase());
    return m ? String(m.department_id) : '';
  } catch (_) { return ''; }
}

// ---------------- Doctors wiring ----------------
async function refreshDoctors() {
  const tbody = document.querySelector('#doctors table tbody');
  if (!tbody) return;
  try {
    const rows = await apiGet('backend/list_doctors.php');
    tbody.innerHTML = '';
    rows.forEach((r, idx) => {
      const tr = document.createElement('tr');
      tr.dataset.doctorId = String(r.user_id);
      tr.innerHTML = `
        <td>${idx + 1}</td>
        <td>${r.last_name || ''}</td>
        <td>${r.first_name || ''}</td>
        <td>${r.middle_name || ''}</td>
        <td>${r.email || ''}</td>
        <td>${r.department_name || ''}</td>
        <td>********</td>
        <td class="actions-cell">
          <button class="btn-icon btn-edit" aria-label="Edit"><i class="fas fa-edit"></i></button>
          <button class="btn-icon btn-delete" aria-label="Delete"><i class="fas fa-trash-alt"></i></button>
        </td>
      `;
      tbody.appendChild(tr);
    });
    updateDashboardCounts();
  } catch (err) {
    console.error(err);
  }
}

// Delegate edit/delete actions within Doctors section
document.addEventListener('click', async (e) => {
  const t = e.target;
  if (!(t instanceof HTMLElement)) return;
  const actionBtn = t.closest('#doctors .btn-icon');
  if (!actionBtn) return;
  const tr = t.closest('tr');
  const id = tr && tr.dataset.doctorId ? Number(tr.dataset.doctorId) : 0;
  if (id <= 0) return;

  if (actionBtn.classList.contains('btn-edit')) {
    openEditRecordModal({
      kind: 'doctor',
      id,
      fields: {
        last_name: tr.children[1]?.textContent?.trim() || '',
        first_name: tr.children[2]?.textContent?.trim() || '',
        middle_name: tr.children[3]?.textContent?.trim() || '',
        email: tr.children[4]?.textContent?.trim() || '',
        department_name: tr.children[5]?.textContent?.trim() || ''
      }
    });
    return;
  }

  if (actionBtn.classList.contains('btn-delete')) {
    openDeleteModal('doctor', id, async () => {
      await apiPost('backend/delete_doctor.php', { doctor_id: id });
      await refreshDoctors();
    });
    return;
  }
});

// Delegate edit/delete actions for patients
document.addEventListener('click', async (e) => {
  const t = e.target;
  if (!(t instanceof HTMLElement)) return;
  // Find if within patients section
  const actionBtn = t.closest('#patients .btn-icon');
  if (!actionBtn) return;
  const tr = t.closest('tr');
  if (!tr) return;

  const patientId = tr.getAttribute('data-patient-id');
  if (!patientId) return;

  if (actionBtn.classList.contains('btn-edit')) {
    // Open edit patient modal
    await openEditPatientModal(patientId);
    return;
  }

  if (actionBtn.classList.contains('btn-delete')) {
    // Delete patient from database
    openDeleteModal('patient', patientId, async () => {
      try {
        await apiPost('backend/delete_patient.php', { patient_id: patientId });
        tr.remove();
        updateDashboardCounts();
      } catch (error) {
        throw new Error('Failed to delete patient: ' + error.message);
      }
    });
    return;
  }
});

const doctorForm = document.getElementById('doctorForm');
if (doctorForm) {
  doctorForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const last = document.getElementById('docLastName').value.trim();
    const first = document.getElementById('docFirstName').value.trim();
    const middle = document.getElementById('docMiddleName').value.trim();
    const email = document.getElementById('docEmail').value.trim();
    const departmentId = (document.getElementById('docDepartment')?.value || '').trim();
    const pass = document.getElementById('docPassword')?.value.trim() || '';
    const pass2 = document.getElementById('docPasswordConfirm')?.value.trim() || '';
    if (!last || !first) { showToast('Last and First name are required', 'error', 2500); return; }
    if (pass || pass2) { if (pass !== pass2) { showToast('Passwords do not match', 'error', 2500); return; } }
    try {
      await apiPost('backend/add_doctor.php', { docLastName: last, docFirstName: first, docMiddleName: middle, email, departmentId: departmentId, password: pass });
      doctorForm.reset();
      closeAddDoctorModal();
      await refreshDoctors();
      showToast('Doctor added', 'success', 1500);
    } catch (err) {
      showToast(err.message || 'Add doctor failed', 'error', 2500);
    }
  });
}

const patientForm = document.getElementById('patientForm');
if (patientForm) {
  patientForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const section = patientForm.closest('section');
    const table = section ? section.querySelector('table') : null;
    if (!table) return;
    const tbody = table.querySelector('tbody');
    const index = (tbody.querySelectorAll('tr').length) + 1;
    const last = document.getElementById('patLastName').value.trim();
    const first = document.getElementById('patFirstName').value.trim();
    const middle = document.getElementById('patMiddleName').value.trim();
    const dept = document.getElementById('patDepartment').value.trim();
    const visit = document.getElementById('patVisitType').value.trim();
    const priority = document.getElementById('patPriority').value.trim();
    if (!last || !first || !dept || !visit || !priority) return;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${index}</td>
      <td>${last}</td>
      <td>${first}</td>
      <td>${middle}</td>
      <td>${dept}</td>
      <td>${visit}</td>
      <td>${priority}</td>
      <td class="actions-cell">
        <button class="btn-icon btn-edit" aria-label="Edit"><i class="fas fa-edit"></i></button>
        <button class="btn-icon btn-delete" aria-label="Delete"><i class="fas fa-trash-alt"></i></button>
      </td>
    `;
    tbody.appendChild(tr);
    patientForm.reset();
  patientForm.classList.add('hidden');
  updateDashboardCounts();
  });
}

// --- Queue management (Priority & Regular lanes) ---
function pad3(n) {
  return String(Math.max(0, Number(n) || 0)).padStart(3, '0');
}

function laneEls(lane) {
  return {
    waiting: document.getElementById(`${lane}Waiting`),
    serving: document.getElementById(`${lane}Serving`)
  };
}

document.addEventListener('click', (e) => {
  const t = e.target;
  if (!(t instanceof HTMLElement)) return;

  if (t.classList.contains('invite-next')) {
    const lane = t.dataset.target;
    const { waiting, serving } = laneEls(lane);
    if (!waiting || !serving) return;
    let w = parseInt(waiting.textContent, 10) || 0;
    let s = parseInt(serving.textContent, 10) || 0;
    if (w > 0) {
      s += 1;
      w -= 1;
      serving.textContent = pad3(s);
      waiting.textContent = String(w);
    }
  }

  if (t.classList.contains('invite-by-number')) {
    const lane = t.dataset.target;
    const { serving } = laneEls(lane);
    if (!serving) return;
    const input = prompt('Enter queue number to invite:');
    if (input == null) return;
    const num = parseInt(input, 10);
    if (Number.isNaN(num) || num < 0) { showToast('Please enter a valid non-negative number.', 'error', 2000); return; }
    serving.textContent = pad3(num);
  }

  if (t.classList.contains('remove-visitors')) {
    const lane = t.dataset.target;
    const { waiting } = laneEls(lane);
    if (!waiting) return;
    if (confirm('Clear all waiting visitors for this lane?')) {
      waiting.textContent = '0';
    }
  }
});

// Delete Modal Functionality
let deleteModalData = {
  type: null,
  id: null,
  callback: null
};

function openDeleteModal(type, id, callback) {
  deleteModalData = { type, id, callback };
  const modal = document.getElementById('deleteModal');
  if (modal) {
    modal.classList.remove('hidden');
  }
}

function closeDeleteModal() {
  const modal = document.getElementById('deleteModal');
  if (modal) {
    modal.classList.add('hidden');
  }
  deleteModalData = { type: null, id: null, callback: null };
}

async function confirmDelete() {
  if (deleteModalData.callback) {
    try {
      await deleteModalData.callback();
      closeDeleteModal();
      showDeleteSuccessDialog({ title: 'Successfully Deleted', subtitle: 'The record has been permanently removed', duration: 2000 });
    } catch (err) {
      showToast(err.message || 'Delete failed', 'error', 2500);
    }
  }
}

// Close modal when clicking outside
document.addEventListener('click', (e) => {
  const modal = document.getElementById('deleteModal');
  if (modal && e.target === modal) {
    closeDeleteModal();
  }
});

// Search + Sorting wiring for Departments, Staffs, Doctors, Patients
document.addEventListener('DOMContentLoaded', () => {
  const sections = ['#departments','#staffs','#doctors','#patients'];
  function wireSearch(sectionSel){
    const input = document.querySelector(`${sectionSel} .filter-search`);
    const tbody = document.querySelector(`${sectionSel} table tbody`);
    if (!input || !tbody) return;
    input.addEventListener('input', (e)=>{
      const term = String(e.target.value || '').toLowerCase();
      Array.from(tbody.rows).forEach(row=>{
        const text = Array.from(row.cells).map(c=>c.textContent.toLowerCase()).join(' ');
        row.style.display = term && !text.includes(term) ? 'none' : '';
      });
    });
  }
  function parseMaybeNumberOrDate(s){
    const n = Number(s.replace(/[^0-9.-]/g,''));
    if (!Number.isNaN(n) && s.trim() !== '') return n;
    const d = new Date(s);
    if (!Number.isNaN(d.getTime())) return d.getTime();
    return s.toLowerCase();
  }
  function wireSort(sectionSel){
    const theadRow = document.querySelector(`${sectionSel} thead tr`);
    const tbody = document.querySelector(`${sectionSel} table tbody`);
    if (!theadRow || !tbody) return;
    theadRow.querySelectorAll('th:not(.actions-col)').forEach((th, idx)=>{
      th.style.cursor = 'pointer';
      th.title = 'Click to sort';
      th.addEventListener('click', ()=>{
        const asc = !(tbody.dataset.sortCol == String(idx) && tbody.dataset.sortDir === 'asc');
        const rows = Array.from(tbody.rows);
        rows.sort((a,b)=>{
          const av = parseMaybeNumberOrDate(a.cells[idx]?.textContent || '');
          const bv = parseMaybeNumberOrDate(b.cells[idx]?.textContent || '');
          if (av < bv) return asc ? -1 : 1;
          if (av > bv) return asc ? 1 : -1;
          return 0;
        });
        tbody.innerHTML = '';
        rows.forEach(r=>tbody.appendChild(r));
        tbody.dataset.sortCol = String(idx);
        tbody.dataset.sortDir = asc ? 'asc' : 'desc';
      });
    });
  }
  sections.forEach(sel=>{ wireSearch(sel); wireSort(sel); });

  const editPatientModal = document.getElementById('editPatientModal');
  if (editPatientModal) {
    const closeBtn = editPatientModal.querySelector('.modal-close');
    const cancelBtn = editPatientModal.querySelector('.modal-cancel');
    if (closeBtn) closeBtn.addEventListener('click', closeEditPatientModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeEditPatientModal);
  }
});
