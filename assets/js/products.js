document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable with responsive features
    const productsTable = $('#productsTable').DataTable({
        responsive: {
            details: {
                display: $.fn.dataTable.Responsive.display.childRow,
                type: 'column',
                renderer: function (api, rowIdx, columns) {
                    const data = $.map(columns, function (col, i) {
                        return col.hidden ?
                            '<tr data-dt-row="' + col.rowIndex + '" data-dt-column="' + col.columnIndex + '">' +
                            '<td class="fw-bold">' + col.title + ':</td> ' +
                            '<td>' + col.data + '</td>' +
                            '</tr>' :
                            '';
                    }).join('');
 
                    return data ?
                        $('<table class="table table-sm"/>').append(data) :
                        false;
                }
            }
        },
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        buttons: [
            {
                extend: 'collection',
                text: '<i class="fas fa-download"></i> Export',
                className: 'btn-primary',
                buttons: [
                    {
                        extend: 'csv',
                        text: '<i class="fas fa-file-csv"></i> CSV',
                        className: 'btn-secondary'
                    },
                    {
                        extend: 'excel',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn-secondary'
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn-secondary'
                    }
                ]
            }
        ],
        language: {
            search: "",
            searchPlaceholder: "Search products...",
            lengthMenu: "Show _MENU_",
            info: "Showing _START_ to _END_ of _TOTAL_ products",
            paginate: {
                first: '<i class="fas fa-angle-double-left"></i>',
                previous: '<i class="fas fa-angle-left"></i>',
                next: '<i class="fas fa-angle-right"></i>',
                last: '<i class="fas fa-angle-double-right"></i>'
            }
        },
        order: [[1, 'asc']], // Sort by name by default
        columnDefs: [
            {
                targets: [0], // Image column
                orderable: false,
                responsivePriority: 1
            },
            {
                targets: [1], // Name column
                responsivePriority: 1
            },
            {
                targets: [2], // Category column
                responsivePriority: 3
            },
            {
                targets: [3], // Price column
                responsivePriority: 2
            },
            {
                targets: [4], // Stock column
                responsivePriority: 2
            },
            {
                targets: [5], // Status column
                responsivePriority: 3
            },
            {
                targets: [6], // Actions column
                orderable: false,
                responsivePriority: 1,
                className: 'text-end'
            }
        ],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        drawCallback: function(settings) {
            // Reinitialize tooltips after table redraw
            const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(tooltip => {
                new bootstrap.Tooltip(tooltip);
            });
        }
    });

    // Sync search between DataTable and custom search input
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            productsTable.search(this.value).draw();
            filterProductGrid(this.value);
        });
    }

    // Handle category filter
    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function() {
            const selectedCategory = this.value;
            
            // Filter DataTable
            productsTable.column(2).search(selectedCategory ? 
                document.querySelector(`option[value="${selectedCategory}"]`).textContent : 
                ''
            ).draw();

            // Filter product grid
            filterProductGrid();
        });
    }

    // Handle sort filter
    const sortFilter = document.getElementById('sortFilter');
    if (sortFilter) {
        sortFilter.addEventListener('change', function() {
            const [column, direction] = this.value.split('_');
            
            // Sort DataTable
            let columnIndex;
            switch(column) {
                case 'name': columnIndex = 1; break;
                case 'price': columnIndex = 3; break;
                case 'stock': columnIndex = 4; break;
                default: columnIndex = 1;
            }
            productsTable.order([columnIndex, direction]).draw();

            // Sort product grid
            sortProductGrid(column, direction);
        });
    }

    // Function to filter product grid
    function filterProductGrid(searchTerm = '') {
        const cards = document.querySelectorAll('.product-card');
        const selectedCategory = categoryFilter ? categoryFilter.value : '';
        searchTerm = searchTerm.toLowerCase();

        cards.forEach(card => {
            const name = card.querySelector('.product-name').textContent.toLowerCase();
            const category = card.querySelector('.product-category').textContent;
            const categoryMatch = !selectedCategory || category === document.querySelector(`option[value="${selectedCategory}"]`).textContent;
            const searchMatch = !searchTerm || name.includes(searchTerm);

            card.style.display = categoryMatch && searchMatch ? '' : 'none';
        });
    }

    // Function to sort product grid
    function sortProductGrid(column, direction) {
        const grid = document.querySelector('.product-grid');
        if (!grid) return;

        const cards = Array.from(grid.children);

        cards.sort((a, b) => {
            let valueA, valueB;

            switch(column) {
                case 'name':
                    valueA = a.querySelector('.product-name').textContent;
                    valueB = b.querySelector('.product-name').textContent;
                    break;
                case 'price':
                    valueA = parseFloat(a.querySelector('.product-price').textContent.replace(/[^0-9.-]+/g, ''));
                    valueB = parseFloat(b.querySelector('.product-price').textContent.replace(/[^0-9.-]+/g, ''));
                    break;
                case 'stock':
                    valueA = parseInt(a.querySelector('.product-stock').textContent.match(/\d+/)[0]);
                    valueB = parseInt(b.querySelector('.product-stock').textContent.match(/\d+/)[0]);
                    break;
                default:
                    return 0;
            }

            if (direction === 'asc') {
                return valueA > valueB ? 1 : -1;
            } else {
                return valueA < valueB ? 1 : -1;
            }
        });

        // Remove existing cards
        while (grid.firstChild) {
            grid.removeChild(grid.firstChild);
        }

        // Add sorted cards
        cards.forEach(card => grid.appendChild(card));
    }

    // Handle mobile swipe actions
    let touchStartX = 0;
    let touchEndX = 0;
    const swipeThreshold = 50;

    document.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        }, false);

        card.addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe(card);
        }, false);
    });

    function handleSwipe(card) {
        const swipeDistance = touchEndX - touchStartX;

        if (Math.abs(swipeDistance) >= swipeThreshold) {
            const actions = card.querySelector('.product-actions');
            if (!actions) return;
            
            if (swipeDistance > 0) {
                // Swipe right - show actions
                actions.style.transform = 'translateX(0)';
            } else {
                // Swipe left - hide actions
                actions.style.transform = 'translateX(100%)';
            }
        }
    }

    // Handle image preview in forms
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function(e) {
            const preview = this.parentElement.querySelector('.image-preview');
            if (preview) {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.style.backgroundImage = `url(${e.target.result})`;
                        preview.classList.add('has-image');
                    };
                    reader.readAsDataURL(file);
                }
            }
        });
    });

    // Handle form validation
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
});

