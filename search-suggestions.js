// Search Suggestions with Vietnamese support
(function() {
    let products = [];
    let suggestionTimer = null;

    // Function to remove Vietnamese tones
    function removeVietnameseTones(str) {
        if (!str) return '';
        str = str.toLowerCase();
        str = str.replace(/à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ/g, 'a');
        str = str.replace(/è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ/g, 'e');
        str = str.replace(/ì|í|ị|ỉ|ĩ/g, 'i');
        str = str.replace(/ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ/g, 'o');
        str = str.replace(/ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ/g, 'u');
        str = str.replace(/ỳ|ý|ỵ|ỷ|ỹ/g, 'y');
        str = str.replace(/đ/g, 'd');
        return str;
    }

    // Format currency
    function formatCurrency(val) {
        if (!Number.isFinite(val)) return "-";
        return val.toLocaleString('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 });
    }

    // Highlight matching text
    function highlightText(text, query) {
        if (!text || !query) return text;
        const searchTerm = removeVietnameseTones(query);
        const textNormalized = removeVietnameseTones(text);
        const index = textNormalized.indexOf(searchTerm);
        
        if (index === -1) return text;
        
        const before = text.substring(0, index);
        const match = text.substring(index, index + query.length);
        const after = text.substring(index + query.length);
        
        return `${before}<span class="suggestion-highlight">${match}</span>${after}`;
    }

    // Load products from API
    async function loadProducts() {
        try {
            const res = await fetch('/api/admin_product_list.php');
            const data = await res.json();
            if (data.success && data.data) {
                products = data.data.map(p => ({
                    id: p.id,
                    name: p.name,
                    sku: p.sku,
                    sale_price: p.sale_price,
                    original_price: p.original_price,
                    thumbnail_path: p.thumbnail_path,
                    slug: p.slug
                }));
            }
        } catch (error) {
            console.error('Failed to load products:', error);
        }
    }

    // Show suggestions
    function showSuggestions(query, suggestionsBox) {
        if (!query || query.length < 1) {
            suggestionsBox.classList.remove('show');
            suggestionsBox.innerHTML = '';
            return;
        }
        
        const searchTerm = removeVietnameseTones(query);
        const matches = products.filter(p => {
            const name = removeVietnameseTones(p.name || '');
            const sku = removeVietnameseTones(p.sku || '');
            return name.includes(searchTerm) || sku.includes(searchTerm);
        }).slice(0, 8);
        
        if (matches.length === 0) {
            suggestionsBox.innerHTML = '<div class="suggestion-empty">Không tìm thấy sản phẩm phù hợp</div>';
            suggestionsBox.classList.add('show');
            return;
        }
        
        suggestionsBox.innerHTML = matches.map(p => {
            const imgSrc = p.thumbnail_path || 'https://via.placeholder.com/50x50?text=SP';
            const priceVal = Number.isFinite(p.sale_price) ? p.sale_price : 
                           (Number.isFinite(p.original_price) ? p.original_price : 0);
            const priceText = priceVal ? formatCurrency(priceVal) : 'Đang cập nhật';
            const nameHighlighted = highlightText(p.name || '', query);
            
            return `
                <div class="suggestion-item" data-product-id="${p.id}" data-product-slug="${p.slug || ''}">
                    <img src="${imgSrc}" alt="${p.name}" class="suggestion-img" onerror="this.src='https://via.placeholder.com/50x50?text=SP'">
                    <div class="suggestion-info">
                        <div class="suggestion-name">${nameHighlighted}</div>
                        <div class="suggestion-sku">SKU: ${p.sku || 'N/A'}</div>
                    </div>
                    <div class="suggestion-price">${priceText}</div>
                </div>
            `;
        }).join('');
        
        suggestionsBox.classList.add('show');
        
        // Add click handlers
        suggestionsBox.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const productId = item.dataset.productId;
                window.location.href = `san-pham.html?id=${productId}`;
            });
        });
    }

    // Initialize search
    function initSearchSuggestions() {
        const searchInput = document.querySelector('.search-input');
        const searchBtn = document.querySelector('.search-btn');
        const suggestionsBox = document.getElementById('searchSuggestions');
        
        if (!searchInput || !searchBtn || !suggestionsBox) return;
        
        // Load products on init
        loadProducts();
        
        // Input event - show suggestions
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            
            if (suggestionTimer) clearTimeout(suggestionTimer);
            suggestionTimer = setTimeout(() => {
                showSuggestions(query, suggestionsBox);
            }, 200);
        });
        
        // Focus event - show suggestions if input has value
        searchInput.addEventListener('focus', (e) => {
            if (e.target.value.trim()) {
                showSuggestions(e.target.value.trim(), suggestionsBox);
            }
        });
        
        // Enter key - navigate to search page
        searchInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                suggestionsBox.classList.remove('show');
                const query = searchInput.value.trim();
                if (query) {
                    window.location.href = `san-pham.html?search=${encodeURIComponent(query)}`;
                }
            }
        });
        
        // Search button click
        searchBtn.addEventListener('click', () => {
            suggestionsBox.classList.remove('show');
            const query = searchInput.value.trim();
            if (query) {
                window.location.href = `san-pham.html?search=${encodeURIComponent(query)}`;
            }
        });
        
        // Close suggestions when clicking outside
        document.addEventListener('click', (e) => {
            if (!searchInput?.contains(e.target) && 
                !suggestionsBox?.contains(e.target) && 
                !searchBtn?.contains(e.target)) {
                suggestionsBox?.classList.remove('show');
            }
        });
    }

    // Auto-init when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSearchSuggestions);
    } else {
        initSearchSuggestions();
    }
})();
