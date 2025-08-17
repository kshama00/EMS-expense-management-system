document.addEventListener('DOMContentLoaded', function () {

    // Toggle details for grouped cards
    document.querySelectorAll('.toggle-details-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const details = btn.closest('.expense-card').querySelector('.expense-details');
            if (details) {
                details.style.display = details.style.display === 'none' ? 'block' : 'none';
                btn.textContent = details.style.display === 'none' ? 'View' : 'Hide';
            }
        });
    });

    // Toggle date's "view more"
    document.querySelectorAll('.view-more-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const content = btn.closest('.date-expense-card').querySelector('.view-more-content');
            if (content) {
                content.style.display = content.style.display === 'none' ? 'block' : 'none';
                btn.textContent = content.style.display === 'none' ? 'View' : 'Hide';
            }
        });
    });

    // Table row expand
    document.querySelectorAll('.expense-view-more').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const currentRow = btn.closest('tr');
            const nextRow = currentRow.nextElementSibling;
            if (nextRow && nextRow.classList.contains('view-more-row')) {
                const isVisible = nextRow.style.display === 'table-row';
                nextRow.style.display = isVisible ? 'none' : 'table-row';
                btn.textContent = isVisible ? 'View More' : 'Hide';
            }
        });
    });

    // Group checkbox → toggle all expense checkboxes inside it
    document.querySelectorAll('.date-group-checkbox').forEach(function (groupCheckbox) {
        groupCheckbox.addEventListener('change', function () {
            const card = groupCheckbox.closest('.date-expense-card');
            const expenseCheckboxes = card.querySelectorAll('.expense-type-checkbox');
            expenseCheckboxes.forEach(function (checkbox) {
                checkbox.checked = groupCheckbox.checked;
            });
        });
    });

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute("content");

    // Function to check expense status before taking action
    async function checkExpenseStatus(expenseId) {
        try {
            const response = await fetch(`/admin/expenses/check-status/${expenseId}`, {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });
            const data = await response.json();

            // Debug logging
            console.log(`Status check for expense ${expenseId}:`, data);

            return data;
        } catch (error) {
            console.error('Error checking expense status:', error);
            return { status: 'error', message: 'Failed to check expense status' };
        }
    }

    // Function to update expense status
    async function updateExpenseStatus(expenses) {
        try {
            const response = await fetch('/admin/expenses/bulk-update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ expenses: expenses })
            });
            return await response.json();
        } catch (error) {
            console.error('Error updating expenses:', error);
            return { success: false, message: 'Failed to update expenses' };
        }
    }

    // Function to collect expense data from UI
    function getExpenseDataFromRow(checkbox) {
        const row = checkbox.closest('tr');
        const expenseId = checkbox.getAttribute('data-expense-id');
        const submittedAmountText = row.querySelector('.submitted-amount').textContent;
        const submittedAmount = parseFloat(submittedAmountText.replace('₹', '').replace(/,/g, ''));

        return {
            id: expenseId,
            submittedAmount: submittedAmount
        };
    }

    // Function to handle different action types
    async function processAction(action, selectedExpenses) {
        let updatedExpenses = [];

        for (let expenseData of selectedExpenses) {
            // Check if expense is still pending
            const statusCheck = await checkExpenseStatus(expenseData.id);

            if (statusCheck.status === 'blocked') {
                Swal.fire({
                    icon: "warning",
                    title: "Action Blocked",
                    text: statusCheck.message
                });
                continue; // Skip this expense
            }



            let expenseUpdate = {
                id: expenseData.id,
                status: action
            };

            // Handle different actions
            switch (action) {
                case 'approve':
                    expenseUpdate.approved_amount = expenseData.submittedAmount;
                    break;

                case 'reject':
                    expenseUpdate.approved_amount = 0;
                    break;

                case 'pending':
                    expenseUpdate.approved_amount = 0;
                    break;

                case 'partially approve':
                    // For partial approval, we'll handle this separately
                    return await handlePartialApproval(selectedExpenses);
            }

            updatedExpenses.push(expenseUpdate);
        }

        if (updatedExpenses.length === 0) {
            return;
        }

        // Confirm and update
        const result = await Swal.fire({
            title: `Confirm ${action.toUpperCase()}`,
            text: `Do you want to proceed`,
            icon: "question",
            showCancelButton: true,
            confirmButtonText: "Yes, proceed",
            cancelButtonText: "Cancel"
        });

        if (result.isConfirmed) {
            const updateResult = await updateExpenseStatus(updatedExpenses);

            if (updateResult.success || updateResult.message === 'Expenses updated successfully') {
                Swal.fire({
                    icon: "success",
                    title: "Updated!",
                    text: "Expenses updated successfully."
                });

                // Update UI
                updateExpenseRowsInUI(updatedExpenses);
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: updateResult.message || "Something went wrong."
                });
            }
        }
    }

    // Function to handle partial approval with custom amount
    // Function to handle partial approval with custom amount
    async function handlePartialApproval(selectedExpenses) {
        // Enforce only one expense at a time
        if (selectedExpenses.length !== 1) {
            Swal.fire({
                icon: "warning",
                title: "Invalid Selection",
                text: "You can only partially approve one expense at a time."
            });
            return;
        }

        const expense = selectedExpenses[0];

        const { value: formValues } = await Swal.fire({
            title: 'Partial Approval',
            html: `
            <div style="text-align: left;">
                <label style="display: block; margin-bottom: 5px;">
                    Submitted Amount: ₹${expense.submittedAmount}
                </label>
                <input type="number" id="approved_amount_${expense.id}" 
                       placeholder="Enter approved amount" 
                       max="${expense.submittedAmount}" 
                       min="0" step="0.01" 
                       class="swal2-input" style="margin: 0;">

                <div style="margin-top: 15px;">
                    <label style="display: block; margin-bottom: 5px;">Admin Comment:</label>
                    <textarea id="admin_comment" placeholder="Enter comment (optional)" 
                              class="swal2-textarea"></textarea>
                </div>
            </div>
        `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'Update Expense',
            cancelButtonText: 'Cancel',
            preConfirm: () => {
                const approvedAmount = parseFloat(document.getElementById(`approved_amount_${expense.id}`).value);
                const comment = document.getElementById('admin_comment').value;

                if (isNaN(approvedAmount) || approvedAmount < 0) {
                    Swal.showValidationMessage('Please enter a valid approved amount');
                    return false;
                }

                if (approvedAmount > expense.submittedAmount) {
                    Swal.showValidationMessage('Approved amount cannot exceed submitted amount');
                    return false;
                }

                return [{
                    id: expense.id,
                    approved_amount: approvedAmount,
                    status: 'partially_approved',
                    admin_comment: comment
                }];
            }
        });

        if (formValues) {
            // Check latest status before update
            const statusCheck = await checkExpenseStatus(expense.id);

            if (statusCheck.status === 'blocked') {
                Swal.fire({
                    icon: "warning",
                    title: "Action Blocked",
                    text: statusCheck.message
                });
                return;
            }

            const updateResult = await updateExpenseStatus(formValues);

            if (updateResult.success || updateResult.message === 'Expenses updated successfully') {
                Swal.fire({
                    icon: "success",
                    title: "Updated!",
                    text: "Expense partially approved successfully."
                });

                updateExpenseRowsInUI(formValues);
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: updateResult.message || "Something went wrong."
                });
            }
        }
    }



    // Function to update UI after successful update
    function updateExpenseRowsInUI(updatedExpenses) {
        updatedExpenses.forEach(expense => {
            const checkbox = document.querySelector(`input[data-expense-id="${expense.id}"]`);
            if (checkbox) {
                const row = checkbox.closest('tr');

                // Update status
                const statusCell = row.querySelector('.expense-status');
                if (statusCell) {
                    let statusText = expense.status;

                    // Map actions to past tense labels
                    switch (statusText) {
                        case 'approve':
                            statusText = 'Approved';
                            break;
                        case 'reject':
                            statusText = 'Rejected';
                            break;
                        case 'pending':
                            statusText = 'Pending';
                            break;
                        case 'partially approve':
                        case 'partially_approved':
                            statusText = 'Partially Approved';
                            break;
                    }

                    statusCell.textContent = statusText;
                }

                // Update approved amount
                const approvedAmountCell = row.querySelector('.approved-amount');
                if (approvedAmountCell && expense.approved_amount !== undefined) {
                    approvedAmountCell.textContent = `₹${expense.approved_amount.toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    })}`;
                }

                // Uncheck the checkbox
                checkbox.checked = false;
            }
        });
    }


    // Handle action dropdown changes
    document.querySelectorAll('.action-select-global').forEach(function (dropdown) {
        dropdown.addEventListener('change', async function () {
            const selectedAction = this.value;

            if (!selectedAction) return;

            // Find the context (card, date group, or global)
            const expenseCard = this.closest('.expense-card');
            const dateCard = this.closest('.date-expense-card');

            let selectedCheckboxes = [];

            if (dateCard) {
                // Date-level action: get all checkboxes in this date card
                selectedCheckboxes = dateCard.querySelectorAll('.expense-type-checkbox:checked');
            } else if (expenseCard) {
                // Card-level action: get all checkboxes in this expense card
                selectedCheckboxes = expenseCard.querySelectorAll('.expense-type-checkbox:checked');
            } else {
                // Global action: get all selected checkboxes
                selectedCheckboxes = document.querySelectorAll('.expense-type-checkbox:checked');
            }

            if (selectedCheckboxes.length === 0) {
                Swal.fire({
                    icon: "warning",
                    title: "No expenses selected",
                    text: "Please select at least one expense before performing an action."
                });
                this.value = ""; // Reset dropdown
                return;
            }

            // Collect expense data
            let selectedExpenses = [];
            selectedCheckboxes.forEach(checkbox => {
                const expenseData = getExpenseDataFromRow(checkbox);
                if (expenseData.id) {
                    selectedExpenses.push(expenseData);
                }
            });

            // Process the action
            await processAction(selectedAction, selectedExpenses);

            // Reset dropdown
            this.value = "";
        });
    });

    // Optional: Handle group-level checkbox toggle
    document.querySelectorAll('.group-checkbox').forEach(groupCheckbox => {
        groupCheckbox.addEventListener("change", function () {
            const groupId = this.getAttribute('data-group-id');
            const childCheckboxes = document.querySelectorAll(`.expense-checkbox[data-group-id="${groupId}"]`);
            childCheckboxes.forEach(cb => cb.checked = this.checked);
        });
    });
});