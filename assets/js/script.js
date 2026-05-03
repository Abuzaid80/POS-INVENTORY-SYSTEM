// Sidebar Toggle
document.addEventListener('DOMContentLoaded', function() {
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    const sidebar = document.getElementById('sidebar');
    const content = document.getElementById('content');

    if (sidebarCollapse) {
        sidebarCollapse.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            content.classList.toggle('active');
        });
    }

    // Auto-hide sidebar on mobile
    function checkWidth() {
        if (window.innerWidth <= 768) {
            sidebar.classList.add('active');
            content.classList.add('active');
        } else {
            sidebar.classList.remove('active');
            content.classList.remove('active');
        }
    }

    // Check on load and resize
    checkWidth();
    window.addEventListener('resize', checkWidth);
});

// Form Validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;

    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            isValid = false;
            field.classList.add('is-invalid');
            
            // Add error message
            if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('invalid-feedback')) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback';
                errorDiv.textContent = 'This field is required';
                field.parentNode.insertBefore(errorDiv, field.nextSibling);
            }
        } else {
            field.classList.remove('is-invalid');
            const errorDiv = field.nextElementSibling;
            if (errorDiv && errorDiv.classList.contains('invalid-feedback')) {
                errorDiv.remove();
            }
        }
    });

    return isValid;
}

// Alert Auto-dismiss
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade');
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });
});

// Table Search
function tableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('keyup', function() {
        const filter = input.value.toLowerCase();
        const rows = table.getElementsByTagName('tr');

        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            const cells = row.getElementsByTagName('td');
            let found = false;

            for (let j = 0; j < cells.length; j++) {
                const cell = cells[j];
                if (cell) {
                    const text = cell.textContent || cell.innerText;
                    if (text.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
            }

            row.style.display = found ? '' : 'none';
        }
    });
}

// Number Formatting
function formatNumber(number, decimals = 2) {
    return parseFloat(number).toFixed(decimals).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Currency Formatting
function formatCurrency(amount) {
    return '₱' + formatNumber(amount);
}

// Date Formatting
function formatDate(date) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(date).toLocaleDateString('en-US', options);
}

// Time Formatting
function formatTime(date) {
    const options = { hour: '2-digit', minute: '2-digit', hour12: true };
    return new Date(date).toLocaleTimeString('en-US', options);
}

// Initialize all table searches
document.addEventListener('DOMContentLoaded', function() {
    const searchInputs = document.querySelectorAll('[data-table-search]');
    searchInputs.forEach(input => {
        tableSearch(input.id, input.dataset.tableSearch);
    });
});

// Cart Management
function updateCart() {
    const cart = [];
    const cartTable = document.getElementById('cartTable').getElementsByTagName('tbody')[0];
    const processSaleBtn = document.getElementById('processSale');
    const amountPaidInput = document.getElementById('amount_paid');
    const changeAmountInput = document.getElementById('change_amount');
    const cartInput = document.getElementById('cart');

    cartTable.innerHTML = '';
    let total = 0;
    
    cart.forEach((item, index) => {
        const row = cartTable.insertRow();
        row.innerHTML = `
            <td>${item.name}</td>
            <td>${formatCurrency(item.price)}</td>
            <td>${item.quantity}</td>
            <td>${formatCurrency(item.price * item.quantity)}</td>
            <td>
                <button type="button" class="btn btn-sm btn-danger" onclick="removeFromCart(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        total += item.price * item.quantity;
    });

    cartInput.value = JSON.stringify(cart);
    processSaleBtn.disabled = cart.length === 0;
    
    // Update change amount
    const amountPaid = parseFloat(amountPaidInput.value) || 0;
    const change = amountPaid - total;
    changeAmountInput.value = change >= 0 ? formatCurrency(change) : 'Insufficient';
}

// Add to Cart
function addToCart() {
    const productSelect = document.getElementById('product_id');
    const quantityInput = document.getElementById('quantity');
    const selectedOption = productSelect.options[productSelect.selectedIndex];
    
    if (!selectedOption.value) {
        showAlert('Please select a product', 'danger');
        return;
    }

    const quantity = parseInt(quantityInput.value);
    if (quantity < 1) {
        showAlert('Quantity must be at least 1', 'danger');
        return;
    }

    const stock = parseInt(selectedOption.dataset.stock);
    if (quantity > stock) {
        showAlert(`Only ${stock} items available in stock`, 'danger');
        return;
    }

    const existingItem = cart.find(item => item.id === parseInt(selectedOption.value));
    if (existingItem) {
        if (existingItem.quantity + quantity > stock) {
            showAlert(`Only ${stock} items available in stock`, 'danger');
            return;
        }
        existingItem.quantity += quantity;
    } else {
        cart.push({
            id: parseInt(selectedOption.value),
            name: selectedOption.text,
            price: parseFloat(selectedOption.dataset.price),
            quantity: quantity
        });
    }

    updateCart();
    productSelect.value = '';
    quantityInput.value = '1';
    showAlert('Product added to cart', 'success');
}

// Remove from Cart
function removeFromCart(index) {
    cart.splice(index, 1);
    updateCart();
    showAlert('Product removed from cart', 'success');
}

// Show Alert
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    setTimeout(() => {
        alertDiv.classList.add('fade');
        setTimeout(() => {
            alertDiv.remove();
        }, 500);
    }, 5000);
} 