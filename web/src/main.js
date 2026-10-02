/**
 * UK Tutoring Platform - Web Frontend Entry Point
 * Authentication Phase (F02)
 */

import './styles/main.css';
import { setupAuthUI } from './components/authUI.js';
import { setupTutorOnboardingUI } from './components/tutorOnboardingUI.js';

console.info('UK Tutoring Platform Phase F04 Initialized.');

const appElement = document.getElementById('app');
if (appElement) {
  setupAuthUI(appElement);
  setupTutorOnboardingUI(appElement);
}
