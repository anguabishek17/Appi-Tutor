/**
 * UK Tutoring Platform - Payments & Refunds UI Component
 * Phase F06 — Payments & Refunds
 */

import { callFunction } from '../services/api.js';

export function setupPaymentUI(container) {
  if (!container) return;

  const paymentSection = document.createElement('div');
  paymentSection.id = 'payment-ui-section';
  paymentSection.className = 'mt-8 max-w-4xl mx-auto space-y-6';

  paymentSection.innerHTML = `
    <!-- Header Card -->
    <div class="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-6 backdrop-blur shadow-xl">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h2 class="text-xl font-bold text-white tracking-tight">Payments & Refunds System</h2>
          <p class="text-xs text-slate-400 mt-1">
            Phase F06 — Server-Authoritative Financial Processing, Minor Integer Units & Idempotent Refund Engine
          </p>
        </div>
        <button id="btn-refresh-payment-data" class="px-3 py-1.5 text-xs font-medium bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition-colors shadow">
          Refresh Payments
        </button>
      </div>

      <!-- Action Response Status Banner -->
      <div id="payment-alert-box" class="hidden mt-4 p-3 rounded-xl text-xs font-medium"></div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

      <!-- Left Column: Initiate & Confirm Payment -->
      <div class="space-y-6">

        <!-- Initiate Payment Form -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 backdrop-blur shadow-xl space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-white">Initiate Lesson Payment</h3>
            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded font-mono bg-blue-500/10 text-blue-300 border border-blue-500/30">
              STUDENT ONLY
            </span>
          </div>
          <form id="form-create-payment" class="space-y-3 text-xs">
            <div>
              <label class="block text-slate-400 font-medium mb-1">Target Booking ID</label>
              <input type="text" id="pay-booking-id" placeholder="e.g. booking_123" required class="w-full bg-slate-900/80 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:outline-none focus:border-emerald-500" />
            </div>
            <div>
              <label class="block text-slate-400 font-medium mb-1">Idempotency Key (Optional)</label>
              <input type="text" id="pay-idempotency-key" placeholder="e.g. key_unique_123" class="w-full bg-slate-900/80 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:outline-none focus:border-emerald-500" />
            </div>
            <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-medium rounded-lg transition-colors shadow">
              Create Authoritative Payment Intent
            </button>
          </form>
        </div>

        <!-- Confirm Payment Form -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 backdrop-blur shadow-xl space-y-4">
          <h3 class="text-base font-semibold text-white">Confirm / Capture Payment</h3>
          <form id="form-confirm-payment" class="space-y-3 text-xs">
            <div>
              <label class="block text-slate-400 font-medium mb-1">Payment ID to Confirm</label>
              <input type="text" id="confirm-payment-id" placeholder="e.g. pay_abc123" required class="w-full bg-slate-900/80 border border-slate-700 rounded-lg px-3 py-2 text-white font-mono focus:outline-none focus:border-emerald-500" />
            </div>
            <button type="submit" class="w-full py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg transition-colors shadow">
              Confirm & Capture Payment
            </button>
          </form>
        </div>

      </div>

      <!-- Right Column: My Payments & Manager Oversight -->
      <div class="space-y-6">

        <!-- My Payments & Refunds List -->
        <div class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 backdrop-blur shadow-xl space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-white">My Payment Records</h3>
            <button id="btn-fetch-my-payments" class="px-2.5 py-1 text-xs bg-slate-700 hover:bg-slate-600 text-slate-200 rounded border border-slate-600">
              Fetch Payments
            </button>
          </div>
          <div id="my-payments-container" class="space-y-3 text-xs text-slate-400">
            <p>Click fetch to load your payments.</p>
          </div>
        </div>

        <!-- Manager Payment Oversight -->
        <div id="manager-payment-panel" class="bg-slate-800/60 border border-slate-700/60 rounded-2xl p-6 backdrop-blur shadow-xl space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-white">Manager Financial Administration</h3>
            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded font-mono bg-rose-500/10 text-rose-300 border border-rose-500/30">
              MANAGER ONLY
            </span>
          </div>
          <button id="btn-manager-list-payments" class="w-full py-2 bg-slate-700 hover:bg-slate-600 text-slate-200 font-medium rounded-lg transition-colors text-xs border border-slate-600">
            List All Platform Payments & Refunds
          </button>
          <div id="manager-payments-container" class="space-y-3 text-xs text-slate-400">
            <p>Click list to inspect platform financial transactions.</p>
          </div>
        </div>

      </div>

    </div>
  `;

  container.appendChild(paymentSection);

  // Status Alert Helper
  const alertBox = document.getElementById('payment-alert-box');

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

  // Create Payment Event Listener
  const formCreate = document.getElementById('form-create-payment');
  if (formCreate) {
    formCreate.addEventListener('submit', async e => {
      e.preventDefault();
      try {
        const bookingId = document.getElementById('pay-booking-id').value.trim();
        const idempotencyKey =
          document.getElementById('pay-idempotency-key').value.trim() || undefined;

        const res = await callFunction('/createPayment', { bookingId, idempotencyKey });

        if (res.success && res.data?.payment) {
          const p = res.data.payment;
          showAlert(
            `Payment Intent Created! ID: ${p.paymentId} | Amount: £${(p.amount / 100).toFixed(
              2
            )} ${p.currency}`
          );
          fetchMyPayments();
        } else {
          showAlert(res.error?.message || 'Payment initiation failed', false);
        }
      } catch (err) {
        showAlert(err.message || 'Error creating payment', false);
      }
    });
  }

  // Confirm Payment Event Listener
  const formConfirm = document.getElementById('form-confirm-payment');
  if (formConfirm) {
    formConfirm.addEventListener('submit', async e => {
      e.preventDefault();
      try {
        const paymentId = document.getElementById('confirm-payment-id').value.trim();

        const res = await callFunction('/confirmPayment', { paymentId });

        if (res.success) {
          showAlert(`Payment ${paymentId} captured successfully! Status: SUCCEEDED`);
          fetchMyPayments();
        } else {
          showAlert(res.error?.message || 'Confirmation failed', false);
        }
      } catch (err) {
        showAlert(err.message || 'Error confirming payment', false);
      }
    });
  }

  // Fetch My Payments
  async function fetchMyPayments() {
    const listContainer = document.getElementById('my-payments-container');
    if (!listContainer) return;

    try {
      const res = await callFunction('/getMyPayments');
      if (res.success && res.data?.payments) {
        const payments = res.data.payments;
        if (payments.length === 0) {
          listContainer.innerHTML =
            '<p class="text-slate-400 italic">No payment records found.</p>';
          return;
        }

        listContainer.innerHTML = payments
          .map(
            p => `
          <div class="bg-slate-900/80 p-3 rounded-xl border border-slate-700/80 space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-mono text-[10px] text-slate-300">${p.paymentId}</span>
              <span class="px-2 py-0.5 rounded font-mono text-[10px] font-bold uppercase ${getPaymentBadgeClass(
                p.status
              )}">
                ${p.status}
              </span>
            </div>
            <div class="text-slate-200 font-bold text-xs">
              Amount: £${(p.amount / 100).toFixed(2)} ${p.currency}
              ${
                p.refundedAmount
                  ? `<span class="text-rose-400 text-[10px] ml-2">(Refunded: £${(
                      p.refundedAmount / 100
                    ).toFixed(2)})</span>`
                  : ''
              }
            </div>
            <div class="text-slate-400 text-[10px]">
              Booking: ${p.bookingId} | Provider: ${p.provider}
            </div>
            ${
              p.status === 'SUCCEEDED' || p.status === 'PARTIALLY_REFUNDED'
                ? `
              <div class="pt-2 border-t border-slate-800 flex items-center justify-between">
                <button data-id="${p.paymentId}" class="btn-refund-pay px-2.5 py-1 text-[10px] bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 border border-rose-500/30 rounded">
                  Request Refund
                </button>
              </div>
            `
                : ''
            }
          </div>
        `
          )
          .join('');

        document.querySelectorAll('.btn-refund-pay').forEach(btn => {
          btn.addEventListener('click', async e => {
            const paymentId = e.currentTarget.getAttribute('data-id');
            try {
              const res = await callFunction('/processRefund', {
                paymentId,
                reason: 'Customer requested refund',
              });
              if (res.success) {
                showAlert(`Refund processed successfully for ${paymentId}!`);
                fetchMyPayments();
              } else {
                showAlert(res.error?.message || 'Refund failed', false);
              }
            } catch (err) {
              showAlert(err.message || 'Refund error', false);
            }
          });
        });
      } else {
        listContainer.innerHTML = `<p class="text-rose-400">${res.error?.message || 'Failed to fetch payments'}</p>`;
      }
    } catch (err) {
      listContainer.innerHTML = `<p class="text-rose-400">${err.message || 'Error'}</p>`;
    }
  }

  function getPaymentBadgeClass(status) {
    switch (status) {
      case 'SUCCEEDED':
        return 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
      case 'PENDING':
        return 'bg-amber-500/20 text-amber-300 border border-amber-500/30';
      case 'REFUNDED':
      case 'PARTIALLY_REFUNDED':
        return 'bg-purple-500/20 text-purple-300 border border-purple-500/30';
      case 'FAILED':
      case 'CANCELLED':
        return 'bg-rose-500/20 text-rose-300 border border-rose-500/30';
      default:
        return 'bg-slate-800 text-slate-400';
    }
  }

  // Refresh & Fetch Event Listeners
  const btnRefresh = document.getElementById('btn-refresh-payment-data');
  if (btnRefresh) {
    btnRefresh.addEventListener('click', fetchMyPayments);
  }

  const btnFetchMy = document.getElementById('btn-fetch-my-payments');
  if (btnFetchMy) {
    btnFetchMy.addEventListener('click', fetchMyPayments);
  }

  // Manager List Payments Button
  const btnManagerList = document.getElementById('btn-manager-list-payments');
  if (btnManagerList) {
    btnManagerList.addEventListener('click', async () => {
      const container = document.getElementById('manager-payments-container');
      if (!container) return;
      try {
        const res = await callFunction('/managerListPayments');
        if (res.success && res.data?.payments) {
          const payments = res.data.payments;
          if (payments.length === 0) {
            container.innerHTML =
              '<p class="text-slate-400 italic">No payments logged in system.</p>';
            return;
          }

          container.innerHTML = payments
            .map(
              p => `
            <div class="bg-slate-900/80 p-2.5 rounded-lg border border-slate-700/60 flex items-center justify-between text-[11px]">
              <div>
                <span class="font-mono text-slate-300">${p.paymentId}</span> - 
                <span class="font-bold text-emerald-300">£${(p.amount / 100).toFixed(2)} ${p.currency}</span>
                <span class="ml-2 font-mono text-[10px] text-amber-300">[${p.status}]</span>
              </div>
              ${
                p.status === 'SUCCEEDED'
                  ? `
                <button data-id="${p.paymentId}" class="btn-mgr-refund px-2 py-1 bg-rose-600/20 hover:bg-rose-600/30 text-rose-300 border border-rose-500/30 rounded">Manager Refund</button>
              `
                  : ''
              }
            </div>
          `
            )
            .join('');

          document.querySelectorAll('.btn-mgr-refund').forEach(btn => {
            btn.addEventListener('click', async e => {
              const paymentId = e.currentTarget.getAttribute('data-id');
              try {
                const res = await callFunction('/processRefund', {
                  paymentId,
                  reason: 'Manager administrative refund override',
                });
                if (res.success) {
                  showAlert(`Manager refund issued for payment ${paymentId}!`);
                  btnManagerList.click();
                  fetchMyPayments();
                } else {
                  showAlert(res.error?.message || 'Manager refund failed', false);
                }
              } catch (err) {
                showAlert(err.message || 'Error', false);
              }
            });
          });
        } else {
          container.innerHTML = `<p class="text-rose-400">${res.error?.message || 'Manager list failed'}</p>`;
        }
      } catch (err) {
        container.innerHTML = `<p class="text-rose-400">${err.message || 'Error'}</p>`;
      }
    });
  }

  // Initial load
  fetchMyPayments();
}
