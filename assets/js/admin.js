(function($) {
    'use strict';

    let selectedProducts = [];
    
    // Inicializar productos seleccionados
    if ($('#selected-products').length && $('#selected-products').data('products')) {
        selectedProducts = $('#selected-products').data('products');
        renderSelectedProducts();
    }

    // Búsqueda de productos
    $('#product-search').on('input', debounce(function() {
        const searchTerm = $(this).val() || '';
        if (searchTerm.length < 2) {
            $('#search-results').empty();
            return;
        }

        $.ajax({
            url: countdownAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'search_sale_products',
                nonce: countdownAjax.nonce,
                search: searchTerm
            },
            beforeSend: function() {
                $('#search-results').html('<p>Buscando productos en oferta...</p>');
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    renderSearchResults(response.data);
                } else {
                    $('#search-results').html('<p>No se encontraron productos en oferta</p>');
                }
            },
            error: function() {
                $('#search-results').html('<p>Error al buscar productos</p>');
            }
        });
    }, 500));

    // Agregar producto
    $(document).on('click', '.product-item', function() {
        const $this = $(this);
        const product = {
            id: $this.data('id'),
            title: $this.find('h4').text(),
            image: $this.find('img').attr('src'),
            price: $this.find('.price').html()
        };

        if (selectedProducts.length >= 20) {
            alert('Máximo 20 productos');
            return;
        }

        selectedProducts.push(product);
        $this.fadeOut(300, function() { $(this).remove(); });
        renderSelectedProducts();
    });

    // Remover producto
    $(document).on('click', '.remove-product', function() {
        const index = $(this).closest('.selected-product').index();
        selectedProducts.splice(index, 1);
        renderSelectedProducts();
    });

    // Guardar countdown
    $('#countdown-form').on('submit', function(e) {
        e.preventDefault();

        if (!selectedProducts.length) {
            alert('Selecciona al menos un producto en oferta');
            return;
        }

        const formData = new FormData(this);
        formData.append('action', 'save_countdown');
        formData.append('nonce', countdownAjax.nonce);
        formData.append('product_ids', selectedProducts.map(p => p.id).join(','));

        const $button = $(this).find('button[type="submit"]');
        $button.prop('disabled', true).text('Guardando...');

        $.ajax({
            url: countdownAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    window.location.href = response.data.redirect;
                } else {
                    alert(response.data || 'Error al guardar el countdown');
                }
            },
            error: function() {
                alert('Error al guardar el countdown');
            },
            complete: function() {
                $button.prop('disabled', false).text('Actualizar Countdown');
            }
        });
    });

    // Funciones auxiliares
    function renderSearchResults(products) {
        const $results = $('#search-results');
        $results.empty();

        products.forEach(product => {
            if (!selectedProducts.find(p => p.id === product.id)) {
                $results.append(`
                    <div class="product-item" data-id="${product.id}">
                        <img src="${product.image}" alt="${product.title}">
                        <div class="product-info">
                            <h4>${product.title}</h4>
                            <div class="price">${product.price}</div>
                        </div>
                    </div>
                `);
            }
        });
    }

    function renderSelectedProducts() {
        const $container = $('#selected-products');
        $container.empty();

        selectedProducts.forEach(product => {
            $container.append(`
                <div class="selected-product">
                    <img src="${product.image}" alt="${product.title}">
                    <div class="product-info">
                        <h4>${product.title}</h4>
                        <div class="price">${product.price}</div>
                    </div>
                    <button type="button" class="remove-product">×</button>
                </div>
            `);
        });
    }

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }
})(jQuery);