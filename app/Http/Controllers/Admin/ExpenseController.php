<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Period-based operating expenses (rent, salaries, marketing…) feeding the
 * P&L report. Deliberately minimal — the P&L is the primary consumer.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'category' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $from = ! empty($data['from']) ? Carbon::parse($data['from'])->startOfDay() : null;
        $to = ! empty($data['to']) ? Carbon::parse($data['to'])->endOfDay() : null;

        $rows = Expense::query()
            ->when($from, fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($to, fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($data['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $total = (clone $rows->getCollection())->sum('amount');

        return Inertia::render('Admin/Expenses/Index', [
            'rows' => $rows,
            'filters' => [
                'from' => $data['from'] ?? null,
                'to' => $data['to'] ?? null,
                'category' => $data['category'] ?? null,
                'search' => $data['search'] ?? null,
            ],
            'categories' => Expense::CATEGORIES,
            'pageSum' => (float) $total,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Expenses/Create', [
            'categories' => Expense::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['user_id'] = $request->user()->id;
        Expense::create($data);
        return redirect()->route('admin.expenses.index')->with('success', 'Expense added.');
    }

    public function edit(Expense $expense): Response
    {
        return Inertia::render('Admin/Expenses/Edit', [
            'expense' => $expense,
            'categories' => Expense::CATEGORIES,
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $expense->update($this->validated($request));
        return redirect()->route('admin.expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $expense->delete();
        return redirect()->route('admin.expenses.index')->with('success', 'Expense deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'date'],
            'category' => ['required', 'string', 'in:' . implode(',', array_keys(Expense::CATEGORIES))],
            'title' => ['required', 'string', 'max:200'],
            'note' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);
    }
}
