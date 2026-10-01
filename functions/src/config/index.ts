/**
 * UK Tutoring Platform - Configuration Layer
 * Foundation Phase (F01)
 */

/**
 * The standard deployment region for all Cloud Functions and backend resources.
 */
export const DEFAULT_REGION = 'europe-west2' as const;

export type SupportedRegion = typeof DEFAULT_REGION;

/**
 * Environment configuration interface
 */
export interface AppConfig {
  env: 'development' | 'staging' | 'production' | 'test';
  region: SupportedRegion;
  isEmulator: boolean;
  logging: {
    level: 'debug' | 'info' | 'warn' | 'error';
    enableAuditLogging: boolean;
  };
  services: {
    // Placeholders for external providers to be configured in later phases via secrets
    emailProvider: {
      provider: string;
      configured: boolean;
    };
  };
  features: {
    enableRateLimiting: boolean;
  };
}

/**
 * Read configuration safely from environment variables with sensible defaults.
 * Secrets should NEVER be hardcoded here.
 */
export function loadConfig(): AppConfig {
  const nodeEnv = process.env.NODE_ENV || 'development';
  const isEmulator =
    process.env.FUNCTIONS_EMULATOR === 'true' || !!process.env.FIREBASE_EMULATOR_HUB;

  return {
    env: (['development', 'staging', 'production', 'test'].includes(nodeEnv)
      ? nodeEnv
      : 'development') as AppConfig['env'],
    region: DEFAULT_REGION,
    isEmulator,
    logging: {
      level: (process.env.LOG_LEVEL as AppConfig['logging']['level']) || 'info',
      enableAuditLogging: process.env.ENABLE_AUDIT_LOGGING !== 'false',
    },
    services: {
      emailProvider: {
        provider: process.env.EMAIL_PROVIDER || 'mock',
        configured: Boolean(process.env.EMAIL_PROVIDER_API_KEY),
      },
    },
    features: {
      enableRateLimiting: process.env.ENABLE_RATE_LIMITING === 'true',
    },
  };
}

export const config = loadConfig();
