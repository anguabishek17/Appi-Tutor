/**
 * UK Tutoring Platform - Lesson Booking & Availability UI Component
 * Phase F05 — Lesson Booking, Availability & Double-Booking Prevention
 */

import { callFunction } from '../services/api.js';

export function setupBookingUI(container) {
  if (!container) return;

  const bookingSection = document.createElement('div');
  bookingSection.id = 'booking-ui-section';
  bookingSection.className = 'mt-8 max-w-4xl mx-auto space-y-6';

  bookingSection.innerHTML = `
    <!-- Header -->
    <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 backdrop-blur shadow-xl">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-xl font-bold text-white tracking-tight">Lesson Booking & Availability System</h2>
          <p class="text-xs text-slate-400 mt-1">
            Phase F05 — Double-Booking Prevention, Authoritative Lifecycle & Availability Management
          </p>
        </div>
        <button id="btn-refresh-booking-data" class="px-3 py-1.5 text-xs font-medium bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg transition-colors shadow">
          Refresh Data
        </button>
      </div>

      <!-- Action Response Status Banner -->
      <div id="booking-alert-box" class="hidden mt-4 p-3 rounded-xl text-xs font-medium"></div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

      <!-- Left Column: Tutor Availability Management & Browse Slots -->
      <div class="space-y-6">

        <!-- Tutor Availability Creator -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 backdrop-blur shadow-xl space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-white">Create Availability Slot</h3>
            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded font-mono bg-amber-500/10 text-amber-300 border border-amber-500/30">
              TUTOR ONLY
            </span>
          </div>
          <form id="form-create-slot" class="space-y-3 text-xs">
            <div>
              <label class="block text-slate-400 font-medium mb-1">Start Time (UTC / ISO)</label>
              <input type="datetime-local" id="slot-start-time" required class="w-full bg-slate-900/80 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <div>
              <label class="block text-slate-400 font-medium mb-1">End Time (UTC / ISO)</label>
              <input type="datetime-local" id="slot-end-time" required class="w-full bg-slate-900/80 border border-slate-700 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-indigo-500" />
            </div>
            <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-medium rounded-lg transition-colors shadow">
              Create Availability Slot
            </button>
          </form>
        </div>

        <!-- Available Slots List -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 backdrop-blur shadow-xl space-y-4">
          <h3 class="text-base font-semibold text-white">Available Tutor Slots</h3>
          <div id="available-slots-container" class="space-y-3 text-xs text-slate-400">
            <p>Click "Refresh Data" or fetch slots...</p>
          </div>
        </div>

      </div>

      <!-- Right Column: My Bookings & Booking Actions -->
      <div class="space-y-6">

        <!-- My Bookings List -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 backdrop-blur shadow-xl space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-white">My Bookings</h3>
            <button id="btn-fetch-my-bookings" class="px-2.5 py-1 text-xs bg-slate-700 hover:bg-slate-600 text-slate-200 rounded border border-slate-600">
              Fetch My Bookings
            </button>
          </div>
          <div id="my-bookings-container" class="space-y-3 text-xs text-slate-400">
            <p>No bookings loaded yet.</p>
          </div>
        </div>

        <!-- Manager Booking Administration -->
        <div id="manager-booking-panel" class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 backdrop-blur shadow-xl space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-white">Manager Booking Oversight</h3>
            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded font-mono bg-rose-500/10 text-rose-300 border border-rose-500/30">
              MANAGER ONLY
            </span>
          </div>
          <button id="btn-manager-list-bookings" class="w-full py-2 bg-slate-700 hover:bg-slate-600 text-slate-200 font-medium rounded-lg transition-colors text-xs border border-slate-600">
            List All Platform Bookings
          </button>
          <div id="manager-bookings-container" class="space-y-3 text-xs text-slate-400">
            <p>Click list to view all platform bookings.</p>
          </div>
        </div>

      </div>

    </div>
  `;

  container.appendChild(bookingSection);

  // Set default datetime inputs to today + 1 day
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  tomorrow.setMinutes(0, 0, 0);

  const startInput = document.getElementById('slot-start-time');
  const endInput = document.getElementById('slot-end-time');

  if (startInput && endInput) {
    const end = new Date(tomorrow.getTime() + 60 * 60 * 1000);
    startInput.value = tomorrow.toISOString().slice(0, 16);
    endInput.value = end.toISOString().slice(0, 16);
  }

  // Event Listeners
  const alertBox = document.getElementById('booking-alert-box');

  function showAlert(msg, isSuccess = true) {
    if (!alertBox) return;
    alertBox.className = `mt-4 p-3 rounded-xl text-xs font-medium ${
      isSuccess
        ? 'bg-emerald-500/10 border border-emerald-500/30 text-emerald-300'
        : 'bg-rose-500/10 border border-rose-500/30 text-rose-300'
    }`;
    alertBox.textContent = msg;
    alertBox.classList.remove('hidden');
  }

  // Create Slot Handler
  const formCreateSlot = document.getElementById('form-create-slot');
  if (formCreateSlot) {
    formCreateSlot.addEventListener('submit', async e => {
      e.preventDefault();
      try {
        const startVal = new Date(document.getElementById('slot-start-time').value).toISOString();
        const endVal = new Date(document.getElementById('slot-end-time').value).toISOString();

        const res = await callFunction('/createAvailabilitySlot', {
          startAt: startVal,
          endAt: endVal,
          timezone: 'Europe/London',
        });

        if (res.success) {
          showAlert('Availability slot created successfully!');
          fetchAvailableSlots();
        } else {
          showAlert(res.error?.message || 'Failed to create slot', false);
        }
      } catch (err) {
        showAlert(err.message || 'Error creating slot', false);
      }
    });
  }

  // Fetch Available Slots
  async function fetchAvailableSlots() {
    const slotsContainer = document.getElementById('available-slots-container');
    if (!slotsContainer) return;

    try {
      const res = await callFunction('/getAvailableSlots');
      if (res.success && res.data?.slots) {
        const slots = res.data.slots;
        if (slots.length === 0) {
          slotsContainer.innerHTML =
            '<p class="text-slate-400 italic">No available slots found.</p>';
          return;
        }

        slotsContainer.innerHTML = slots
          .map(
            s => `
          <div class="bg-slate-900/80 p-3 rounded-xl border border-slate-700/80 flex items-center justify-between gap-3">
            <div>
              <div class="font-mono text-white text-[11px]">Slot ID: ${s.slotId}</div>
              <div class="text-slate-300 font-medium mt-0.5">
                ${new Date(s.startAt).toLocaleString('en-GB', { timeZone: 'Europe/London' })} - 
                ${new Date(s.endAt).toLocaleTimeString('en-GB', { timeZone: 'Europe/London' })} (${s.timezone || 'UK'})
              </div>
              <div class="text-slate-500 text-[10px]">Tutor: ${s.tutorId}</div>
            </div>
            <button data-slot-id="${s.slotId}" class="btn-book-slot px-3 py-1.5 text-xs bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg shadow">
              Book Lesson
            </button>
          </div>
        `
          )
          .join('');

        // Attach book click handlers
        document.querySelectorAll('.btn-book-slot').forEach(btn => {
          btn.addEventListener('click', async e => {
            const slotId = e.currentTarget.getAttribute('data-slot-id');
            try {
              const bookRes = await callFunction('/createBooking', { slotId });
              if (bookRes.success) {
                showAlert('Booking requested successfully! Initial status: PENDING');
                fetchAvailableSlots();
                fetchMyBookings();
              } else {
                showAlert(bookRes.error?.message || 'Booking failed', false);
              }
            } catch (err) {
              showAlert(err.message || 'Error booking slot', false);
            }
          });
        });
      } else {
        slotsContainer.innerHTML = `<p class="text-rose-400">${res.error?.message || 'Failed to fetch slots'}</p>`;
      }
    } catch (err) {
      slotsContainer.innerHTML = `<p class="text-rose-400">${err.message || 'Error'}</p>`;
    }
  }

  // Fetch My Bookings
  async function fetchMyBookings() {
    const container = document.getElementById('my-bookings-container');
    if (!container) return;

    try {
      const res = await callFunction('/getMyBookings');
      if (res.success && res.data?.bookings) {
        const bookings = res.data.bookings;
        if (bookings.length === 0) {
          container.innerHTML =
            '<p class="text-slate-400 italic">No bookings found for your account.</p>';
          return;
        }

        container.innerHTML = bookings
          .map(
            b => `
          <div class="bg-slate-900/80 p-3 rounded-xl border border-slate-700/80 space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-mono text-[10px] text-slate-400">ID: ${b.bookingId}</span>
              <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold uppercase ${getStatusBadgeClass(
                b.status
              )}">
                ${b.status}
              </span>
            </div>
            <div class="text-slate-200 text-xs">
              Time: ${new Date(b.startAt).toLocaleString('en-GB', { timeZone: 'Europe/London' })}
            </div>
            <div class="text-slate-400 text-[11px] flex justify-between">
              <span>Student: ${b.studentUid}</span>
              <span>Tutor: ${b.tutorUid}</span>
            </div>

            <!-- Action Buttons based on state -->
            <div class="flex flex-wrap gap-1.5 pt-2 border-t border-slate-800">
              ${
                b.status === 'PENDING'
                  ? `
                <button data-id="${b.bookingId}" data-action="/confirmBooking" class="btn-b-action px-2 py-1 text-[10px] bg-emerald-600/20 text-emerald-300 border border-emerald-500/30 rounded">Confirm</button>
                <button data-id="${b.bookingId}" data-action="/rejectBooking" class="btn-b-action px-2 py-1 text-[10px] bg-rose-600/20 text-rose-300 border border-rose-500/30 rounded">Reject</button>
                <button data-id="${b.bookingId}" data-action="/proposeReschedule" class="btn-b-action px-2 py-1 text-[10px] bg-amber-600/20 text-amber-300 border border-amber-500/30 rounded">Propose Reschedule</button>
              `
                  : ''
              }
              ${
                b.status === 'CONFIRMED'
                  ? `
                <button data-id="${b.bookingId}" data-action="/completeBooking" class="btn-b-action px-2 py-1 text-[10px] bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 rounded">Complete</button>
              `
                  : ''
              }
              ${
                ['PENDING', 'CONFIRMED', 'RESCHEDULE_PROPOSED'].includes(b.status)
                  ? `
                <button data-id="${b.bookingId}" data-action="/cancelBooking" class="btn-b-action px-2 py-1 text-[10px] bg-slate-700 text-slate-300 rounded">Cancel</button>
              `
                  : ''
              }
            </div>

            <!-- History log dropdown -->
            <details class="text-[10px] text-slate-500 pt-1">
              <summary class="cursor-pointer font-medium hover:text-slate-400">View Status History (${
                b.statusHistory ? b.statusHistory.length : 0
              })</summary>
              <div class="mt-1 space-y-1 pl-2 border-l border-slate-800">
                ${(b.statusHistory || [])
                  .map(
                    h => `
                  <div>
                    <span class="text-slate-300 font-mono">${h.status}</span> by <span class="text-slate-400">${h.changedByRole || 'USER'}</span> at ${new Date(h.changedAt).toLocaleString('en-GB')}
                    ${h.reason ? `<span class="italic text-slate-500">(${h.reason})</span>` : ''}
                  </div>
                `
                  )
                  .join('')}
              </div>
            </details>
          </div>
        `
          )
          .join('');

        // Attach action handlers
        document.querySelectorAll('.btn-b-action').forEach(btn => {
          btn.addEventListener('click', async e => {
            const bookingId = e.currentTarget.getAttribute('data-id');
            const endpoint = e.currentTarget.getAttribute('data-action');
            try {
              const res = await callFunction(endpoint, { bookingId });
              if (res.success) {
                showAlert(`Action ${endpoint} completed successfully!`);
                fetchMyBookings();
                fetchAvailableSlots();
              } else {
                showAlert(res.error?.message || 'Action failed', false);
              }
            } catch (err) {
              showAlert(err.message || 'Error executing booking action', false);
            }
          });
        });
      } else {
        container.innerHTML = `<p class="text-rose-400">${res.error?.message || 'Failed to fetch bookings'}</p>`;
      }
    } catch (err) {
      container.innerHTML = `<p class="text-rose-400">${err.message || 'Error'}</p>`;
    }
  }

  function getStatusBadgeClass(status) {
    switch (status) {
      case 'CONFIRMED':
        return 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
      case 'PENDING':
        return 'bg-amber-500/20 text-amber-300 border border-amber-500/30';
      case 'REJECTED':
      case 'CANCELLED':
      case 'SYSTEM_CANCELLED':
        return 'bg-rose-500/20 text-rose-300 border border-rose-500/30';
      case 'COMPLETED':
        return 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30';
      case 'RESCHEDULE_PROPOSED':
        return 'bg-purple-500/20 text-purple-300 border border-purple-500/30';
      default:
        return 'bg-slate-800 text-slate-400';
    }
  }

  // Refresh data button
  const btnRefresh = document.getElementById('btn-refresh-booking-data');
  if (btnRefresh) {
    btnRefresh.addEventListener('click', () => {
      fetchAvailableSlots();
      fetchMyBookings();
    });
  }

  const btnFetchMyBookings = document.getElementById('btn-fetch-my-bookings');
  if (btnFetchMyBookings) {
    btnFetchMyBookings.addEventListener('click', fetchMyBookings);
  }

  // Manager List Bookings button
  const btnManagerList = document.getElementById('btn-manager-list-bookings');
  if (btnManagerList) {
    btnManagerList.addEventListener('click', async () => {
      const container = document.getElementById('manager-bookings-container');
      if (!container) return;
      try {
        const res = await callFunction('/managerListBookings');
        if (res.success && res.data?.bookings) {
          const bookings = res.data.bookings;
          if (bookings.length === 0) {
            container.innerHTML =
              '<p class="text-slate-400 italic">No bookings registered in system.</p>';
            return;
          }

          container.innerHTML = bookings
            .map(
              b => `
            <div class="bg-slate-900/80 p-2.5 rounded-lg border border-slate-700/60 flex items-center justify-between text-[11px]">
              <div>
                <span class="font-mono text-slate-300">${b.bookingId}</span> - 
                <span class="font-bold text-amber-300">${b.status}</span>
                <div class="text-slate-500 text-[10px]">Student: ${b.studentUid} | Tutor: ${b.tutorUid}</div>
              </div>
              ${
                ['PENDING', 'CONFIRMED', 'RESCHEDULE_PROPOSED'].includes(b.status)
                  ? `
                <button data-id="${b.bookingId}" class="btn-mgr-cancel px-2 py-1 bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 border border-rose-500/30 rounded">Manager Cancel</button>
              `
                  : ''
              }
            </div>
          `
            )
            .join('');

          document.querySelectorAll('.btn-mgr-cancel').forEach(btn => {
            btn.addEventListener('click', async e => {
              const bookingId = e.currentTarget.getAttribute('data-id');
              try {
                const res = await callFunction('/managerCancelBooking', {
                  bookingId,
                  reason: 'Manager administrative action',
                });
                if (res.success) {
                  showAlert('Booking cancelled by manager with audit log!');
                  btnManagerList.click();
                  fetchMyBookings();
                } else {
                  showAlert(res.error?.message || 'Manager cancel failed', false);
                }
              } catch (err) {
                showAlert(err.message || 'Error', false);
              }
            });
          });
        } else {
          container.innerHTML = `<p class="text-rose-400">${res.error?.message || 'Manager access failed'}</p>`;
        }
      } catch (err) {
        container.innerHTML = `<p class="text-rose-400">${err.message || 'Error'}</p>`;
      }
    });
  }

  // Initial fetch
  fetchAvailableSlots();
}
