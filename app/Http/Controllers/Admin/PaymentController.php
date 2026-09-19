<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereLike('payment_id', "%$search%")
                ->orWhereLike('type', "%$search%")
                ->orWhereLike('amount', "%$search%")
                ->orWhereLike('status', "%$search%");
        }

        $payments = $query->paginate(5)->withQueryString();

        return Inertia::render('Admin/Payment/Index', [
            'payments' => $payments,
            'filters' => $request->only(['search']),
        ]);
    }

    public function delete(Payment $payment)
    {
        $payment->delete();

        return redirect()->back()->with('message', 'Payment Deleted successfully');
    }
}
