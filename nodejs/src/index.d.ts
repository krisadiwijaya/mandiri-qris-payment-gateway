export interface MandiriQrisConfig {
    clientId: string;
    clientSecret: string;
    baseUrl?: string;
    sandbox?: boolean;
}

export interface QRParams {
    amount: number;
    merchantId: string;
    terminalId: string;
    invoiceNumber?: string;
    customerName?: string;
    customerPhone?: string;
}

export interface QRResponse {
    transaction_id: string;
    qr_string: string;
    qr_image: string;
    amount: number;
    merchant_id: string;
    terminal_id: string;
}

export interface PaymentStatus {
    transaction_id: string;
    status: 'PENDING' | 'SUCCESS' | 'FAILED' | 'EXPIRED';
    amount: number;
    merchant_id: string;
    paid_at?: string;
}

export interface WebhookPayload {
    transaction_id: string;
    status: string;
    amount: number;
    merchant_id: string;
    paid_at?: string;
    signature: string;
}

export declare class MandiriQrisClient {
    constructor(config: MandiriQrisConfig);
    
    generateQR(params: QRParams): Promise<QRResponse>;
    
    checkPaymentStatus(transactionId: string): Promise<PaymentStatus>;
    
    verifyWebhookSignature(payload: WebhookPayload, signature: string): boolean;
    
    handleWebhook(rawPayload: string, signature: string): WebhookPayload;
    
    pollPaymentStatus(
        transactionId: string,
        maxAttempts?: number,
        intervalSeconds?: number
    ): Promise<PaymentStatus>;
}
