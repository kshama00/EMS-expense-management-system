document.addEventListener("DOMContentLoaded", function () {
    let allowSubmit = false;
    const addRow = document.getElementById("addRow");
    const expenseRows = document.getElementById("expense-rows");
    const form = document.querySelector("form");

    function getCurrentLocation(input) {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(async function (position) {
                try {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;

                    const response = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json`);
                    const data = await response.json();

                    // Use full address from display_name
                    const fullAddress = data.display_name;

                    if (input) input.value = fullAddress;
                } catch (error) {
                    console.error("Geolocation error:", error);
                    if (input) input.placeholder = "Unable to get location";
                }
            }, function (error) {
                if (input) input.placeholder = "Unable to get location";
                console.error("Geolocation error:", error);
            });
        } else {
            if (input) input.placeholder = "Geolocation not supported";
        }
    }

    // Initial location setup
    const locationInput = document.getElementById('location');
    if (locationInput) {
        getCurrentLocation(locationInput);
    }

    function DuplicateCheck(row) {
        const typeSelect = row.querySelector("select[name*='[type]']");
        const amountInput = row.querySelector("input[name*='[amount]']");
        const dateInput = document.getElementById("global-date");
        let alertShown = false;

        function showDuplicateWarning(inputElement) {
            if (alertShown) return;
            alertShown = true;

            Swal.fire({
                icon: 'warning',
                title: 'Duplicate entry in the form!',
                text: 'You have already entered this value.',
                showCancelButton: true,
                confirmButtonText: 'Yes, Continue',
                cancelButtonText: 'Let Me Change',
                reverseButtons: true
            }).then((result) => {
                alertShown = false;

                if (result.isConfirmed) {
                    // User clicked "Yes, Continue" → do nothing
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    // User clicked "Let Me Change" → clear the input
                    if (inputElement) inputElement.value = '';
                }
            });
        }

        function checkDuplicate() {
            // Skip duplicate check on resubmission
            if (window.resubmitData) return;

            const type = typeSelect?.value;
            const amount = parseFloat(amountInput?.value);
            const date = dateInput?.value;

            if (!type || !amount || isNaN(amount) || !date) return;

            let duplicateFound = false;

            // 1️⃣ Check duplicates in the form
            const rows = document.querySelectorAll(".expense-row");
            for (let r of rows) {
                if (r === row) continue;
                const rType = r.querySelector("select[name*='[type]']")?.value;
                const rAmount = parseFloat(r.querySelector("input[name*='[amount]']")?.value);
                if (rType === type && rAmount === amount) {
                    duplicateFound = true;
                    break; // stop further loop
                }
            }

            if (duplicateFound) {
                showDuplicateWarning(amountInput);
                return; // ✅ skip database check if form duplicate exists
            }

            // 2️⃣ Check duplicates in the database
            fetch('/expenses/check-duplicate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ type, amount, date })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.duplicate) {
                        showDuplicateWarning(amountInput);
                    }
                });
        }

        // Trigger when user leaves the field or changes type/date
        if (typeSelect) typeSelect.addEventListener('change', checkDuplicate);
        if (amountInput) amountInput.addEventListener('blur', checkDuplicate);
        if (dateInput) dateInput.addEventListener('change', checkDuplicate);
    }



    function saveFormData() {
        const formData = {
            rowCount: document.querySelectorAll(".expense-row").length,
            rows: []
        };

        document.querySelectorAll(".expense-row").forEach((row, index) => {
            const rowData = { index };
            row.querySelectorAll("input, select, textarea").forEach(input => {
                if (input.name) {
                    const fieldName = input.name.replace(/expenses\[\d+\]\[(.+?)\].*/, '$1');
                    if (fieldName) {
                        if (input.type === "file") {
                            rowData[fieldName] = Array.from(input.files).map(file => file.name);
                        } else {
                            rowData[fieldName] = input.value;
                        }
                    }
                }
            });
            formData.rows.push(rowData);
        });

        localStorage.setItem("expensesData", JSON.stringify(formData));
    }


    function loadFormData() {
        const savedData = localStorage.getItem("expensesData");
        if (!savedData) return;

        try {
            const formData = JSON.parse(savedData);
            const currentRowCount = document.querySelectorAll(".expense-row").length;
            const neededRows = formData.rowCount || formData.rows.length;


            for (let i = currentRowCount; i < neededRows; i++) {
                addNewRow(false);
            }


            formData.rows.forEach((rowData, index) => {
                const row = document.querySelectorAll(".expense-row")[index];
                if (!row) return;

                Object.keys(rowData).forEach(fieldName => {
                    if (fieldName === 'index') return;

                    const input = row.querySelector(`[name*="[${fieldName}]"]`);
                    if (!input || input.type === "file") return;

                    input.value = rowData[fieldName] || "";
                });

                setTimeout(() => handleTypeChange(row), 100);
            });
        } catch (error) {
            console.error("Error loading form data:", error);
            localStorage.removeItem("expensesData");
        }
    }

    function validateMobileSelection(row) {
        const typeSelect = row.querySelector("select[name*='[type]']");
        const amountInput = row.querySelector("input[name*='[amount]']");


        if (typeSelect) {
            typeSelect.addEventListener('change', function () {
                if (this.value === 'Mobile') {

                    if (window.hasMobile) {
                        const isResubmission = window.resubmitData && window.resubmitData.type === 'Mobile';

                        if (!isResubmission) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Mobile Expense Already Exists',
                                text: 'You have already submitted a Mobile expense for this month. Only one Mobile expense is allowed per month.',
                            });
                            this.value = '';
                            if (amountInput) amountInput.value = '';
                            return;
                        }
                    }

                    const otherMobileRows = document.querySelectorAll('.expense-row select[name*="[type]"]');
                    let mobileCount = 0;

                    otherMobileRows.forEach(select => {
                        if (select.value === 'Mobile') {
                            mobileCount++;
                        }
                    });

                    if (mobileCount > 1) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Multiple Mobile Entries',
                            text: 'Only one Mobile expense is allowed per month. Please remove the other Mobile entry first.',
                        });
                        this.value = '';
                        if (amountInput) amountInput.value = '';
                        return;
                    }
                }
                setTimeout(() => {
                    if (row._duplicateChecker) {
                        row._duplicateChecker(row, true);
                    }
                }, 100);
            });
        }
    }

    function handleTypeChange(row) {
        const typeSelect = row.querySelector("select[name*='[type]']");
        const subtypeSelect = row.querySelector("select[name*='[subtype]']");
        const amountInput = row.querySelector("input[name*='[amount]']");

        const lodgingFields = row.querySelectorAll(".lodging");
        const travelFields = row.querySelectorAll(".travel");
        const twoWheelerFields = row.querySelectorAll(".twoWheeler");
        const fourWheelerFields = row.querySelectorAll(".fourWheeler");

        // Inputs for required logic
        const fromLocationInput = row.querySelector("input[name*='[from_location]']");
        const toLocationInput = row.querySelector("input[name*='[to_location]']");
        const startReadingInput = row.querySelector("input[name*='[start_reading]']");
        const endReadingInput = row.querySelector("input[name*='[end_reading]']");
        const checkinInput = row.querySelector("input[name*='[checkin_date]']");
        const checkoutInput = row.querySelector("input[name*='[checkout_date]']");

        const globalDateInput = document.getElementById("global-date");


        function toggleFields() {
            const type = typeSelect?.value;
            const subtype = subtypeSelect?.value;

            // Hide all fields first
            lodgingFields.forEach(el => el.style.display = "none");
            travelFields.forEach(el => el.style.display = "none");
            twoWheelerFields.forEach(el => el.style.display = "none");
            fourWheelerFields.forEach(el => el.style.display = "none");

            // Reset required attributes
            [fromLocationInput, toLocationInput,
                startReadingInput, endReadingInput, checkinInput, checkoutInput].forEach(input => {
                    if (input) input.removeAttribute('required');
                });

            if (amountInput) {
                const currentValue = amountInput.value;
                const wasMobile = amountInput.readOnly && currentValue === "250";

                if (type === "Mobile") {
                    amountInput.value = "250";
                    amountInput.readOnly = true;
                } else {
                    amountInput.readOnly = false;
                    if (wasMobile) {
                        amountInput.value = "";
                    }
                }
            }

            // Show fields and set required based on type/subtype
            if (type === "Lodging") {
                lodgingFields.forEach(el => el.style.display = "block");
                if (checkinInput) checkinInput.setAttribute('required', 'true');
                if (checkoutInput) checkoutInput.setAttribute('required', 'true');

                if (checkinInput) {
                    checkinInput.value = globalDateInput.value;
                    checkinInput.setAttribute("readonly", true);
                }

                if (checkoutInput) {
                    checkoutInput.min = globalDateInput.value;
                }
            } else if (type === "Travel") {
                travelFields.forEach(el => el.style.display = "block");
                if (subtypeSelect) subtypeSelect.setAttribute('required', 'true');

                if (subtype === "2_wheeler") {
                    twoWheelerFields.forEach(el => el.style.display = "block");
                    if (startReadingInput) startReadingInput.setAttribute('required', 'true');
                    if (endReadingInput) endReadingInput.setAttribute('required', 'true');
                } else if (["bus", "car", "train"].includes(subtype)) {
                    fourWheelerFields.forEach(el => el.style.display = "block");
                    if (fromLocationInput) fromLocationInput.setAttribute('required', 'true');
                    if (toLocationInput) toLocationInput.setAttribute('required', 'true');
                }
            }
        }

        if (typeSelect) typeSelect.addEventListener("change", toggleFields);
        if (subtypeSelect) subtypeSelect.addEventListener("change", toggleFields);

        toggleFields();

        validateMobileSelection(row);
        DuplicateCheck(row);
    }

    function bindValidation(row) {
        const startInput = row.querySelector("input[name*='[start_reading]']");
        const endInput = row.querySelector("input[name*='[end_reading]']");
        const amountInput = row.querySelector("input[name*='[amount]']");
        const attachmentInput = row.querySelector("input[name*='[attachments]']");
        const checkinInput = row.querySelector("input[name*='[checkin_date]']");
        const checkoutInput = row.querySelector("input[name*='[checkout_date]']");
        function getErrorSpan(input, className) {
            if (!input) return null;

            let span = input.parentElement.querySelector("." + className);

            if (!span) {
                span = document.createElement("small");
                span.className = className;
                span.style.color = "#eb2c2cff";
                span.style.display = "block";
                span.style.margin = "0";
                span.style.padding = "0";
                span.style.fontSize = "12px";
                span.style.lineHeight = "1";
                input.parentElement.appendChild(span);
            }

            return span;
        }


        const startError = getErrorSpan(startInput, "start-reading-error");
        const endError = getErrorSpan(endInput, "end-reading-error");
        const amountError = getErrorSpan(amountInput, "amount-error");
        const attachmentError = getErrorSpan(attachmentInput, "attachment-error");
        const checkinError = getErrorSpan(checkinInput, "checkin-error");
        const checkoutError = getErrorSpan(checkoutInput, "checkout-error");

        function validateFields() {
            const start = parseFloat(startInput?.value);
            const end = parseFloat(endInput?.value);
            const amount = parseFloat(amountInput?.value);
            const checkin = checkinInput?.value;
            const checkout = checkoutInput?.value;
            const typeSelect = row.querySelector("select[name*='[type]']");

            let hasError = false;

            // Reset custom validation errors
            [startError, endError, amountError, attachmentError, checkinError, checkoutError].forEach(span => {
                if (span) {
                    span.style.display = "none";
                    span.textContent = "";
                    span.parentElement.querySelector('input')?.classList.remove('error');
                }
            });

            // No negative numbers
            if (!isNaN(start) && start < 0) {
                startError.textContent = "Start reading cannot be negative.";
                startError.style.display = "block";
                startInput?.classList.add('error');
                hasError = true;
            }

            if (!isNaN(end) && end < 0) {
                endError.textContent = "End reading cannot be negative.";
                endError.style.display = "block";
                endInput?.classList.add('error');
                hasError = true;
            }

            // End >= Start validation
            if (!isNaN(start) && !isNaN(end) && end <= start) {
                endError.textContent = "End reading cannot be less than or equal to start reading.";
                endError.style.display = "block";
                endInput?.classList.add('error');
                hasError = true;
            }

            // Amount calculation validation for 2-wheeler
            if (!isNaN(start) && !isNaN(end) && !isNaN(amount)) {
                const subtypeSelect = row.querySelector("select[name*='[subtype]']");
                if (typeSelect?.value === "Travel" && subtypeSelect?.value === "2_wheeler") {
                    const expectedAmount = (end - start) * 3;
                    if (amount !== expectedAmount) {
                        amountError.textContent = `Expected calculated amount is ₹${expectedAmount}`;
                        amountError.style.display = "block";
                        amountInput?.classList.add('error');
                        hasError = true;
                    }
                }
            }

            const checkinDate = new Date(checkin);
            const checkoutDate = new Date(checkout);
            const diffTime = checkoutDate - checkinDate;
            const diffDays = diffTime / (1000 * 60 * 60 * 24);

            if (checkin && checkout && diffDays > 1) {
                checkoutError.textContent = "Check-out date can be same or one greater than checkin date";
                checkoutError.style.display = "block";
                checkoutInput?.classList.add('error');
                hasError = true;
            }

            // Amount must be positive
            if (!isNaN(amount) && amount <= 0) {
                amountError.textContent = "Amount must be greater than 0.";
                amountError.style.display = "block";
                amountInput?.classList.add('error');
                hasError = true;
            }

            if (!isNaN(amount) && (amount) > 99999) {
                amountError.textContent = "Amount must be less than 99999.";
                amountError.style.display = "block";
                amountInput?.classList.add('error');
                hasError = true;
            }

            return !hasError;
        }

        // Add event listeners for custom validation
        [startInput, endInput, amountInput, attachmentInput, checkinInput, checkoutInput].forEach(input => {
            if (input) {
                input.addEventListener("input", validateFields);
                input.addEventListener("change", validateFields);
                input.addEventListener("blur", validateFields);
            }
        });

        return validateFields;
    }

    // Replace your existing addNewRow function with this updated version:

    function addNewRow(shouldSave = true) {
        const expenseRows = document.getElementById("expense-rows");
        const firstRow = document.querySelector(".expense-row");
        if (!firstRow) return null;

        const newRow = firstRow.cloneNode(true);
        const currentIndex = document.querySelectorAll(".expense-row").length;

        // Clear and update all inputs
        newRow.querySelectorAll("input, select, textarea").forEach((input) => {
            const name = input.getAttribute("name");
            if (name) {
                input.setAttribute("name", name.replace(/\[\d+\]/, `[${currentIndex}]`));
            }

            if (input.type === "file") {
                input.value = "";
                input.id = `file_input_${currentIndex}`;
            } else if (!input.name.includes('[date]') && !input.name.includes('[location]')) {
                input.value = "";
            }

            // Auto-fill date and location
            if (input.name.includes('[date]')) {
                const globalDate = document.getElementById("global-date")?.value;
                input.value = globalDate || new Date().toISOString().split("T")[0];
                const formGroup = input.closest(".form");
                if (formGroup) formGroup.style.display = "none";
            }

            if (input.name.includes('[location]')) {
                getCurrentLocation(input);
            }

            // Reset readonly and styling
            input.readOnly = false;
            input.style.backgroundColor = '';
            input.removeAttribute('min');
            input.removeAttribute('max');
        });

        // Setup file input list
        const fileNameList = newRow.querySelector(".file-names");
        if (fileNameList) {
            fileNameList.id = `selected_files_${currentIndex}`;
            fileNameList.innerHTML = "";
        }

        const fileInput = newRow.querySelector("input[type='file']");
        if (fileInput) {
            bindFileInput(fileInput, currentIndex);
        }

        // Show delete button and assign proper click handler
        const deleteBtn = newRow.querySelector(".remove-row-btn");
        if (deleteBtn) {
            deleteBtn.style.display = currentIndex > 0 ? "flex" : "none";
            deleteBtn.onclick = () => newRow.remove();
        }

        expenseRows.appendChild(newRow);
        handleTypeChange(newRow);
        bindValidation(newRow);

        if (shouldSave) {
            saveFormData();
        }

        return newRow;
    }



    // Enhanced file input handling
    document.querySelectorAll(".file-input").forEach((input, index) => {
        bindFileInput(input, index);
    });

    function bindFileInput(input, index) {
        let selectedFiles = [];

        input.addEventListener("change", function () {
            const newFiles = Array.from(this.files);

            // Check file limit
            if (selectedFiles.length + newFiles.length > 2) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Limit Exceeded',
                    text: 'You can only upload a maximum of 2 files.',
                });
                return;
            }

            selectedFiles = selectedFiles.concat(newFiles);
            updateFileDisplay(index, selectedFiles);
            updateFileInput(input, selectedFiles);
        });

        function updateFileDisplay(index, files) {
            const displayList = document.getElementById(`selected_files_${index}`);

            if (displayList) {
                displayList.innerHTML = "";

                files.forEach((file, fileIndex) => {
                    const li = document.createElement("li");
                    li.style.display = "flex";
                    li.style.alignItems = "center";
                    li.style.gap = "10px";
                    li.style.marginBottom = "5px";

                    const fileLink = document.createElement("a");
                    fileLink.textContent = file.name;
                    fileLink.href = "#";
                    fileLink.style.cursor = "pointer";
                    fileLink.style.color = "#007bff";
                    fileLink.style.textDecoration = "underline";
                    fileLink.style.flex = "1";

                    fileLink.addEventListener("click", function (e) {
                        e.preventDefault();
                        const fileURL = URL.createObjectURL(file);
                        window.open(fileURL, '_blank');
                        setTimeout(() => URL.revokeObjectURL(fileURL), 5000);
                    });

                    const removeBtn = document.createElement("button");
                    removeBtn.type = "button";
                    removeBtn.innerText = "×";
                    removeBtn.setAttribute("aria-label", "Remove file");
                    removeBtn.addEventListener("click", () => removeFile(index, fileIndex));

                    li.appendChild(fileLink);
                    li.appendChild(removeBtn);
                    displayList.appendChild(li);
                });
            }
        }

        function removeFile(rowIndex, fileIndex) {
            selectedFiles.splice(fileIndex, 1);
            updateFileDisplay(rowIndex, selectedFiles);
            updateFileInput(input, selectedFiles);
        }

        function updateFileInput(input, files) {
            const dataTransfer = new DataTransfer();
            files.forEach(file => dataTransfer.items.add(file));
            input.files = dataTransfer.files;
        }
    }

    // Apply to existing rows
    document.querySelectorAll(".expense-row").forEach(row => {
        handleTypeChange(row);
        bindValidation(row);
    });

    // Add row event listener
    if (addRow) {
        addRow.addEventListener("click", function (e) {
            e.preventDefault();
            addNewRow(true);
        });
    }

    // Auto-save functionality
    let saveTimeout;
    function debouncedSave() {
        clearTimeout(saveTimeout);
        saveTimeout = setTimeout(saveFormData, 500);
    }

    document.addEventListener("input", function (e) {
        if (e.target.closest("form") && e.target.closest(".expense-row")) {
            debouncedSave();
        }
    });

    document.addEventListener("change", function (e) {
        if (e.target.closest("form") && e.target.closest(".expense-row")) {
            debouncedSave();
        }
    });

    // Enhanced form submission with immediate resubmit redirect
    if (form) {
        form.addEventListener("submit", async function (e) {
            const resubmitId = window?.resubmitData?.id;
            const isResubmission = !!resubmitId; // Check if this is a resubmission

            if (!allowSubmit) {
                e.preventDefault();
            } else {
                return;
            }

            const validateAndSubmitForm = async () => {
                let total = 0;
                let valid = true;

                // Run custom validation on all rows
                document.querySelectorAll(".expense-row").forEach((row, index) => {
                    const validateRow = bindValidation(row);
                    const isRowValid = validateRow();

                    if (!isRowValid) {
                        valid = false;
                    }

                    const type = row.querySelector("select[name*='[type]']")?.value;
                    const subtype = row.querySelector("select[name*='[subtype]']")?.value;
                    const amountInput = row.querySelector("input[name*='[amount]']");
                    const startInput = row.querySelector("input[name*='[start_reading]']");
                    const endInput = row.querySelector("input[name*='[end_reading]']");
                    let hiddenDate = row.querySelector("input[name*='[date]']");

                    const amount = parseFloat(amountInput?.value);
                    const start = parseFloat(startInput?.value);
                    const end = parseFloat(endInput?.value);

                    if (!isNaN(amount)) total += amount;

                    // Additional 2-wheeler validation
                    if (type === "Travel" && subtype === "2_wheeler") {
                        if (!isNaN(start) && !isNaN(end)) {
                            const expected = (end - start) * 3;
                            if (amount !== expected) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Amount Mismatch',
                                    text: `Expense ${index + 1}: Given Amount ₹${amount} does not match expected ₹${expected}`,
                                    allowOutsideClick: false,
                                });
                                valid = false;
                            }
                        }
                    }

                    // Add hidden date field
                    if (!hiddenDate) {
                        hiddenDate = document.createElement("input");
                        hiddenDate.type = "hidden";
                        hiddenDate.name = `expenses[${index}][date]`;
                        row.appendChild(hiddenDate);
                    }
                    hiddenDate.value = document.getElementById("global-date").value;
                });

                // Check HTML5 validation first
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                // If custom validation failed
                if (!valid) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Errors',
                        text: 'Please fix all validation errors before submitting the form.',
                        allowOutsideClick: false,
                    });

                    const firstError = document.querySelector('.error');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstError.focus();
                    }
                    return;
                }

                // Replace the proceedToSubmit function and the resubmission logic in your existing code

                const proceedToSubmit = () => {
                    localStorage.removeItem("expensesData");

                    if (isResubmission) {
                        // Show confirmation dialog for resubmission
                        Swal.fire({
                            icon: 'question',
                            title: 'Confirm Resubmission',
                            html: `Your total is ₹${total.toFixed(2)}.<br><br>Do you want to resubmit this expense?`,
                            showCancelButton: true,
                            confirmButtonText: 'Yes, Resubmit',
                            cancelButtonText: 'Cancel',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // User confirmed resubmission
                                sessionStorage.setItem('resubmit_success', JSON.stringify({
                                    message: 'Expense resubmitted successfully!',
                                    timestamp: Date.now()
                                }));

                                const Toast = Swal.mixin({
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 2000,
                                    timerProgressBar: true,
                                });

                                Toast.fire({
                                    icon: 'success',
                                    title: 'Expense Resubmitted Successfully!'
                                });

                                const formData = new FormData(form);

                                fetch(form.action, {
                                    method: 'POST',
                                    body: formData,
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                    }
                                })
                                    .then(response => {
                                        if (response.ok) {
                                            window.location.href = '/expenses/view';
                                        } else {
                                            throw new Error('Submission failed');
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Resubmission error:', error);
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Submission Error',
                                            text: 'There was an error resubmitting your expense. Please try again.'
                                        });
                                    });
                            }
                            // If user cancels, do nothing - form submission is cancelled
                        });

                    } else {
                        // Normal submission
                        allowSubmit = true;
                        form.requestSubmit();
                    }
                };

                // Also update the resubmission logic to handle the limit check with confirmation
                if (isResubmission) {
                    if (total > remainingLimit) {
                        let hasRemark = false;
                        const allRemarks = document.querySelectorAll("input[name*='[remarks]']");
                        allRemarks.forEach(input => {
                            if (input.value.trim() !== "") hasRemark = true;
                        });

                        if (!hasRemark) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Remark Required',
                                text: `Total expense ₹${total.toFixed(2)} exceeds ₹${remainingLimit}. Please add a remark before resubmitting.`,
                                allowOutsideClick: false,
                            });
                            allRemarks[0]?.focus();
                            return;
                        }
                    }
                    // Call proceedToSubmit which will now show confirmation dialog
                    proceedToSubmit();
                    return;
                }

                console.log('used amount', total);

                if (isResubmission) {
                    if (total > remainingLimit) {
                        let hasRemark = false;
                        const allRemarks = document.querySelectorAll("input[name*='[remarks]']");
                        allRemarks.forEach(input => {
                            if (input.value.trim() !== "") hasRemark = true;
                        });

                        if (!hasRemark) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Remark Required',
                                text: `Total expense ₹${total.toFixed(2)} exceeds ₹${remainingLimit}. Please add a remark before resubmitting.`,
                                allowOutsideClick: false,
                            });
                            allRemarks[0]?.focus();
                            return;
                        }
                    }
                    proceedToSubmit();
                    return;
                }

                if (total > remainingLimit) {
                    let hasRemark = false;
                    const allRemarks = document.querySelectorAll("input[name*='[remarks]']");
                    allRemarks.forEach(input => {
                        if (input.value.trim() !== "") hasRemark = true;
                    });

                    if (!hasRemark) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Exceed Limit',
                            text: `Total expense ₹${total.toFixed(2)} exceeds ₹${remainingLimit}. Please add a remark.`,
                            allowOutsideClick: false,
                        });
                        allRemarks[0]?.focus();
                        return;
                    }

                    Swal.fire({
                        icon: 'question',
                        title: 'Confirm Submission',
                        html: `Your total is ₹${total.toFixed(2)}, which exceeds the remaining limit ₹${remainingLimit}.<br><br>Do you want to claim?`,
                        showCancelButton: true,
                        confirmButtonText: 'Yes, Submit',
                        cancelButtonText: 'Cancel',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                    }).then((result) => {
                        if (result.isConfirmed) proceedToSubmit();
                    });
                } else {
                    Swal.fire({
                        icon: 'question',
                        title: 'Confirm Submission',
                        html: `Your total is ₹${total.toFixed(2)}.<br><br>Do you want to claim?`,
                        showCancelButton: true,
                        confirmButtonText: 'Yes, Submit',
                        cancelButtonText: 'Cancel',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const Toast = Swal.mixin({
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000,
                                timerProgressBar: true,
                            });

                            Toast.fire({
                                icon: 'success',
                                title: 'Expenses Submitted Successfully'
                            });

                            setTimeout(() => {
                                proceedToSubmit();
                            }, 2000);
                        }
                    });
                }
            };

            if (resubmitId) {
                fetch(`/expenses/check-status/${resubmitId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'ok') {
                            validateAndSubmitForm();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Resubmission Blocked',
                                text: data.message || 'Expense is no longer pending.',
                            }).then(() => {
                                window.location.href = '/expenses/view';
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Status check error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Unable to check expense status.',
                        });
                    });
            } else {
                validateAndSubmitForm();
            }
        });
    }

    console.log('Remaining limit:', remainingLimit);

    // Handle resubmit data FIRST
    if (window.resubmitData) {
        console.log(window.resubmitData);
        const firstRow = document.querySelector(".expense-row");

        if (firstRow) {
            const typeSelect = firstRow.querySelector("select[name*='[type]']");
            const subtypeSelect = firstRow.querySelector("select[name*='[subtype]']");

            if (typeSelect && resubmitData.type) {
                typeSelect.value = resubmitData.type;
            }

            if (subtypeSelect && resubmitData.subtype) {
                subtypeSelect.value = resubmitData.subtype;
            }

            handleTypeChange(firstRow);

            typeSelect?.dispatchEvent(new Event("change"));
            subtypeSelect?.dispatchEvent(new Event("change"));

            const setValue = (name, value) => {
                const input = firstRow.querySelector(`[name="expenses[0][${name}]"]`);
                if (input && value !== undefined && value !== null) {
                    input.value = value;
                }
            };

            setValue("start_reading", resubmitData.start_reading);
            setValue("end_reading", resubmitData.end_reading);
            setValue("from_location", resubmitData.from_location);
            setValue("to_location", resubmitData.to_location);
            setValue("checkin_date", resubmitData.checkin_date);
            setValue("checkout_date", resubmitData.checkout_date);
            setValue("amount", resubmitData.amount);
            setValue("location", resubmitData.location);
            setValue("remarks", resubmitData.remarks);
        }
    } else {
        loadFormData();
    }
});