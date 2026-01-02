/**
 * TypeScript Type Definitions for Mandiri QRIS SDK
 */

export interface MandiriQrisOptions {
    clientId: string;
    clientSecret: string;
    environment?: 'sandbox' | 'production';
    merchantNmid: string;
    merchantName: string;
    merchantCity: string;
    qrisExpiryMinutes?: number;
    timeout?: number;
}

export interface CreateQrisOptions {
    amount: number;
    reference: string;
    callbackUrl?: string;
}

export interface QrisResponse {
    qrId: string;
    qrString: string;
    qrImageUrl: string;
    status: string;
    amount: number;
    reference: string;
    expiredAt: string;
}

export interface PaymentStatus {
    qrId: string;
    status: 'PENDING' | 'COMPLETED' | 'EXPIRED' | 'FAILED' | 'UNKNOWN';
    amount: number | null;
    paidAt: string | null;
    transactionId: string | null;
}

export class MandiriQrisError extends Error {
    statusCode: number;
    response: any;
    
    constructor(message: string, statusCode?: number, response?: any);
}

export class MandiriQris {
    constructor(options: MandiriQrisOptions);
    
    getAccessToken(): Promise<string>;
    createQris(options: CreateQrisOptions): Promise<QrisResponse>;
    checkStatus(qrId: string): Promise<PaymentStatus>;
    clearToken(): void;
    setQrisExpiryMinutes(minutes: number): void;
    getEnvironment(): string;
}

export default MandiriQris;
