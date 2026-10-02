/**
 * UK Tutoring Platform - F04 Tutor Onboarding & Manager Approval UI
 * Vanilla JS Component for Tutor Profile Onboarding & Manager Review
 */

import { callFunction } from '../services/api.js';

export function setupTutorOnboardingUI(container) {
  if (!container) return;

  const section = document.createElement('div');
  section.className = 'space-y-8 max-w-4xl mx-auto mt-8 border-t border-slate-700/60 pt-8';
  section.innerHTML = `
    <!-- F04 Header Banner -->
    <div class="flex items-center justify-between bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 backdrop-blur shadow-xl">
      <div>
        <span class="px-2.5 py-0.5 rounded text-[10px] font-mono font-bold bg-sky-500/20 text-sky-300 border border-sky-500/30 uppercase">
          Phase F04
        </span>
        <h2 class="text-lg font-semibold text-white mt-1">Tutor Onboarding & Manager Review Console</h2>
        <p class="text-xs text-slate-400">Manage profile completion, private DBS submission, and administrative manager approval.</p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Tutor Onboarding Card -->
      <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 backdrop-blur shadow-xl space-y-4">
        <div>
          <h3 class="text-base font-semibold text-white">Tutor Onboarding Workflow</h3>
          <p class="text-xs text-slate-400 mt-1">Complete your tutor profile and submit private DBS document details.</p>
        </div>

        <form id="form-tutor-profile" class="space-y-3 text-xs">
          <div>
            <label class="block text-slate-400 font-medium mb-1">Tutor Bio * (min 10 chars)</label>
            <textarea id="tutor-bio" rows="3" required placeholder="Experienced UK Certified GCSE & A-Level Mathematics Tutor..." class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 focus:outline-none focus:border-sky-500"></textarea>
          </div>

          <div>
            <label class="block text-slate-400 font-medium mb-1">Subjects * (comma separated)</label>
            <input type="text" id="tutor-subjects" required placeholder="Mathematics, Further Maths, Physics" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 focus:outline-none focus:border-sky-500" />
          </div>

          <div>
            <label class="block text-slate-400 font-medium mb-1">Qualifications * (comma separated)</label>
            <input type="text" id="tutor-qualifications" required placeholder="BSc Mathematics - Imperial College, PGCE Secondary" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 focus:outline-none focus:border-sky-500" />
          </div>

          <div>
            <label class="block text-slate-400 font-medium mb-1">Hourly Rate (in Pence, e.g. 4000 = £40.00) *</label>
            <input type="number" id="tutor-rate" required min="1000" step="100" value="4000" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 focus:outline-none focus:border-sky-500" />
          </div>

          <button type="submit" id="btn-save-tutor-profile" class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-500 text-white font-medium rounded-lg shadow transition-colors">
            Save & Update Tutor Profile
          </button>
        </form>

        <div class="pt-3 border-t border-slate-700/60 space-y-3">
          <h4 class="text-xs font-semibold text-slate-300">DBS Private Document Submission</h4>
          <form id="form-dbs-submit" class="space-y-3 text-xs">
            <div>
              <label class="block text-slate-400 font-medium mb-1">Private Storage Path (e.g. dbs/{uid}/cert.pdf)</label>
              <input type="text" id="dbs-path" required placeholder="dbs/YOUR_UID/certificate.pdf" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 focus:outline-none focus:border-sky-500" />
            </div>
            <button type="submit" id="btn-submit-dbs" class="w-full py-2.5 px-4 bg-sky-600 hover:bg-sky-500 text-white font-medium rounded-lg shadow transition-colors">
              Submit Private DBS Certificate
            </button>
          </form>
        </div>
      </div>

      <!-- Manager Control Console -->
      <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 backdrop-blur shadow-xl space-y-4">
        <div>
          <h3 class="text-base font-semibold text-white">Manager Administrative Actions</h3>
          <p class="text-xs text-slate-400 mt-1">Review pending tutor profiles, verify DBS status, approve, reject, or suspend.</p>
        </div>

        <button id="btn-mgr-list-tutors" class="w-full py-2 px-3 bg-purple-600 hover:bg-purple-500 text-white font-medium rounded-lg text-xs shadow transition-colors">
          List All Tutor Onboarding Profiles
        </button>

        <div class="space-y-3 text-xs pt-2 border-t border-slate-700/60">
          <div>
            <label class="block text-slate-400 font-medium mb-1">Target Tutor UID *</label>
            <input type="text" id="mgr-target-uid" placeholder="Target Tutor UID" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 focus:outline-none focus:border-purple-500" />
          </div>

          <div>
            <label class="block text-slate-400 font-medium mb-1">Notes / Reason (optional for approval, required for reject/suspend)</label>
            <input type="text" id="mgr-reason" placeholder="e.g. DBS certificate verified / invalid document" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200 focus:outline-none focus:border-purple-500" />
          </div>

          <div class="grid grid-cols-2 gap-2 pt-1">
            <button id="btn-mgr-approve" type="button" class="py-2 px-3 bg-emerald-600/20 hover:bg-emerald-600/30 text-emerald-300 border border-emerald-500/40 rounded-lg font-medium transition-colors">
              Approve Tutor
            </button>
            <button id="btn-mgr-reject" type="button" class="py-2 px-3 bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 border border-rose-500/40 rounded-lg font-medium transition-colors">
              Reject Application
            </button>
            <button id="btn-mgr-suspend" type="button" class="py-2 px-3 bg-amber-600/20 hover:bg-amber-600/30 text-amber-300 border border-amber-500/40 rounded-lg font-medium transition-colors">
              Suspend Tutor
            </button>
            <button id="btn-mgr-reapprove" type="button" class="py-2 px-3 bg-sky-600/20 hover:bg-sky-600/30 text-sky-300 border border-sky-500/40 rounded-lg font-medium transition-colors">
              Re-approve Tutor
            </button>
          </div>
        </div>
      </div>
    </div>
  `;

  container.appendChild(section);

  const displayF04Output = (status, data, requestId) => {
    const outputEl = document.getElementById('api-output');
    const statusBadge = document.getElementById('response-status-badge');
    const reqIdEl = document.getElementById('response-request-id');

    if (outputEl) {
      outputEl.textContent = JSON.stringify(data, null, 2);
      outputEl.className =
        status >= 200 && status < 300
          ? 'bg-slate-950 p-4 rounded-xl border border-slate-800/80 text-xs font-mono text-emerald-400 overflow-x-auto max-h-56 select-all'
          : 'bg-slate-950 p-4 rounded-xl border border-rose-900/40 text-xs font-mono text-rose-400 overflow-x-auto max-h-56 select-all';
    }

    if (statusBadge) {
      statusBadge.textContent = `HTTP ${status}`;
      statusBadge.className = `px-2 py-0.5 rounded text-[10px] font-mono border ${
        status >= 200 && status < 300
          ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30'
          : 'bg-rose-500/10 text-rose-400 border-rose-500/30'
      }`;
    }

    if (reqIdEl) {
      if (requestId) {
        reqIdEl.textContent = `req: ${requestId}`;
        reqIdEl.classList.remove('hidden');
      } else {
        reqIdEl.classList.add('hidden');
      }
    }
  };

  // Event Listeners
  const formProfile = document.getElementById('form-tutor-profile');
  if (formProfile) {
    formProfile.addEventListener('submit', async e => {
      e.preventDefault();
      const bio = document.getElementById('tutor-bio')?.value;
      const subjectsStr = document.getElementById('tutor-subjects')?.value;
      const qualificationsStr = document.getElementById('tutor-qualifications')?.value;
      const hourlyRatePence = Number(document.getElementById('tutor-rate')?.value);

      const subjects = subjectsStr
        ? subjectsStr
            .split(',')
            .map(s => s.trim())
            .filter(Boolean)
        : [];
      const qualifications = qualificationsStr
        ? qualificationsStr
            .split(',')
            .map(q => q.trim())
            .filter(Boolean)
        : [];

      const res = await callFunction('updateTutorProfile', {
        method: 'POST',
        body: { bio, subjects, qualifications, hourlyRatePence },
      });
      displayF04Output(res.status, res.data, res.requestId);
    });
  }

  const formDbs = document.getElementById('form-dbs-submit');
  if (formDbs) {
    formDbs.addEventListener('submit', async e => {
      e.preventDefault();
      const dbsDocumentPath = document.getElementById('dbs-path')?.value?.trim();

      const res = await callFunction('submitDbsDocument', {
        method: 'POST',
        body: { dbsDocumentPath },
      });
      displayF04Output(res.status, res.data, res.requestId);
    });
  }

  const btnList = document.getElementById('btn-mgr-list-tutors');
  if (btnList) {
    btnList.addEventListener('click', async () => {
      const res = await callFunction('managerListPendingTutors', { method: 'GET' });
      displayF04Output(res.status, res.data, res.requestId);
    });
  }

  const btnApprove = document.getElementById('btn-mgr-approve');
  if (btnApprove) {
    btnApprove.addEventListener('click', async () => {
      const tutorUid = document.getElementById('mgr-target-uid')?.value?.trim();
      const managerNotes = document.getElementById('mgr-reason')?.value?.trim();
      const res = await callFunction('managerApproveTutor', {
        method: 'POST',
        body: { tutorUid, managerNotes },
      });
      displayF04Output(res.status, res.data, res.requestId);
    });
  }

  const btnReject = document.getElementById('btn-mgr-reject');
  if (btnReject) {
    btnReject.addEventListener('click', async () => {
      const tutorUid = document.getElementById('mgr-target-uid')?.value?.trim();
      const reason = document.getElementById('mgr-reason')?.value?.trim();
      const res = await callFunction('managerRejectTutor', {
        method: 'POST',
        body: { tutorUid, reason },
      });
      displayF04Output(res.status, res.data, res.requestId);
    });
  }

  const btnSuspend = document.getElementById('btn-mgr-suspend');
  if (btnSuspend) {
    btnSuspend.addEventListener('click', async () => {
      const tutorUid = document.getElementById('mgr-target-uid')?.value?.trim();
      const reason = document.getElementById('mgr-reason')?.value?.trim();
      const res = await callFunction('managerSuspendTutor', {
        method: 'POST',
        body: { tutorUid, reason },
      });
      displayF04Output(res.status, res.data, res.requestId);
    });
  }

  const btnReapprove = document.getElementById('btn-mgr-reapprove');
  if (btnReapprove) {
    btnReapprove.addEventListener('click', async () => {
      const tutorUid = document.getElementById('mgr-target-uid')?.value?.trim();
      const managerNotes = document.getElementById('mgr-reason')?.value?.trim();
      const res = await callFunction('managerReapproveTutor', {
        method: 'POST',
        body: { tutorUid, managerNotes },
      });
      displayF04Output(res.status, res.data, res.requestId);
    });
  }
}
