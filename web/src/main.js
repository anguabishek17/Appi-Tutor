/**
 * UK Tutoring Platform - Web Frontend Entry Point
 * Authentication Phase (F02)
 */

import './styles/main.css';
import { setupAuthUI } from './components/authUI.js';
import { setupTutorOnboardingUI } from './components/tutorOnboardingUI.js';
import { setupBookingUI } from './components/bookingUI.js';

console.info('UK Tutoring Platform Phase F05 Initialized.');

const appElement = document.getElementById('app');
if (appElement) {
  setupAuthUI(appElement);
  setupTutorOnboardingUI(appElement);
  setupBookingUI(appElement);
}
