<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('payment.view');

        $query = Payment::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('method')) {
            $query->where('method', $request->method);
        }

        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        if ($request->has('date_from')) {
            $query->where('payment_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('payment_date', '<=', $request->date_to);
        }

        $payments = $query->with(['order', 'createdBy'])
            ->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($payments);
    }

    public function show(Payment $payment)
    {
        $this->authorize('payment.view');

        return response()->json($payment->load(['order', 'createdBy']));
    }

    public function store(Request $request)
    {
        $this->authorize('payment.create');

        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'type' => 'required|in:income,expense',
            'method' => 'required|in:cash,bank_transfer,alipay,wechat,credit,other',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'transaction_no' => 'nullable|string',
            'remark' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $order = Order::findOrFail($validated['order_id']);

            $newPaidAmount = $order->paid_amount + $validated['amount'];
            if ($newPaidAmount > $order->total) {
                return response()->json([
                    'message' => "Payment amount exceeds order balance. Remaining: " . ($order->total - $order->paid_amount)
                ], 400);
            }

            $payment = Payment::create([
                'order_id' => $validated['order_id'],
                'created_by' => $request->user()->id,
                'type' => $validated['type'],
                'method' => $validated['method'],
                'amount' => $validated['amount'],
                'payment_date' => $validated['payment_date'],
                'transaction_no' => $validated['transaction_no'] ?? null,
                'remark' => $validated['remark'] ?? null,
            ]);

            $order->increment('paid_amount', $validated['amount']);
            $order->updatePaymentStatus();

            return response()->json([
                'message' => 'Payment recorded successfully',
                'payment' => $payment,
                'order' => $order,
            ], 201);
        });
    }

    public function update(Request $request, Payment $payment)
    {
        $this->authorize('payment.edit');

        $validated = $request->validate([
            'method' => 'sometimes|in:cash,bank_transfer,alipay,wechat,credit,other',
            'amount' => 'sometimes|numeric|min:0.01',
            'payment_date' => 'sometimes|date',
            'transaction_no' => 'nullable|string',
            'remark' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($payment, $validated) {
            $order = $payment->order;

            if (isset($validated['amount'])) {
                $difference = $validated['amount'] - $payment->amount;
                $newPaidAmount = $order->paid_amount + $difference;

                if ($newPaidAmount > $order->total) {
                    return response()->json([
                        'message' => "Payment amount exceeds order balance."
                    ], 400);
                }

                if ($newPaidAmount < 0) {
                    return response()->json([
                        'message' => "Invalid payment amount."
                    ], 400);
                }

                $order->increment('paid_amount', $difference);
                $order->updatePaymentStatus();
            }

            $payment->update($validated);

            return response()->json([
                'message' => 'Payment updated successfully',
                'payment' => $payment,
                'order' => $order,
            ]);
        });
    }

    public function destroy(Payment $payment)
    {
        $this->authorize('payment.delete');

        return DB::transaction(function () use ($payment) {
            $order = $payment->order;
            $order->decrement('paid_amount', $payment->amount);
            $order->updatePaymentStatus();

            $payment->delete();

            return response()->json([
                'message' => 'Payment deleted successfully',
                'order' => $order,
            ]);
        });
    }

    public function statistics(Request $request)
    {
        $this->authorize('payment.view');

        $query = Payment::query();

        if ($request->has('date_from')) {
            $query->where('payment_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('payment_date', '<=', $request->date_to);
        }

        $stats = [
            'total_income' => (clone $query)->where('type', 'income')->sum('amount'),
            'total_expense' => (clone $query)->where('type', 'expense')->sum('amount'),
            'net_amount' => (clone $query)->where('type', 'income')->sum('amount') -
                (clone $query)->where('type', 'expense')->sum('amount'),
            'income_count' => (clone $query)->where('type', 'income')->count(),
            'expense_count' => (clone $query)->where('type', 'expense')->count(),
        ];

        return response()->json($stats);
    }
}
