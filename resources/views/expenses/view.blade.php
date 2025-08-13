@extends('layouts.app')

@section('styles')
    @vite('resources/css/view.css')
@endsection

@section('content')
    @php
        $today = \Carbon\Carbon::today()->format('Y-m-d');
    @endphp
    <div class="expenses-container">
        <div class="header-row">
            <h2>Expense List</h2>
            <a href="{{ route('expenses.create') }}" class="add-expense-button">Add Expenses</a>
        </div>


        <div class="form-actions">
            <form method="GET" action="{{ route('expenses.view') }}" class="filter-form">
                <div class="filter-fields">
                    <h4>Filters</h4>

                    <div class="filter-field">
                        <label for="date_from">From</label>
                        <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}"
                            max="{{ $today }}">
                    </div>

                    <div class="filter-field">
                        <label for="date_to">To</label>
                        <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}" max="{{ $today }}">
                    </div>

                    <div class="filter-field">
                        <label for="type">Type</label>
                        <select name="type" id="type">
                            <option value="">All</option>
                            <option value="Travel" {{ request('type') == 'Travel' ? 'selected' : '' }}>Travel</option>
                            <option value="Lodging" {{ request('type') == 'Lodging' ? 'selected' : '' }}>Lodging</option>
                            <option value="Food" {{ request('type') == 'Food' ? 'selected' : '' }}>Food</option>
                            <option value="Mobile" {{ request('type') == 'Mobile' ? 'selected' : '' }}>Mobile</option>
                            <option value="Printing" {{ request('type') == 'Printing' ? 'selected' : '' }}>Printing</option>
                            <option value="Miscellaneous" {{ request('type') == 'Miscellaneous' ? 'selected' : '' }}>
                                Miscellaneous
                            </option>
                        </select>
                    </div>

                    <div class="filter-field">
                        <label for="status">Status</label>
                        <select name="status" id="status">
                            <option value="">All</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                            <option value="partially_approved" {{ request(key: 'status') == 'partially_approved' ? 'selected' : '' }}>Partially Approved </option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>

                        </select>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="apply-button">Apply</button>
                        <a href="{{ route('expenses.view') }}" class="reset-button">Reset</a>
                    </div>
                </div>
            </form>
        </div>
        <hr>
        @foreach ($expenseGroups as $group)
            <div class="expense-card">
                <div class="expense-header">
                    <div class="expense-summary">
                        <strong>Date:</strong> {{ \Carbon\Carbon::parse($group['date'])->format('Y-m-d') }} |
                        <strong>Total Expense:</strong> ₹{{ number_format($group['total'], 2) }} |
                        <strong>Approved:</strong> ₹{{ number_format($group['approved'], 2) }} |
                        <strong>Status:</strong> <span class="status-value">{{ $group['status'] }}</span>
                    </div>
                    <button class="toggle-details-btn">View </button>
                </div>

                <div class="expense-details" style="display: none;">
                    <table class="expenses-table">
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
                            @foreach ($group['expenses'] as $expense)
                                <tr data-date="{{ $group['date'] }}" data-type="{{ $expense['type'] }}"
                                    data-status="{{ $expense['status'] }}">
                                    <td>{{ $expense['type'] }}</td>
                                    <td>₹{{ number_format($expense['amount'], 2) }}</td>
                                    <td>₹{{ number_format($expense['approved_amount'] ?? 0, 2) }}</td>
                                    <td>{{ $expense['status'] }}</td>
                                    <td>
                                        @foreach ($expense['images'] as $image)
                                            <a href="{{ asset('storage/' . $image->path) }}" target="_blank">
                                                {{ basename($image->path) }}<br>
                                            </a>
                                        @endforeach
                                    </td>
                                    <td>
                                        <button class="view-more-btn">View More</button>
                                        @if ($expense['status'] === 'Pending')
                                            <a href="{{ route('expenses.create', ['resubmit_id' => $expense['id']]) }}"
                                                class="resubmit-btn" style="display:inline-block; margin-top: 5px;">
                                                Resubmit
                                            </a>
                                        @endif

                                    </td>

                                </tr>
                                <tr class="view-more-row" style="display: none;">
                                    <td colspan="6">
                                        @if (is_array($expense['meta_data']))
                                            <strong>Description:</strong>
                                            <ul>
                                                @foreach ($expense['meta_data'] as $key => $value)
                                                    @if ($key !== 'resubmitted_from' && $key !== 'previous_expense')
                                                        <li>
                                                            <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong>
                                                            @if (is_array($value))
                                                                <pre>{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                            @else
                                                                {{ $value }}
                                                            @endif
                                                        </li>
                                                    @endif
                                                @endforeach
                                            </ul>
                                        @endif

                                        <strong>User Remarks:</strong> {{ $expense['remarks'] ?? 'N/A' }}<br>
                                        <strong>Approved By:</strong> {{ $expense['approved_by'] ?? 'N/A' }}<br>
                                        <strong>Approved At:</strong> {{ $expense['approved_at'] ?? 'N/A' }}<br>
                                        <strong>Approver Comment:</strong> {{ $expense['admin_comment'] ?? 'N/A' }}<br>

                                        @if (isset($expense['meta_data']['resubmitted_from']) && isset($expense['meta_data']['previous_expense']))
                                            <hr>
                                            <div style="background-color: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 10px;">
                                                <strong>Previous Expense History (Resubmitted)</strong>
                                                <div style="margin-top: 10px;">
                                                    <table style="width: 100%; border-collapse: collapse;">
                                                       
                                                        <tr>
                                                            <td style="padding: 5px; border: 1px solid #ddd; background-color: #ffffff;"><strong>Date:</strong></td>
                                                            <td style="padding: 5px; border: 1px solid #ddd; background-color: #ffffff;">{{ \Carbon\Carbon::parse($expense['meta_data']['previous_expense']['date'])->format('Y-m-d') }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="padding: 5px; border: 1px solid #ddd; background-color: #ffffff;"><strong>Type:</strong></td>
                                                            <td style="padding: 5px; border: 1px solid #ddd; background-color: #ffffff;">{{ $expense['meta_data']['previous_expense']['type'] ?? 'N/A' }}</td>
                                                        </tr>
                                                        
                                                        <tr>
                                                            <td style="padding: 5px; border: 1px solid #ddd; background-color: #ffffff;"><strong>Amount:</strong></td>
                                                            <td style="padding: 5px; border: 1px solid #ddd; background-color: #ffffff;">₹{{ number_format($expense['meta_data']['previous_expense']['amount'] ?? 0, 2) }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="padding: 5px; border: 1px solid #ddd; background-color: #ffffff;"><strong>Status:</strong></td>
                                                            <td style="padding: 5px; border: 1px solid #ddd; background-color: #ffffff;">{{ $expense['meta_data']['previous_expense']['status'] ?? 'N/A' }}</td>
                                                        </tr>
                                                        <tr>
                                                            <td style="padding: 5px; border: 1px solid #ddd; background-color: #ffffff;"><strong>Remarks:</strong></td>
                                                            <td style="padding: 5px; border: 1px solid #ddd; background-color: #ffffff;">{{ $expense['meta_data']['previous_expense']['remarks'] ?? 'N/A' }}</td>
                                                        </tr>
                                                       
                                                    </table>
                                                </div>

                                                @if (!empty($expense['meta_data']['previous_expense']['meta_data']))
                                                    <div style="margin-top: 10px;">
                                                        <strong>Additional Details:</strong>
                                                        <div style="background-color: #ffffff; padding: 8px; border-radius: 3px; margin-top: 5px;">
                                                            @foreach ($expense['meta_data']['previous_expense']['meta_data'] as $key => $value)
                                                                @if (!is_array($value))
                                                                    <span style="display: inline-block; margin-right: 15px;">
                                                                        <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong> {{ $value }}
                                                                    </span>
                                                                @endif
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif

                                                @if (!empty($expense['meta_data']['previous_expense']['images']))
                                                    <div style="margin-top: 10px;">
                                                        <strong>Attachments:</strong>
                                                        <div style="margin-top: 5px;">
                                                            @foreach ($expense['meta_data']['previous_expense']['images'] as $imgPath)
                                                                <a href="{{ asset('storage/' . $imgPath) }}" target="_blank" 
                                                                   style="display: inline-block; margin-right: 10px; padding: 3px 8px; background-color: #007bff; color: white; text-decoration: none; border-radius: 3px; font-size: 12px;">
                                                                    {{ basename($imgPath) }}
                                                                </a>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif

                                    </td>
                                </tr>

                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
        <div class="header-row">
            <a href="{{ route('expenses.create') }}" class="add-expense-button">Add Expenses</a>
        </div>
    </div>
@endsection

@section('scripts')
    @vite('resources/js/expenses.js')
@endsection