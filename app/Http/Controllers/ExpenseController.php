<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Expense;
use App\Models\ExpenseImage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;


class ExpenseController extends Controller
{

    public function create(Request $request)
    {
        $userId = auth()->id() || 1;
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $usedAmount = Expense::where('user_id', $userId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $maxLimit = 6250;
        $remaining = $maxLimit - $usedAmount;

        $prefillExpense = null;
        $resubmitId = $request->query('resubmit_id');

        if ($resubmitId) {
            $prefillExpense = Expense::with('images')->findOrFail($resubmitId)->toArray();

            $types = [
                1 => 'Travel',
                2 => 'Lodging',
                3 => 'Food',
                4 => 'Printing',
                5 => 'Mobile',
                6 => 'Miscellaneous',
            ];

            $subtypes = [
                1 => '2_wheeler',
                2 => 'bus',
                3 => 'cab',
                4 => 'train',
            ];

            if (isset($prefillExpense['type'])) {
                $prefillExpense['type'] = $types[$prefillExpense['type']] ?? $prefillExpense['type'];
            }

            if (isset($prefillExpense['subtype'])) {
                $prefillExpense['subtype'] = $subtypes[$prefillExpense['subtype']] ?? $prefillExpense['subtype'];
            }

            if (isset($prefillExpense['meta_data']) && is_array($prefillExpense['meta_data'])) {
                $prefillExpense = array_merge($prefillExpense, $prefillExpense['meta_data']);
            }
        }

        return view('expenses.create', compact('usedAmount', 'maxLimit', 'remaining', 'prefillExpense', 'resubmitId'));
    }


    public function store(Request $request)
    {

        $expenses = $request->input('expenses', []);
        if (empty($expenses)) {
            return redirect()->back()->with('error', 'No expense data found.');
        }

        $typeMap = [
            'Travel' => 1,
            'Lodging' => 2,
            'Food' => 3,
            'Printing' => 4,
            'Mobile' => 5,
            'Miscellaneous' => 6,
        ];

        foreach ($expenses as $index => $expenseData) {
            $validator = Validator::make($expenseData, [

                'date' => 'required|date',
                'type' => 'required|string',
                'location' => 'required|string',
                'remarks' => 'nullable|string',
                'amount' => 'required|numeric',
                'subtype' => 'nullable|string',
                'start_reading' => 'nullable|numeric',
                'end_reading' => 'nullable|numeric',
                'from_location' => 'nullable|string',
                'to_location' => 'nullable|string',
                'checkin_date' => 'nullable|date',
                'checkout_date' => 'nullable|date|after_or_equal:checkin_date',
                'attachments' => 'nullable|min:1|max:2',
                'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf|max:2048',
            ]);

            if ($validator->fails()) {
                return redirect()->back()
                    ->withErrors(["expenses.$index" => $validator->errors()])
                    ->withInput();
            }

            $originalId = $request->input('original_expense_id');
            $meta = [];

            if ($originalId) {
                // Find original expense with its images
                $originalExpense = Expense::with('images')->find($originalId);

                if ($originalExpense) {
                    // Store complete previous expense data
                    $meta['resubmitted_from'] = $originalId;
                    $meta['previous_expense'] = [
                        'id' => $originalExpense->id,
                        'date' => $originalExpense->date,
                        'type' => $originalExpense->typeName(), // Get type name instead of ID
                        'location' => $originalExpense->location,
                        'amount' => $originalExpense->amount,
                        'remarks' => $originalExpense->remarks,
                        'meta_data' => $originalExpense->meta_data,
                        'status' => $originalExpense->statusName(), // Get status name
                        'images' => $originalExpense->images->pluck('path')->toArray(),
                        'created_at' => $originalExpense->created_at,
                    ];

                    // Cancel the original expense
                    $originalExpense->status = 5; // Cancelled
                    $originalExpense->save();
                }
            }


            $amount = $expenseData['amount'];
            $type = $typeMap[$expenseData['type']] ?? null;

            if (!empty($expenseData['subtype'])) {
                $meta['subtype'] = $expenseData['subtype'];
            }

            if ($type === 1) { // Travel
                if (($expenseData['subtype'] ?? '') === '2_wheeler') {
                    $meta['start_reading'] = $expenseData['start_reading'];
                    $meta['end_reading'] = $expenseData['end_reading'];

                    if (is_numeric($expenseData['start_reading']) && is_numeric($expenseData['end_reading'])) {
                        $calculated = ($expenseData['end_reading'] - $expenseData['start_reading']) * 3;
                        if ($expenseData['amount'] != $calculated) {
                            return redirect()->back()
                                ->withErrors(["expenses.$index.amount" => "Amount should be equal to (End - Start) * 3 = $calculated"])
                                ->withInput();
                        }
                        $amount = $calculated;
                    }
                } elseif (in_array($expenseData['subtype'], ['bus', 'cab', 'train'])) {
                    $meta['from_location'] = $expenseData['from_location'];
                    $meta['to_location'] = $expenseData['to_location'];
                }
            } elseif ($type === 2) { // Lodging
                $meta['checkin_date'] = $expenseData['checkin_date'];
                $meta['checkout_date'] = $expenseData['checkout_date'];
            } elseif ($type === 5) { // Mobile
                $amount = 250;
            }

            $expense = new Expense();
            $expense->user_id = auth()->id() ?? 1;
            $expense->date = $expenseData['date'];
            $expense->type = $type;
            $expense->location = $expenseData['location'];
            $expense->remarks = $expenseData['remarks'] ?? null;
            $expense->amount = $amount;
            $expense->approved_amount = null;
            $expense->meta_data = $meta;
            $expense->status = '1';
            $expense->save();

            if ($request->hasFile("expenses.$index.attachments")) {
                foreach ($request->file("expenses.$index.attachments") as $file) {
                    $originalName = $file->getClientOriginalName();
                    $path = $file->storeAs('expenses/attachments', $originalName, 'public');

                    ExpenseImage::create([
                        'expense_id' => $expense->id,
                        'path' => $path,
                    ]);
                }
            }
        }

        return redirect()->back()->with('success', 'Expenses saved successfully.');
    }

    public function index(Request $request)
    {
        $userId = 1;
        $query = Expense::where('user_id', $userId);

        $typeMap = array_flip(Expense::typeMap());
        $statusMap = array_flip(Expense::statusMap());

        // If month is passed (format: Y-m), filter that month only
        if ($request->filled('month')) {
            try {
                $startOfMonth = Carbon::createFromFormat('Y-m', $request->month)->startOfMonth();
                $endOfMonth = Carbon::createFromFormat('Y-m', $request->month)->endOfMonth();
                $query->whereBetween('date', [$startOfMonth, $endOfMonth]);
            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Invalid month format.');
            }
        }
        // If no month is passed, default to current month
        elseif (!$request->filled('date_from') && !$request->filled('date_to')) {
            $startOfMonth = Carbon::now()->startOfMonth();
            $today = Carbon::now();
            $query->whereBetween('date', [$startOfMonth, $today]);
        }

        // Date range filters (overrides month if both given)
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        if ($request->filled('type') && isset($typeMap[$request->type])) {
            $query->where('type', $typeMap[$request->type]);
        }
        if ($request->filled('status') && isset($statusMap[$request->status])) {
            $query->where('status', $statusMap[$request->status]);
        }

        $expenses = $query->with(['images', 'approver'])
            ->orderBy('date', 'desc')
            ->get();

        $grouped = $expenses->groupBy(function ($item) {
            return Carbon::parse($item->date)->format('Y-m-d');
        });

        $expenseGroups = [];
        foreach ($grouped as $date => $items) {
            $total = $items->sum('amount');
            $approved = $items->sum('approved_amount');
            $statuses = $items->pluck('status')->unique();

            $statusName = $statuses->count() === 1
                ? $items->first()->statusName()
                : 'Partially Approved';

            $expenseGroups[] = [
                'date' => $date,
                'total' => $total,
                'approved' => $approved,
                'status' => $statusName,
                'expenses' => $items->map(function ($expense) {
                    return [
                        'id' => $expense->id,
                        'type' => $expense->typeName(),
                        'amount' => $expense->amount,
                        'approved_amount' => $expense->approved_amount,
                        'status' => $expense->statusName(),
                        'images' => $expense->images,
                        'meta_data' => $expense->meta_data ?? [],
                        'remarks' => $expense->remarks,
                        'approved_by' => $expense->approver->name ?? null,
                        'approved_at' => $expense->approved_at,
                        'admin_comment' => $expense->admin_comment,
                    ];
                }),
            ];
        }

        return view('expenses.view', compact('expenseGroups'));
    }


    public function checkStatus($id)
    {
        $expense = Expense::find($id);

        if (!$expense) {
            return response()->json(['status' => 'error', 'message' => 'Expense not found.'], 404);
        }

        if ($expense->status !== '1') {
            return response()->json(['status' => 'error', 'message' => 'Action has been Taken on this expense. Resubmission is no longer allowed ']);
        }

        return response()->json(['status' => 'ok']);
    }
}