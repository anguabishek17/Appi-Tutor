/**
 * UK Tutoring Platform - Web Frontend Entry Point
 * Foundation Phase (F01)
 */

import './styles/main.css';
import { app } from './firebase/config.js';

console.info('UK Tutoring Platform Foundation initialized.');

const statusElement = document.getElementById('emulator-status');
if (statusElement) {
  const isDev = import.meta.env.DEV;
  statusElement.innerHTML = `
    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
    <span>Firebase client initialized (${app.name}) &bull; Mode: <strong class="text-slate-200">${isDev ? 'Local Development' : 'Production'}</strong></span>
  `;
}
