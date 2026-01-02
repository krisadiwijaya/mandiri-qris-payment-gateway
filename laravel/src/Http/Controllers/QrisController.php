<?php

namespace Mandiri\Qris\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Mandiri\Qris\Facades\MandiriQris;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class QrisController extends Controller
{
    /**
     * Create QRIS payment
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'reference' => 'required|string|max:255|unique:mandiri_qris_payments,reference',
            'callback_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $qrisData = MandiriQris::createQris(
                $request->amount,
                $request->reference,
                $request->callback_url
            );

            // Save to database
            DB::table('mandiri_qris_payments')->insert([
                'qr_id' => $qrisData['qr_id'],
                'reference' => $qrisData['reference'],
                'qr_string' => $qrisData['qr_string'],
                'qr_image_url' => $qrisData['qr_image_url'],
                'amount' => $qrisData['amount'],
                'status' => $qrisData['status'],
                'expired_at' => $qrisData['expired_at'],
                'metadata' => json_encode($request->except(['amount', 'reference', 'callback_url'])),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $qrisData,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check payment status
     *
     * @param Request $request
     * @param string $qrId
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkStatus(Request $request, $qrId)
    {
        try {
            // Get payment from database
            $payment = DB::table('mandiri_qris_payments')
                ->where('qr_id', $qrId)
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                ], 404);
            }

            // Check if already completed
            if ($payment->status === 'COMPLETED') {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'qr_id' => $payment->qr_id,
                        'status' => $payment->status,
                        'amount' => $payment->amount,
                        'paid_at' => $payment->paid_at,
                        'transaction_id' => $payment->transaction_id,
                    ],
                ]);
            }

            // Check status from Mandiri API
            $statusData = MandiriQris::checkStatus($qrId, $payment->reference);

            // Update database if status changed
            if ($statusData['status'] !== $payment->status) {
                DB::table('mandiri_qris_payments')
                    ->where('qr_id', $qrId)
                    ->update([
                        'status' => $statusData['status'],
                        'transaction_id' => $statusData['transaction_id'],
                        'paid_at' => $statusData['paid_at'],
                        'updated_at' => now(),
                    ]);
            }

            return response()->json([
                'success' => true,
                'data' => $statusData,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle webhook notification
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function webhook(Request $request)
    {
        try {
            // Validate webhook signature (implement your own validation)
            // $signature = $request->header('X-Signature');
            // if (!$this->validateSignature($request->all(), $signature)) {
            //     return response()->json(['success' => false], 401);
            // }

            $qrId = $request->input('qrId') ?? $request->input('originalReferenceNo');
            $status = $request->input('transactionStatusCode');

            if (!$qrId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing qrId',
                ], 400);
            }

            // Get payment from database
            $payment = DB::table('mandiri_qris_payments')
                ->where('qr_id', $qrId)
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found',
                ], 404);
            }

            // Map status code
            $statusMap = [
                '00' => 'COMPLETED',
                '03' => 'PENDING',
                '05' => 'EXPIRED',
            ];
            $newStatus = $statusMap[$status] ?? 'FAILED';

            // Update payment status
            $updateData = [
                'status' => $newStatus,
                'updated_at' => now(),
            ];

            if ($newStatus === 'COMPLETED') {
                $updateData['transaction_id'] = $request->input('referenceNo');
                $updateData['paid_at'] = now();
            }

            DB::table('mandiri_qris_payments')
                ->where('qr_id', $qrId)
                ->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
