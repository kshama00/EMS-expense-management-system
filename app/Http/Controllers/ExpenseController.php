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
        $userId = auth()->id() ?? 1;
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $usedAmount = Expense::where('user_id', $userId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $maxLimit = 6250;
        $remaining = $maxLimit - $usedAmount;

        $hasMobile = Expense::where('user_id', $userId)
            ->where('type', '5')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->exists();

        // Get type and subtype mappings - FIXED: Define these variables first
        $types = Expense::typeMap();
        $subtypes = Expense::subtypeMap();

        // Get existing expenses for duplicate checking with more details
        $existingExpenses = Expense::where('user_id', $userId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->where('status', '!=', '5') // Exclude cancelled expenses
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($expense) use ($types) { // FIXED: Use the $types variable here
                return [
                    'id' => $expense->id,
                    'type' => $types[$expense->type] ?? 'Unknown',
                    'amount' => (float) $expense->amount,
                    'date' => $expense->date,
                    'status' => $expense->status,
                    'location' => $expense->location,
                    'remarks' => $expense->remarks,
                ];
            });

        $prefillExpense = null;
        $resubmitId = $request->query('resubmit_id');

        if ($resubmitId) {
            $prefillExpense = Expense::with('images')->findOrFail($resubmitId)->toArray();

            // FIXED: Now $types and $subtypes are properly defined
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

        return view('expenses.create', compact('usedAmount', 'maxLimit', 'remaining', 'prefillExpense', 'resubmitId', 'hasMobile', 'existingExpenses'));
    }


    public function store(Request $request)
    {
        $expenses = $request->input('expenses', []);

        if (empty($expenses)) {
            return redirect()->back()->with('error', 'No expense data found.');
        }

        $typeMap = array_flip(Expense::typeMap());

        $userId = auth()->id() ?? 1;

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

            $typeId = $typeMap[$expenseData['type']] ?? null;
            $originalId = $request->input('original_expense_id');

            if ($typeId) {
                $duplicateQuery = Expense::where('user_id', $userId)
                    ->where('type', $typeId)
                    ->where('amount', $expenseData['amount'])
                    ->where('date', $expenseData['date'])
                    ->where('status', '!=', '5');

                // Exclude the original expense if this is a resubmission
                if ($originalId) {
                    $duplicateQuery->where('id', '!=', $originalId);
                }

                $duplicateExists = $duplicateQuery->exists();

                if ($duplicateExists) {
                    return redirect()->back()
                        ->withErrors(["expenses.$index" => "A similar expense entry already exists with the same type, amount, and date."])
                        ->withInput();
                }
            }

            // Additional validation for lodging dates
            if ($expenseData['type'] === 'Lodging') {
                $globalDate = $expenseData['date'];
                $checkinDate = $expenseData['checkin_date'] ?? null;
                $checkoutDate = $expenseData['checkout_date'] ?? null;

                // Validate checkin date is same as global date
                if ($checkinDate !== $globalDate) {
                    return redirect()->back()
                        ->withErrors(["expenses.$index.checkin_date" => "Check-in date must be same as expense date"])
                        ->withInput();
                }

                // Validate checkout date is same or +1 day
                if ($checkoutDate) {
                    $globalDateObj = Carbon::parse($globalDate);
                    $checkoutDateObj = Carbon::parse($checkoutDate);
                    $maxCheckoutDate = $globalDateObj->copy()->addDay();

                    if ($checkoutDateObj->lt($globalDateObj) || $checkoutDateObj->gt($maxCheckoutDate)) {
                        return redirect()->back()
                            ->withErrors(["expenses.$index.checkout_date" => "Check-out date can only be same as check-in date or one day later"])
                            ->withInput();
                    }
                }
            }

            $originalId = $request->input('original_expense_id');
            $meta = [];

            if ($originalId) {
                $originalExpense = Expense::with('images')->find($originalId);

                if ($originalExpense) {
                    $resubmittedIds = [];

                    if (
                        isset($originalExpense->meta_data['Resubmitted_expense_ids']) &&
                        is_array($originalExpense->meta_data['Resubmitted_expense_ids'])
                    ) {
                        $resubmittedIds = $originalExpense->meta_data['Resubmitted_expense_ids'];
                    }

                    if (!in_array($originalId, $resubmittedIds)) {
                        $resubmittedIds[] = $originalId;
                    }

                    $meta['Resubmitted_expense_ids'] = $resubmittedIds;

                    // Cancel original
                    $originalExpense->status = 5;
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

    public function checkDuplicate(Request $request)
    {
        // FIXED: Define $typeMap variable here
        $typeMap = array_flip(Expense::typeMap());

        $request->validate([
            'type' => 'required|string',
            'amount' => 'required|numeric',
            'date' => 'required|date',
            'exclude_id' => 'nullable|integer' // For resubmission cases
        ]);

        $typeId = $typeMap[$request->type] ?? null;
        if (!$typeId) {
            return response()->json(['duplicate' => false]);
        }

        $userId = auth()->id() ?? 1;
        $query = Expense::where('user_id', $userId)
            ->where('type', $typeId)
            ->where('amount', $request->amount)
            ->where('date', $request->date)
            ->where('status', '!=', '5'); // Exclude cancelled

        if ($request->exclude_id) {
            $query->where('id', '!=', $request->exclude_id);
        }

        $duplicate = $query->first();

        if ($duplicate) {
            return response()->json([
                'duplicate' => true,
                'expense' => [
                    'id' => $duplicate->id,
                    'type' => $request->type,
                    'amount' => $duplicate->amount,
                    'date' => $duplicate->date,
                    'status' => $duplicate->statusName(),
                    'location' => $duplicate->location,
                    'remarks' => $duplicate->remarks,
                ]
            ]);
        }

        return response()->json(['duplicate' => false]);
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
            ->where('status', '!=', '5')
            ->get();

        $grouped = $expenses->groupBy(function ($item) {
            return Carbon::parse($item->date)->format('Y-m-d');
        });

        $expenseGroups = [];
        foreach ($grouped as $date => $items) {
            $total = $items->sum('amount');
            $approved = $items->sum('approved_amount');
            $statuses = $items->pluck('status')->unique();


            if ($statuses->contains('1')) {
                $statusName = 'Pending';
            } elseif ($statuses->count() === 1) {
                $statusName = $statuses->first();
            } else {
                $statusName = 'Partially Approved';
            }

            $expenseGroups[] = [
                'date' => $date,
                'total' => $total,
                'approved' => $approved,
                'status' => $statusName,
                'expenses' => $items->map(function ($expense) {
                    return [
                        'id' => $expense->id,
                        'type' => Expense::typeMap()[$expense->type] ?? 'Unknown',
                        'amount' => $expense->amount,
                        'approved_amount' => $expense->approved_amount,
                        'status' => Expense::statusMap()[$expense->status] ?? 'Unknown',
                        'images' => $expense->images,
                        'meta_data' => collect($expense->meta_data ?? [])
                            ->except(['Resubmitted_expense_ids'])
                            ->toArray(),
                        'remarks' => $expense->remarks,
                        'approved_by' => $expense->approver->name ?? null,
                        'approved_at' => $expense->approved_at,
                        'admin_comment' => $expense->admin_comment,
                        'has_history' => isset($expense->meta_data['Resubmitted_expense_ids']) && !empty($expense->meta_data['Resubmitted_expense_ids']),
                        'history' => isset($expense->meta_data['Resubmitted_expense_ids'])
                            ? Expense::with('images')
                                ->whereIn('id', $expense->meta_data['Resubmitted_expense_ids'])
                                ->get()
                                ->map(function ($exp) {
                                    return [
                                        'id' => $exp->id,
                                        'date' => Carbon::parse($exp->date)->format('Y-m-d'),
                                        'type' => Expense::typeMap()[$exp->type] ?? 'Unknown',
                                        'amount' => $exp->amount,
                                        'status' => Expense::statusMap()[$exp->status] ?? 'Unknown',
                                        'remarks' => $exp->remarks,
                                        'meta_data' => collect($exp->meta_data ?? [])
                                            ->except(['Resubmitted_expense_ids'])
                                            ->toArray(),
                                        'images' => $exp->images->map(fn($img) => ['path' => $img->path])->toArray(),
                                        'location' => $exp->location,
                                    ];
                                })
                                ->toArray() : []
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