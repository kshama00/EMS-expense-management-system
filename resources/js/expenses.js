document.addEventListener('DOMContentLoaded', function () {
    /* -------------------------------
       Toggle expense details button
    --------------------------------*/
    document.querySelectorAll('.toggle-details-btn').forEach(function (btn) {
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

    /* -------------------------------
       Table "View More" rows
    --------------------------------*/
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

    /* -------------------------------
       Expense card status summary
    --------------------------------*/
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

    function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    /* -------------------------------
       History modal functions
    --------------------------------*/
    function openHistoryModal(historyData) {
        const modal = document.getElementById('historyModal');
        const content = document.getElementById('historyContent');
        content.innerHTML = generateHistoryHTML(historyData);
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeHistoryModal() {
        const modal = document.getElementById('historyModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    // Expose globally (for inline onclick)
    window.closeHistoryModal = closeHistoryModal;

    // Close on outside click
    window.addEventListener('click', function (event) {
        const modal = document.getElementById('historyModal');
        if (event.target === modal) {
            closeHistoryModal();
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeHistoryModal();
        }
    });

    // Close button
    const closeBtn = document.querySelector('.history-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', closeHistoryModal);
    }

    /* -------------------------------
       History modal button triggers
    --------------------------------*/
    document.querySelectorAll('.history-view-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            let historyData;
            try {
                historyData = JSON.parse(btn.getAttribute('data-history'));
            } catch (err) {
                console.error('Invalid history JSON', err);
                historyData = [];
            }
            openHistoryModal(historyData);
        });
    });

    /* -------------------------------
       Generate modal content
    --------------------------------*/
    function generateHistoryHTML(historyData) {
        if (!historyData || !Array.isArray(historyData) || historyData.length === 0) {
            return '<p>No history available for this expense.</p>';
        }

        return historyData.map((item, index) => {
            const statusClass = `status-${item.status.toLowerCase().replace(/\s+/g, '-')}`;

            return `
        <div class="history-item">
            <div class="history-item-header">
                <strong>Version ${item.version || index + 1}</strong>
                <span class="status-badge ${statusClass}">${item.status}</span>
            </div>
            <div class="history-item-body">
                <div class="history-detail-row"><strong>Date:</strong> <span>${item.date}</span></div>
                <div class="history-detail-row"><strong>Type:</strong> <span>${item.type}</span></div>
                <div class="history-detail-row"><strong>Location:</strong> <span>${item.location}</span></div>
                <div class="history-detail-row"><strong>Submitted Amount:</strong> <span>₹${parseFloat(item.amount).toFixed(2)}</span></div>
                <div class="history-detail-row"><strong>User Remarks:</strong> <span>${item.remarks || 'N/A'}</span></div>
                ${generateMetaDataHTML(item.meta_data)}
                ${generateImagesHTML(item.images)}
            </div>
        </div>
        `;
        }).join('');
    }


    function generateMetaDataHTML(metaData) {
        if (!metaData || typeof metaData !== 'object' || Object.keys(metaData).length === 0) {
            return '';
        }
        let html = '<div class="history-detail-row"><strong>Additional Details:</strong><span></span></div>';
        for (const [key, value] of Object.entries(metaData)) {
            if (key === 'history') continue;
            const displayKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            const displayValue = typeof value === 'object' ? JSON.stringify(value) : value;
            html += `
                <div class="history-detail-row">
                    <strong>${displayKey}:</strong>
                    <span>${displayValue}</span>
                </div>
            `;
        }
        return html;
    }

    function generateImagesHTML(images) {
        if (!images || !Array.isArray(images) || images.length === 0) {
            return '';
        }
        const imageLinks = images.map(image => {
            const imagePath = image.path || image;
            const imageName = image.name || imagePath.split('/').pop();
            return `<a href="/storage/${imagePath}" target="_blank">${imageName}</a>`;
        }).join('');
        return `
            <div class="history-detail-row"><strong>Attachments:</strong><span></span></div>
            <div class="history-images">${imageLinks}</div>
        `;
    }
});
