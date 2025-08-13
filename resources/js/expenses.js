document.addEventListener('DOMContentLoaded', function () {
    const toggleButtons = document.querySelectorAll('.toggle-details-btn');

    toggleButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const details = btn.closest('.expense-card').querySelector('.expense-details');

            if (details.style.display === 'none' || details.style.display === '') {
                details.style.display = 'block';
                btn.textContent = 'Hide';
            } else {
                details.style.display = 'none';
                btn.textContent = 'View';
            }
        });
    });

    document.querySelectorAll('.view-more-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            const currentRow = button.closest('tr');
            const nextRow = currentRow.nextElementSibling;
            if (nextRow && nextRow.classList.contains('view-more-row')) {
                if (nextRow.style.display === 'none') {
                    nextRow.style.display = 'table-row';
                    button.innerHTML = 'View Less';
                } else {
                    nextRow.style.display = 'none';
                    button.innerHTML = 'View More';
                }
            }
        });
    });

    document.querySelectorAll('.expense-card').forEach(function (card) {
        const statusCells = card.querySelectorAll('tbody tr[data-status]');

        const statuses = Array.from(statusCells).map(row => row.dataset.status?.toLowerCase().trim());

        const statusValueElement = card.querySelector('.expense-summary .status-value');

        const uniqueStatuses = [...new Set(statuses)];

        let finalStatus = 'N/A';

        if (uniqueStatuses.length === 1) {
            finalStatus = uniqueStatuses[0];
        } else if (uniqueStatuses.includes('pending')) {
            finalStatus = 'Pending';
        } else {
            finalStatus = 'Partially approved';
        }

        if (statusValueElement) {
            statusValueElement.textContent = capitalize(finalStatus);
        }

    });

    // Capitalize function for display
    function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
});
