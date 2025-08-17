<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Expense;
use App\Models\Headquarter;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $typeMap = [
            'Travel' => 1,
            'Lodging' => 2,
            'Food' => 3,
            'Printing' => 4,
            'Mobile' => 5,
            'Miscellaneous' => 6,
        ];

        $month = $request->input('month');
        $status = $request->input('status');
        $hq = $request->input('hq');
        $type = $request->input('type');

        // Load user and approver relationships
        $query = Expense::with(['images', 'user', 'approver']) // Added 'approver' relationship
            ->where('status', '!=', 5);

        if ($month) {
            $query->whereRaw('DATE_FORMAT(date, "%Y-%m") = ?', [$month]);
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($hq) {
            $query->whereHas('user', function ($q) use ($hq) {
                $q->where('hq_code', $hq);
            });
        }

        if ($type && isset($typeMap[$type])) {
            $query->where('type', $typeMap[$type]);
        }

        $expenses = $query->get();

        // Transform expenses to clean meta_data and add user/approver names
        $expenses = $expenses->map(function ($expense) {
            // Clean meta_data by removing Resubmitted_expense_ids
            $cleanMetaData = collect($expense->meta_data ?? [])
                ->except(['Resubmitted_expense_ids'])
                ->toArray();

            // Create a new expense object with cleaned data
            $expense->cleaned_meta_data = $cleanMetaData;

            // Add user name for display
            $expense->user_name = $expense->user ? $expense->user->name : 'Unknown User';

            // Add approver name for display
            $expense->approver_name = $expense->approver ? $expense->approver->name : null;

            return $expense;
        });

        // Only group if no type or status is applied
        $groupedExpenses = (!$type && !$status)
            ? $expenses->groupBy([
                fn($e) => $e->user_id,
                fn($e) => \Carbon\Carbon::parse($e->date)->format('Y-m'),
            ])
            : collect();

        $overallStatuses = [];
        $dateStatuses = [];

        foreach ($groupedExpenses as $userId => $months) {
            foreach ($months as $monthKey => $group) {
                $statuses = $group->pluck('status')->unique()->values()->all();

                if (in_array(1, $statuses)) {
                    $overall = 1;
                } elseif (count($statuses) === 1) {
                    $overall = $statuses[0];
                } else {
                    $overall = 4;
                }

                $overallStatuses["{$userId}_{$monthKey}"] = $overall;

                // Calculate date-wise statuses within this card
                $dateGroups = $group->groupBy('date');
                foreach ($dateGroups as $date => $dateExpenses) {
                    $dateStatuses_temp = $dateExpenses->pluck('status')->unique()->values()->all();

                    if (in_array(1, $dateStatuses_temp)) {
                        $dateOverall = 1;
                    } elseif (count($dateStatuses_temp) === 1) {
                        $dateOverall = $dateStatuses_temp[0];
                    } else {
                        $dateOverall = 4;
                    }

                    $dateStatuses["{$userId}_{$monthKey}_{$date}"] = $dateOverall;
                }
            }
        }

        $editableUntil = null;
        $canEditApproval = true;

        $firstExpense = $expenses->first();

        if ($firstExpense && $firstExpense->date) { // Fixed: removed extra $date variable
            $submittedAt = \Carbon\Carbon::parse($firstExpense->date);
            $editableUntil = $submittedAt->copy()->addMonth()->startOfMonth()->addDays(4); // Fixed: use $submittedAt instead of $date

            if (now()->gt($editableUntil)) {
                $canEditApproval = false;
            }
        }

        return view('admin.approval', compact('groupedExpenses', 'expenses', 'type', 'status', 'editableUntil', 'canEditApproval', 'overallStatuses', 'dateStatuses'));
    }

    public function bulkUpdate(Request $request)
    {
        // Updated status mapping with consistent naming
        $statusMap = [
            'Pending' => 1,
            'Approved' => 2,
            'Rejected' => 3,
            'Partially_Approved' => 4,
            'approve' => 2,          // Maps to approved
            'approved' => 2,
            'reject' => 3,           // Maps to rejected
            'rejected' => 3,
            'pending' => 1,
            'partially approve' => 4,
            'partially_approved' => 4,
        ];

        // Status code to name mapping for consistent response
        $statusNames = [
            1 => 'Pending',
            2 => 'Approved',
            3 => 'Rejected',
            4 => 'Partially Approved',
            5 => 'Cancelled'
        ];

        $expenses = $request->input('expenses', []);
        $updatedCount = 0;
        $errors = [];
        $updatedExpenses = []; // Track updated expenses with their new status names

        foreach ($expenses as $item) {
            try {
                $expense = Expense::find($item['id']);

                if (!$expense) {
                    $errors[] = "Expense ID {$item['id']} not found";
                    continue;
                }

                $newStatus = $statusMap[$item['status']] ?? 1;
                $expense->status = $newStatus;

                // Set approved amount
                if (isset($item['approved_amount'])) {
                    $expense->approved_amount = $item['approved_amount'];
                } else {
                    switch ($item['status']) {
                        case 'approve':
                        case 'approved':
                            $expense->approved_amount = $expense->amount;
                            break;
                        case 'reject':
                        case 'rejected':
                        case 'pending':
                            $expense->approved_amount = 0;
                            break;
                        default:
                            $expense->approved_amount = $expense->approved_amount ?? 0;
                    }
                }

                $expense->approved_by = auth()->id() ?? 1;
                $expense->admin_comment = $item['admin_comment'] ?? null;
                $expense->approved_at = now();

                if ($expense->save()) {
                    $updatedCount++;
                    // Add the expense with its proper status name for frontend update
                    $updatedExpenses[] = [
                        'id' => $expense->id,
                        'status_code' => $newStatus,
                        'status_name' => $statusNames[$newStatus],
                        'approved_amount' => $expense->approved_amount
                    ];
                } else {
                    $errors[] = "Failed to save expense ID {$item['id']}";
                }

            } catch (\Exception $e) {
                $errors[] = "Error processing expense ID {$item['id']}: " . $e->getMessage();
                \Log::error("Error updating expense {$item['id']}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => $updatedCount > 0,
            'updated' => $updatedCount,
            'errors' => $errors,
            'updated_expenses' => $updatedExpenses, // Include updated expenses with proper status names
            'message' => $updatedCount > 0
                ? 'Expenses updated successfully'
                : 'No expenses were updated'
        ]);
    }

    public function checkStatus($id)
    {
        try {
            // Fresh database query to get current status
            $expense = Expense::find($id);

            if (!$expense) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Expense not found.'
                ], 404);
            }

            // Check if expense is still pending (status = 1)
            if ((int) $expense->status === 5) {
                $statusNames = [
                    1 => 'Pending',
                    2 => 'Approved',
                    3 => 'Rejected',
                    4 => 'Partially Approved',
                    5 => 'Cancelled'
                ];

                $currentStatus = $statusNames[$expense->status] ?? 'unknown';

                return response()->json([
                    'status' => 'blocked',
                    'message' => "Expense is no longer Pending. Current status: {$currentStatus}. It may have been resubmitted.",
                    'current_status' => $expense->status
                ]);
            }

            // Check if we're within the editable time period (only if previously approved)
            if ($expense->date) {
                $date = \Carbon\Carbon::parse($expense->date);
                $editableUntil = $date->copy()->addMonth()->startOfMonth()->addDays(4);

                if (now()->gt($editableUntil)) {
                    return response()->json([
                        'status' => 'blocked',
                        'message' => 'The approval period has expired. No changes can be made to this expense.'
                    ]);
                }
            }

            return response()->json([
                'status' => 'ok',
                'current_status' => $expense->status,
                'message' => 'Expense is ready for action'
            ]);

        } catch (\Exception $e) {
            \Log::error("Error checking expense status for ID {$id}: " . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check expense status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request)
    {
        return $this->bulkUpdate($request);
    }

    public static function getStatusInfo($statusCode)
    {
        $statusMap = [
            1 => ['name' => 'Pending', 'class' => 'status-pending'],
            2 => ['name' => 'Approved', 'class' => 'status-approved'],
            3 => ['name' => 'Rejected', 'class' => 'status-rejected'],
            4 => ['name' => 'Partially Approved', 'class' => 'status-partially-approved'],
            5 => ['name' => 'Cancelled', 'class' => 'status-cancelled']
        ];

        return $statusMap[$statusCode] ?? ['name' => 'Unknown', 'class' => 'status-unknown'];
    }

    public static function calculateOverallStatus($expenses)
    {
        $statuses = $expenses->pluck('status')->unique()->values()->all();

        if (in_array(1, $statuses)) {
            return 1;
        } elseif (count($statuses) === 1) {
            return $statuses[0];
        } else {
            return 4;
        }
    }
}