// Product action functions
function viewProduct(id) {
    fetch(`get_product_details.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            const modalBody = document.getElementById('viewProductModalBody');
            if (modalBody) {
                modalBody.innerHTML = html;
                new bootstrap.Modal(document.getElementById('viewProductModal')).show();
            }
        })
        .catch(error => console.error('Error:', error));
}

function editProduct(id) {
    fetch(`get_product_details.php?id=${id}`)
        .then(response => response.json())
        .then(product => {
            const form = document.getElementById('editProductForm');
            if (!form) return;

            form.elements['id'].value = product.id;
            form.elements['name'].value = product.name;
            form.elements['description'].value = product.description;
            form.elements['price'].value = product.price;
            form.elements['quantity'].value = product.quantity;
            form.elements['category_id'].value = product.category_id;
            form.elements['low_stock_threshold'].value = product.low_stock_threshold;

            if (product.image) {
                const preview = form.querySelector('.image-preview');
                if (preview) {
                    preview.style.backgroundImage = `url(${product.image})`;
                    preview.classList.add('has-image');
                }
            }

            new bootstrap.Modal(document.getElementById('editProductModal')).show();
        })
        .catch(error => console.error('Error:', error));
}

function deleteProduct(id) {
    if (confirm('Are you sure you want to delete this product?')) {
        fetch('products.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_product&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            } else {
                alert(data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    }
}

// Helper function to format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
} 