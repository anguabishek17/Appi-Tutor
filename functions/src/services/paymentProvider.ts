/**
 * UK Tutoring Platform - Payment Provider Abstraction & Mock Provider
 * Phase F06 — Payments & Refunds
 */

export interface PaymentIntentResult {
  providerPaymentId: string;
  clientSecret?: string;
  status: 'PENDING' | 'SUCCEEDED' | 'FAILED';
}

export interface RefundResult {
  providerRefundId: string;
  status: 'SUCCEEDED' | 'FAILED';
  amount: number;
}

export interface PaymentProvider {
  name: string;
  createPaymentIntent(params: {
    amount: number;
    currency: string;
    bookingId: string;
    metadata?: Record<string, unknown>;
  }): Promise<PaymentIntentResult>;

  capturePayment(providerPaymentId: string): Promise<PaymentIntentResult>;

  refundPayment(params: {
    providerPaymentId: string;
    amount: number;
    currency: string;
    reason?: string;
  }): Promise<RefundResult>;

  verifyWebhookSignature(payload: string, signature: string, secret: string): boolean;
}

/**
 * Deterministic Mock/Emulator Payment Provider implementation.
 * Used when no live production payment provider credentials (e.g. Stripe API Keys) are provided.
 */
export class MockPaymentProvider implements PaymentProvider {
  public readonly name = 'MOCK_PROVIDER';

  async createPaymentIntent(_params: {
    amount: number;
    currency: string;
    bookingId: string;
    metadata?: Record<string, unknown>;
  }): Promise<PaymentIntentResult> {
    const providerPaymentId = `mock_pi_${Date.now()}_${Math.random().toString(36).substring(2, 7)}`;
    return {
      providerPaymentId,
      clientSecret: `${providerPaymentId}_secret`,
      status: 'PENDING',
    };
  }

  async capturePayment(providerPaymentId: string): Promise<PaymentIntentResult> {
    return {
      providerPaymentId,
      status: 'SUCCEEDED',
    };
  }

  async refundPayment(params: {
    providerPaymentId: string;
    amount: number;
    currency: string;
    reason?: string;
  }): Promise<RefundResult> {
    const providerRefundId = `mock_re_${Date.now()}_${Math.random().toString(36).substring(2, 7)}`;
    return {
      providerRefundId,
      status: 'SUCCEEDED',
      amount: params.amount,
    };
  }

  verifyWebhookSignature(_payload: string, signature: string, secret: string): boolean {
    if (!signature || !secret) return false;
    // Validates deterministic signature requirement in test/emulator environment
    return signature === `valid_sig_${secret}` || signature === 'mock_valid_signature';
  }
}

export const defaultPaymentProvider: PaymentProvider = new MockPaymentProvider();
