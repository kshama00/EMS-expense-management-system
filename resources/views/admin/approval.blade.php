@extends('layouts.app')

@section('styles')
    @vite('resources/css/admin.css')
@endsection

<meta name="csrf-token" content="{{ csrf_token() }}">

@section('content')
    <h2>Expense Approval</h2>

    <form>
        <label for="type">Type</label>
        <select name="type" id="type">
            <option value="">All</option>
            <option value="Travel">Travel</option>
            <option value="Lodging">Lodging</option>
            <option value="Food">Food</option>
            <option value="Mobile">Mobile</option>
            <option value="Printing">Printing</option>
            <option value="Miscellaneous">Miscellaneous</option>
        </select>

        <label for="status">Status:</label>
        <select name="status" id="status">
            <option value="">All</option>
            <option value="approved">Approved</option>
            <option value="pending">Pending</option>
            <option value="rejected">Rejected</option>
            <option value="partially_approved">Partially Approved</option>
        </select>

        <button type="submit">Apply</button>
    </form>

    <hr>

    @if ($type || $status)
        <h3>Filtered Expenses ({{ $type ?? ucfirst($status) }})</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Approved Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $expense)
                    <tr>
                        <td>{{ $expense->user->name ?? '-' }}</td>
                        <td>{{ \Carbon\Carbon::parse($expense->date)->format('d M Y') }}</td>
                        <td>{{ $expense->typeName() }}</td>
                        <td>₹{{ number_format($expense->amount, 2) }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $expense->statusName())) }}</td>
                        <td>₹{{ number_format($expense->approved_amount, 2) }}</td>
                        <td><a href="#">View</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No matching expenses found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @else
        <div class="group-container">
            @foreach ($groupedExpenses as $userId => $months)
                @foreach ($months as $month => $expenses)
                    @php
                        $firstExpense = $expenses->first();
                        $approvedAt = \Carbon\Carbon::parse($firstExpense->approved_at);
                        $editableUntil = $approvedAt->copy()->addMonth()->startOfMonth()->addDays(4);
                        $canEditApproval = now()->lte($editableUntil);

                        // Get overall status for this card from controller
                        $cardOverallStatusCode = $overallStatuses["{$userId}_{$month}"] ?? 1;
                        $cardStatusInfo = App\Http\Controllers\Admin\ApprovalController::getStatusInfo($cardOverallStatusCode);
                        $cardOverallStatus = $cardStatusInfo['name'];
                        $cardStatusClass = $cardStatusInfo['class'];
                    @endphp

                    <div class="expense-card">
                        <div class="expense-header">
                            <div class="expense-summary">
                                <strong>HQ:</strong> {{ $expenses->first()->user->hqNames ?? 'N/A' }} |
                                <strong>User Name:</strong> {{ $expenses->first()->user->name ?? 'N/A' }} |
                                <strong>Month:</strong> {{ $month }} |
                                <strong>Total:</strong> ₹{{ number_format($expenses->sum('amount'), 2) }} |
                                <strong>Approved:</strong> ₹{{ number_format($expenses->sum('approved_amount'), 2) }} |
                                <strong>Status:</strong>
                                <span class="status-badge {{ $cardStatusClass }}">{{ $cardOverallStatus }}</span>

                                @if ($canEditApproval)
                                    <select class="action-select-global action" style="min-width: 140px;">
                                        <option value="">Select Action</option>
                                        <option value="approve">Approve</option>
                                        <option value="reject">Reject</option>
                                    </select>
                                @endif

                                <button class="toggle-details-btn">View</button>
                            </div>
                        </div>

                        <br>

                        <div class="expense-details" style="display: none;">
                            @foreach ($expenses->groupBy('date') as $date => $dateExpenses)
                                @php
                                    $approvedAt = \Carbon\Carbon::parse($expenses->first()->approved_at);
                                    $editableUntil = $approvedAt->copy()->addMonth()->startOfMonth()->addDays(4);
                                    $canEditApproval = now()->lte($editableUntil);

                                    $total = $dateExpenses->sum('amount');
                                    $approved = $dateExpenses->sum('approved_amount');

                                    // Get overall status for this date group from controller
                                    $dateOverallStatusCode = $dateStatuses["{$userId}_{$month}_{$date}"] ?? 1;
                                    $dateStatusInfo = App\Http\Controllers\Admin\ApprovalController::getStatusInfo($dateOverallStatusCode);
                                    $dateOverallStatus = $dateStatusInfo['name'];
                                    $dateStatusClass = $dateStatusInfo['class'];
                                @endphp

                                <div class="date-expense-card"
                                    style="border: 1px solid #ccc; border-radius: 5px; margin-bottom: 10px; padding: 10px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <input type="checkbox" class="date-group-checkbox" name="date_groups[]" value="{{ $date }}">
                                            <strong>Date:</strong> {{ \Carbon\Carbon::parse($date)->format('Y-m-d') }} |
                                            <strong>Total Expense:</strong> ₹{{ number_format($total, 2) }} |
                                            <strong>Approved:</strong> ₹{{ number_format($approved, 2) }} |
                                            <strong>Status:</strong>
                                            <span class="status-badge {{ $dateStatusClass }}">{{ $dateOverallStatus }}</span>
                                        </div>
                                        <div style="display: flex; gap: 10px; align-items: center;">
                                            @if ($canEditApproval)
                                                <select class="action-select-global action" style="min-width: 140px;">
                                                    <option value="">Select Action</option>
                                                    <option value="approve">Approve</option>
                                                    <option value="reject">Reject</option>
                                                    <option value="partially approve">Partially Approve</option>
                                                </select>
                                            @endif
                                            <button class="view-more-btn btn btn-primary btn-sm">View</button>
                                        </div>
                                    </div>

                                    <div class="view-more-content" style="display: none; margin-top: 10px;">
                                        <table class="admin-table">
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Submitted Amount</th>
                                                    <th>Approved Amount</th>
                                                    <th>Status</th>
                                                    <th>Attachment</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($dateExpenses as $expense)
                                                    @php
                                                        $individualStatusInfo = App\Http\Controllers\Admin\ApprovalController::getStatusInfo($expense->status);
                                                        $individualStatus = $individualStatusInfo['name'];
                                                        $individualStatusClass = $individualStatusInfo['class'];
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <input type="checkbox" class="expense-type-checkbox"
                                                                data-expense-id="{{ $expense->id }}">

                                                            {{ $expense->typeName() }}
                                                        </td>
                                                        <td class="submitted-amount">₹{{ number_format($expense->amount, 2) }}</td>
                                                        <td class="approved-amount">₹{{ number_format($expense->approved_amount, 2) }}</td>
                                                        <td class="expense-status">
                                                            <span class="status-badge {{ $individualStatusClass }}">
                                                                {{ $individualStatus }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            @foreach ($expense->images as $image)
                                                                <a href="{{ asset('storage/' . $image->path) }}" target="_blank">
                                                                    {{ basename($image->path) }}
                                                                </a>
                                                            @endforeach
                                                        </td>
                                                        <td>
                                                            <button class="expense-view-more">View More</button>
                                                        </td>
                                                    </tr>
                                                    <tr class="view-more-row" style="display: none;">
                                                        <td colspan="6">
                                                            @if(is_array($expense['meta_data']))
                                                                <strong>Description:</strong>
                                                                <ul>
                                                                    @foreach ($expense['meta_data'] as $key => $value)
                                                                        <li>
                                                                            <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong>
                                                                            @if (is_array($value))
                                                                                <pre>{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                                            @else
                                                                                {{ $value }}
                                                                            @endif
                                                                        </li>

                                                                    @endforeach
                                                                </ul>
                                                            @endif
                                                            <strong>User Remarks:</strong> {{ $expense['remarks'] ?? 'N/A' }}<br>
                                                            <strong>Approved By:</strong> {{ $expense['approved_by'] ?? 'N/A' }}<br>
                                                            <strong>Approved At:</strong> {{ $expense['approved_at'] ?? 'N/A' }}<br>
                                                            <strong>Approver Comment:</strong> {{ $expense['admin_comment'] ?? 'N/A' }}<br>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @endforeach

            @if ($canEditApproval)
                <div style="margin-top: 15px;">
                    <select class="action-select-global action" style="min-width: 140px;">
                        <option value="">Select Action</option>
                        <option value="approve">Approve</option>
                        <option value="reject">Reject</option>
                    </select>
                </div>
            @endif
        </div>
    @endif


@endsection

@section('scripts')
    @vite('resources/js/admin.js')
@endsection