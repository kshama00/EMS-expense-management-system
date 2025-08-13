document.addEventListener("DOMContentLoaded", function () {
    let allowSubmit = false;
    const addRow = document.getElementById("addRow");
    const expenseRows = document.getElementById("expense-rows");
    const form = document.querySelector("form");

    // Save form data to localStorage
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

    // Load form data from localStorage
    function loadFormData() {
        const savedData = localStorage.getItem("expensesData");
        if (!savedData) return;

        try {
            const formData = JSON.parse(savedData);
            const currentRowCount = document.querySelectorAll(".expense-row").length;
            const neededRows = formData.rowCount || formData.rows.length;

            // Create additional rows if needed
            for (let i = currentRowCount; i < neededRows; i++) {
                addNewRow(false);
            }

            // Fill data into rows
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

    // Function to manage ONLY dynamic required attributes
    function updateRequiredFields(row) {
        const typeSelect = row.querySelector("select[name*='[type]']");
        const subtypeSelect = row.querySelector("select[name*='[subtype]']");
        const fromLocationInput = row.querySelector("input[name*='[from_location]']");
        const toLocationInput = row.querySelector("input[name*='[to_location]']");
        const startReadingInput = row.querySelector("input[name*='[start_reading]']");
        const endReadingInput = row.querySelector("input[name*='[end_reading]']");
        const checkinInput = row.querySelector("input[name*='[checkin_date]']");
        const checkoutInput = row.querySelector("input[name*='[checkout_date]']");

        const type = typeSelect?.value;
        const subtype = subtypeSelect?.value;

        // Remove only the DYNAMIC required attributes (not the ones set in Blade)
        [fromLocationInput, toLocationInput,
            startReadingInput, endReadingInput, checkinInput, checkoutInput].forEach(input => {
                if (input) input.removeAttribute('required');
            });

        // Add dynamic required attributes based on type/subtype selection
        if (type === "Travel") {

            if (subtypeSelect) subtypeSelect.setAttribute('required', 'true');

            if (subtype === "2_wheeler") {
                if (startReadingInput) startReadingInput.setAttribute('required', 'true');
                if (endReadingInput) endReadingInput.setAttribute('required', 'true');
            } else if (["Bus", "Car", "Train"].includes(subtype)) {
                if (fromLocationInput) fromLocationInput.setAttribute('required', 'true');
                if (toLocationInput) toLocationInput.setAttribute('required', 'true');
            }
        } else if (type === "Lodging") {
            if (checkinInput) checkinInput.setAttribute('required', 'true');
            if (checkoutInput) checkoutInput.setAttribute('required', 'true');
        }
    }

    // Handle type changes
    function handleTypeChange(row) {
        const typeSelect = row.querySelector("select[name*='[type]']");
        const subtypeSelect = row.querySelector("select[name*='[subtype]']");
        const lodgingFields = row.querySelectorAll(".lodging");
        const travelFields = row.querySelectorAll(".travel");
        const twoWheelerFields = row.querySelectorAll(".twoWheeler");
        const fourWheelerFields = row.querySelectorAll(".fourWheeler");
        const amountInput = row.querySelector("input[name*='[amount]']");

        function toggleFields() {
            const type = typeSelect?.value;
            const subtype = subtypeSelect?.value;

            // Hide all fields first
            lodgingFields.forEach(el => el.style.display = "none");
            travelFields.forEach(el => el.style.display = "none");
            twoWheelerFields.forEach(el => el.style.display = "none");
            fourWheelerFields.forEach(el => el.style.display = "none");

            // Handle Mobile type amount
            if (amountInput) {
                if (type === "Mobile") {
                    amountInput.value = 250;
                    amountInput.readOnly = true;
                } else if (type && amountInput.readOnly) {
                    amountInput.value = "";
                    amountInput.readOnly = false;
                }
            }

            // Show relevant fields
            if (type === "Lodging") {
                lodgingFields.forEach(el => el.style.display = "block");
            } else if (type === "Travel") {
                travelFields.forEach(el => el.style.display = "block");
                if (subtype === "2_wheeler") {
                    twoWheelerFields.forEach(el => el.style.display = "block");
                } else if (["Bus", "Car", "Train"].includes(subtype)) {
                    fourWheelerFields.forEach(el => el.style.display = "block");
                }
            }

            // Update required attributes
            updateRequiredFields(row);
        }

        if (typeSelect) typeSelect.addEventListener("change", toggleFields);
        if (subtypeSelect) subtypeSelect.addEventListener("change", toggleFields);

        toggleFields(); // Run initially
    }

    // Enhanced validation function - only for custom business rules
    function bindValidation(row) {
        const startInput = row.querySelector("input[name*='[start_reading]']");
        const endInput = row.querySelector("input[name*='[end_reading]']");
        const amountInput = row.querySelector("input[name*='[amount]']");
        const attachmentInput = row.querySelector("input[name*='[attachments]']");
        const checkinInput = row.querySelector("input[name*='[checkin_date]']");
        const checkoutInput = row.querySelector("input[name*='[checkout_date]']");

        // Only keep error spans for custom validation rules
        function getErrorSpan(input, className) {
            let span = input?.parentElement.querySelector("." + className);
            if (!span && input) {
                span = document.createElement("small");
                span.className = className;
                span.style.color = "#eb2c2cff";
                span.style.display = "none";
                span.style.fontSize = "12px";
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

            let hasError = false;

            // Reset custom validation errors
            [startError, endError, amountError, attachmentError, checkinError, checkoutError].forEach(span => {
                if (span) {
                    span.style.display = "none";
                    span.textContent = "";
                    span.parentElement.querySelector('input')?.classList.remove('error');
                }
            });

            // Custom validation rules only

            // File limit validation (not covered by HTML5)
            if (attachmentInput && attachmentInput.files.length > 2) {
                attachmentError.textContent = "You can upload a maximum of 2 attachments.";
                attachmentError.style.display = "block";
                attachmentInput.classList.add('error');
                hasError = true;
            }

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
            if (!isNaN(start) && !isNaN(end) && end < start) {
                endError.textContent = "End reading cannot be less than start reading.";
                endError.style.display = "block";
                endInput?.classList.add('error');
                hasError = true;
            }

            // Amount calculation validation for 2-wheeler
            if (!isNaN(start) && !isNaN(end) && !isNaN(amount)) {
                const typeSelect = row.querySelector("select[name*='[type]']");
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

            // Date range validation
            if (checkin && checkout && new Date(checkout) < new Date(checkin)) {
                checkoutError.textContent = "Check-out date cannot be before check-in date.";
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

    // Create new row function
    function addNewRow(shouldSave = true) {
        const firstRow = document.querySelector(".expense-row");
        if (!firstRow) return null;

        const newRow = firstRow.cloneNode(true);
        const currentIndex = document.querySelectorAll(".expense-row").length;

        // Clear and update all inputs
        newRow.querySelectorAll("input, select, textarea").forEach((input) => {
            const name = input.getAttribute("name");
            if (name) {
                input.setAttribute("name", name.replace(/\[0\]/, `[${currentIndex}]`));
            }

            if (input.type === "file") {
                input.value = "";
                input.id = `file_input_${currentIndex}`;
            } else if (!input.name.includes('[date]') && !input.name.includes('[location]')) {
                input.value = "";
            }

            // Auto-fill date and location for new rows
            if (input.name.includes('[date]')) {
                const globalDate = document.getElementById("global-date")?.value;
                input.value = globalDate || new Date().toISOString().split("T")[0];
                const formGroup = input.closest(".form");
                if (formGroup) formGroup.style.display = "none";
            }

            if (input.name.includes('[location]')) {
                getCurrentLocation(input);
            }
        });

        // Setup file input
        const fileNameList = newRow.querySelector(".file-names");
        if (fileNameList) {
            fileNameList.id = `selected_files_${currentIndex}`;
            fileNameList.innerHTML = "";
        }

        const fileInput = newRow.querySelector("input[type='file']");
        if (fileInput) {
            bindFileInput(fileInput, currentIndex);
        }

        // Add delete button only for non-first rows
        const existingRemoveBtn = newRow.querySelector(".remove-row-btn");
        if (existingRemoveBtn) {
            existingRemoveBtn.remove();
        }

        if (currentIndex > 0) {
            let removeBtn = document.createElement("button");
            removeBtn.innerHTML = '<i class="fas fa-trash"></i>';
            removeBtn.type = "button";
            removeBtn.classList.add("remove-row-btn");
            removeBtn.addEventListener("click", function () {
                newRow.remove();
                if (shouldSave) saveFormData();
            });
            newRow.appendChild(removeBtn);
        }

        expenseRows.appendChild(newRow);
        handleTypeChange(newRow);
        bindValidation(newRow);

        if (shouldSave) {
            saveFormData();
        }

        return newRow;
    }

    // Get current location
    function getCurrentLocation(input) {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(async function (position) {
                try {
                    const lat = position.coords.latitude;
                    const lon = position.coords.longitude;
                    const response = await fetch(`https://nominatim.openstreetmap.org/reverse?lat=${lat}&lon=${lon}&format=json`);
                    const data = await response.json();
                    const address = data.address.city || data.address.town || data.address.village || data.display_name;
                    if (input) input.value = address;
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

    // Enhanced form submission
    if (form) {
        form.addEventListener("submit", function (e) {
            const resubmitId = window?.resubmitData?.id;

            if (!allowSubmit) {
                e.preventDefault();
            } else {
                return;
            }

            const validateAndSubmitForm = () => {
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
                    form.reportValidity(); // This will show HTML5 validation messages
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

                const proceedToSubmit = () => {
                    localStorage.removeItem("expensesData");
                    allowSubmit = true;
                    form.requestSubmit();
                };

                console.log('used amount', total);

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