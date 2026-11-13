const getAppBaseUrl = () => {
    if (typeof window.APP_BASE_URL === 'string' && window.APP_BASE_URL !== '') return window.APP_BASE_URL;
    const match = window.location.pathname.match(/^(\/[^\/]+)/);
    return match ? match[1] : '';
};
const appUrl = path => {
    const base = getAppBaseUrl();
    const cleanPath = path.startsWith('/') ? path : `/${path}`;
    return `${base}${cleanPath}`;
};
const getProductImageUrl = rawPath => {
    if (!rawPath || typeof rawPath !== 'string') return appUrl('/assets/banner/logo.png');
    const trimmed = rawPath.trim();
    if (trimmed.startsWith('/') || /^https?:\/\//i.test(trimmed)) return trimmed;
    const clean = trimmed.replace(/^(?:\.\.\/)+/, '');
    return appUrl(clean);
};
// ==================== SLIDESHOW ====================
function initSlideshow() {
    const slides = document.querySelectorAll('.slideshow .slide');
    if (slides.length < 2) return;

    const slideshow = document.querySelector('.banner-left');
    const dots = slideshow?.querySelectorAll('.banner-dots .dot') ?? [];
    const previousButton = slideshow?.querySelector('.banner-arrow.prev');
    const nextButton = slideshow?.querySelector('.banner-arrow.next');
    let currentSlide = 0;
    let timer;

    const showSlide = index => {
        slides[currentSlide].classList.remove('active');
        currentSlide = (index + slides.length) % slides.length;
        slides[currentSlide].classList.add('active');
        dots.forEach((dot, dotIndex) => {
            const isActive = dotIndex === currentSlide;
            dot.classList.toggle('active', isActive);
            if (isActive) dot.setAttribute('aria-current', 'true');
            else dot.removeAttribute('aria-current');
        });
    };

    const startTimer = () => {
        window.clearInterval(timer);
        timer = window.setInterval(() => showSlide(currentSlide + 1), 6000);
    };

    previousButton?.addEventListener('click', () => {
        showSlide(currentSlide - 1);
        startTimer();
    });
    nextButton?.addEventListener('click', () => {
        showSlide(currentSlide + 1);
        startTimer();
    });
    dots.forEach((dot, index) => dot.addEventListener('click', () => {
        showSlide(index);
        startTimer();
    }));
    slideshow?.addEventListener('mouseenter', () => window.clearInterval(timer));
    slideshow?.addEventListener('mouseleave', startTimer);
    startTimer();
}

// ==================== COMBO SLIDER & DATA ====================
// Data is dynamically fetched from the database

function renderProductRating(product) {
    const reviewCount = Number(product.review_count) || 0;
    const averageRating = Number(product.average_rating);
    if (reviewCount <= 0 || !Number.isFinite(averageRating)) return '';

    return `
        <div class="product-rating" aria-label="Đánh giá ${averageRating.toFixed(1)} trên 5 sao, ${reviewCount} lượt">
            <span class="product-rating-star" aria-hidden="true">★</span>
            <span>${averageRating.toFixed(1)}</span>
            <span class="product-rating-count">(${reviewCount})</span>
        </div>
    `;
}

function createSliderItems(container, products) {
    products.forEach(product => {
        const giftHTML = (product.gift && typeof product.gift === 'string' && product.gift.trim() !== '') ? `<p class="product-gift">${product.gift}</p>` : '';
        const discountHTML = product.discount ? `<div class="product-discount">${product.discount}</div>` : '';
        const imagePath = typeof product.image === 'string' ? product.image : '';
        const imageUrl = getProductImageUrl(imagePath);

        const stock = Number(product.stock) || 0;
        const soldCount = Number(product.soldCount) || 0;

        const stockHTML = stock > 0
            ? `<span class="product-stock" style="font-size: 0.82rem; color: #27ae60;">Kho: <strong>${stock}</strong></span>`
            : `<span class="product-stock" style="font-size: 0.82rem; color: #e74c3c; font-weight: 600;">Hết hàng</span>`;

        // Chỉ hiển thị lượt bán khi có đơn hàng (soldCount > 0)
        const soldHTML = soldCount > 0
            ? `<span class="sold" style="font-size: 0.82rem; color: #e67e22; font-weight: 500;">Đã bán ${soldCount}</span>`
            : '';

        const metaHTML = `
            <div class="product-meta" style="display: flex; justify-content: space-between; align-items: center; margin: 6px 0 8px;">
                ${stockHTML}
                ${soldHTML}
            </div>
        `;

        const itemHTML = `
            <div class="product-card" data-db-id="${product.id || ''}" data-category="${product.category || ''}" data-detail-url="${appUrl('/client/product.php')}?id=${encodeURIComponent(product.id || '')}" role="link" tabindex="0" aria-label="Xem chi tiết sản phẩm" style="flex-shrink: 0; width: 240px;">
                ${discountHTML}
                <button class="wishlist-btn" title="Yêu thích">♡</button>
                <img src="${imageUrl}" alt="${product.name}" class="product-image" onerror="this.src='${appUrl('/assets/banner/logo.png')}';">
                <div class="product-info">
                    <h3>${product.name}</h3>
                    <div class="product-price">
                        <span class="price-sale">${product.priceSale}<sup>đ</sup></span>
                        ${product.priceOriginal && parsePrice(product.priceOriginal) > 0 ? `<span class="price-original">${product.priceOriginal}<sup>đ</sup></span>` : ''}
                    </div>
                    ${renderProductRating(product)}
                    ${metaHTML}
                    ${giftHTML}
                    <button class="add-to-cart"${stock <= 0 ? ' disabled style="background:#bbb;cursor:not-allowed;"' : ''}>${stock <= 0 ? 'Tạm hết hàng' : 'Thêm vào giỏ'}</button>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', itemHTML);
    });
}

function initProductSlider(containerId, prevBtnId, nextBtnId, initialData, moreData) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const prevBtn = document.getElementById(prevBtnId);
    const nextBtn = document.getElementById(nextBtnId);
    if (container.dataset.loadError === 'true') {
        container.innerHTML = '<p class="home-empty-state">Chưa kết nối được danh mục sản phẩm. Vui lòng thử lại sau.</p>';
        if (prevBtn) prevBtn.hidden = true;
        if (nextBtn) nextBtn.hidden = true;
        return;
    }

    container.innerHTML = '';
    if (initialData.length === 0) {
        container.innerHTML = '<p class="home-empty-state">Sản phẩm đang được cập nhật. Mời bạn quay lại sau nhé.</p>';
        if (prevBtn) prevBtn.hidden = true;
        if (nextBtn) nextBtn.hidden = true;
        return;
    }
    createSliderItems(container, initialData);

    const comboItems = container;

    if (!prevBtn || !nextBtn) return;

    prevBtn.addEventListener('click', () => {
        comboItems.scrollBy({
            left: -256,
            behavior: 'smooth'
        });
    });

    let hasLoadedMore = false;
    nextBtn.addEventListener('click', () => {
        if (!hasLoadedMore) {
            createSliderItems(container, moreData);
            hasLoadedMore = true;

            nextBtn.innerHTML = '❯';
            nextBtn.style.width = '40px';
            nextBtn.style.padding = '0';
            nextBtn.style.borderRadius = '50%';
            nextBtn.style.fontSize = '1.2rem';
            nextBtn.style.fontWeight = 'normal';

            // Nút Thêm vào giỏ đã được xử lý qua Event Delegation trong initAddToCart()
        }

        setTimeout(() => {
            comboItems.scrollBy({
                left: 256,
                behavior: 'smooth'
            });
        }, 10);
    });
}

async function fetchProducts(category) {
    try {
        const response = await fetch(`${appUrl('/api/catalog/get_products.php')}?category=${encodeURIComponent(category)}`);
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();
        // Map database fields to JS field names
        return data.map(p => ({
            id: p.id,
            name: p.name,
            discount: p.discount,
            image: p.image,
            priceSale: p.price_sale,
            priceOriginal: p.price_original,
            gift: p.gift,
            stock: Number(p.stock) || 0,
            soldCount: Number(p.sold_count) || 0,
            average_rating: Number(p.average_rating) || 0,
            review_count: Number(p.review_count) || 0,
            category: p.category
        }));
    } catch (error) {
        console.error(`Lỗi khi tải sản phẩm danh mục ${category}:`, error);
        const containerIds = {
            combo: 'combo-items-container',
            suggest: 'suggest-items-container',
            featured: 'featured-items-container'
        };
        const container = document.getElementById(containerIds[category]);
        if (container) {
            container.dataset.loadError = 'true';
        }
        return [];
    }
}

async function initComboSlider() {
    // Tải dữ liệu từ Database thay vì dùng mảng cứng
    const comboData = await fetchProducts('combo');
    const suggestData = await fetchProducts('suggest');
    const featuredData = await fetchProducts('featured');

    // Khởi tạo các slider với dữ liệu từ DB
    initProductSlider('combo-items-container', 'combo-prev', 'combo-next', comboData, []);
    initProductSlider('suggest-items-container', 'suggest-prev', 'suggest-next', suggestData, []);
    initProductSlider('featured-items-container', 'featured-prev', 'featured-next', featuredData, []);
}

// ==================== SCROLL TO TOP ====================
function initScrollToTop() {
    const scrollBtn = document.querySelector('.scroll-to-top');

    if (!scrollBtn) return;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            scrollBtn.classList.add('show');
        } else {
            scrollBtn.classList.remove('show');
        }
    });

    scrollBtn.addEventListener('click', () => {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
}

// ==================== ADD TO CART ====================
let cart = [];

function getCartKey() {
    const userJson = localStorage.getItem('currentUser');
    if (userJson) {
        try {
            const user = JSON.parse(userJson);
            if (user && user.username) {
                return 'cart_' + user.username;
            }
        } catch (e) {
            console.error('Error parsing currentUser:', e);
        }
    }
    return 'cart_guest';
}

function loadCart() {
    try {
        const key = getCartKey();
        const savedCart = JSON.parse(localStorage.getItem(key));
        cart = Array.isArray(savedCart) ? savedCart : [];
        cart.forEach(item => {
            if (typeof item.selected !== 'boolean') item.selected = true;
        });
    } catch (e) {
        cart = [];
    }
}

function saveCart() {
    try {
        const key = getCartKey();
        localStorage.setItem(key, JSON.stringify(cart));
    } catch (e) {
        console.error('Error saving cart:', e);
    }
}

// Gọi loadCart ngay lập tức
loadCart();

function parsePrice(priceText) {
    return Number(priceText.replace(/[^\d]/g, '')) || 0;
}

function formatPrice(value) {
    return value.toLocaleString('vi-VN') + ' đ';
}

function updateCartCount() {
    const cartCountElements = document.querySelectorAll('.cart-count');
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);

    cartCountElements.forEach(el => {
        el.textContent = totalItems;
        el.classList.toggle('has-items', totalItems > 0);
    });
}

function renderCart() {
    const cartItemsContainer = document.querySelector('.cart-items');
    const cartTotalPrices = document.querySelectorAll('.cart-total-price');
    const cartTotalCount = document.querySelector('.cart-total-count');
    const selectAll = document.querySelector('.cart-select-all');
    const checkoutButton = document.querySelector('.checkout-btn');

    if (!cartItemsContainer || cartTotalPrices.length === 0 || !cartTotalCount) return;

    if (cart.length === 0) {
        cartItemsContainer.innerHTML = '<p class="empty-cart">Giỏ hàng đang trống. Thêm sản phẩm nào đó nhé!</p>';
        cartTotalPrices.forEach(element => { element.textContent = '0 đ'; });
        cartTotalCount.textContent = 'Đã chọn 0 / 0 sản phẩm';
        if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
        }
        if (checkoutButton) checkoutButton.disabled = true;
        return;
    }

    cartItemsContainer.innerHTML = '';
    const selectedItems = cart.filter(item => item.selected !== false);
    const totalPrice = selectedItems.reduce((sum, item) => sum + item.price * item.quantity, 0);
    const selectedQty = selectedItems.reduce((sum, item) => sum + item.quantity, 0);
    const totalQty = cart.reduce((sum, item) => sum + item.quantity, 0);

    cart.forEach((item, index) => {
        const imageUrl = escapeHtml(getProductImageUrl(item.image));
        const itemMarkup = `
            <div class="cart-item" data-index="${index}">
                <label class="cart-item-select-label">
                    <input type="checkbox" class="cart-item-select" aria-label="Chọn ${escapeHtml(item.name)}" ${item.selected !== false ? 'checked' : ''}>
                </label>
                <img src="${imageUrl}" alt="${escapeHtml(item.name)}" class="cart-item-image" onerror="this.src='${appUrl('/assets/banner/logo.png')}';">
                <div class="cart-item-details">
                    <p class="cart-item-name">${escapeHtml(item.name)}</p>
                    <div class="cart-item-actions">
                        <div class="cart-qty-controls">
                            <button class="qty-minus" data-action="decrease">−</button>
                            <span class="cart-qty">${item.quantity}</span>
                            <button class="qty-plus" data-action="increase">+</button>
                        </div>
                        <button class="remove-item-btn" data-action="remove">Xoá</button>
                    </div>
                    <p class="cart-item-price">${formatPrice(item.price * item.quantity)}</p>
                </div>
            </div>
        `;
        cartItemsContainer.insertAdjacentHTML('beforeend', itemMarkup);
    });

    cartTotalPrices.forEach(element => { element.textContent = formatPrice(totalPrice); });
    cartTotalCount.textContent = `Đã chọn ${selectedQty} / ${totalQty} sản phẩm`;
    if (selectAll) {
        selectAll.checked = selectedItems.length === cart.length;
        selectAll.indeterminate = selectedItems.length > 0 && selectedItems.length < cart.length;
    }
    if (checkoutButton) checkoutButton.disabled = selectedItems.length === 0;
}

function addItemToCart(product) {
    loadCart(); // Đồng bộ dữ liệu mới nhất

    if (!product || !product.name) {
        console.error('Sản phẩm không hợp lệ:', product);
        return;
    }

    const existingProduct = cart.find(item =>
        product.product_id
            ? Number(item.product_id) === Number(product.product_id) && item.name === product.name
            : item.name === product.name
    );

    if (existingProduct) {
        existingProduct.quantity += 1;
        existingProduct.selected = true;
    } else {
        cart.push({
            product_id: product.product_id || null,
            name: product.name,
            price: product.price || 0,
            image: product.image || 'assets/banner/logo.png',
            quantity: 1
        });
    }

    saveCart();
    updateCartCount();
    renderCart();
    showToast(`Đã thêm "${product.name}" vào giỏ hàng`);
    console.log('Đã thêm sản phẩm vào giỏ:', product.name);
}

function showToast(message) {
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }

    const toast = document.createElement('div');
    toast.className = 'toast-message';
    toast.textContent = message;
    toastContainer.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('show');
    }, 10);

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function removeCartItem(index) {
    cart.splice(index, 1);
    saveCart();
    updateCartCount();
    renderCart();
}

function updateCartQuantity(index, delta) {
    const item = cart[index];
    if (!item) return;
    item.quantity += delta;
    if (item.quantity < 1) item.quantity = 1;
    saveCart();
    updateCartCount();
    renderCart();
}

function initProductDetailsNavigation() {
    document.addEventListener('click', event => {
        const card = event.target.closest('.product-card[data-detail-url]');
        if (!card || event.target.closest('a, button, input, select, textarea, [contenteditable="true"]')) return;
        window.location.assign(card.dataset.detailUrl);
    });

    document.addEventListener('keydown', event => {
        const card = event.target.closest('.product-card[data-detail-url][role="link"]');
        if (!card || event.target !== card || (event.key !== 'Enter' && event.key !== ' ')) return;
        event.preventDefault();
        window.location.assign(card.dataset.detailUrl);
    });
}

function initAddToCart() {
    // Render initial state
    renderCart();
    updateCartCount();

    // Event Delegation for "Add to Cart" buttons
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.add-to-cart');
        if (!btn) return;

        e.preventDefault();

        const productCard = btn.closest('.product-card');
        if (!productCard) return;

        const nameElement = productCard.querySelector('h3, h1');
        const priceElement = productCard.querySelector('.price-sale');
        const imageElement = productCard.querySelector('.product-image');
        const colorSelect = productCard.querySelector('.color-select');

        let productName = nameElement ? nameElement.textContent.trim() : 'Sản phẩm';
        if (colorSelect && colorSelect.value) {
            productName += ` - Màu: ${colorSelect.value}`;
        }

        const product = {
            product_id: productCard.dataset.dbId ? Number(productCard.dataset.dbId) : null,
            name: productName,
            price: priceElement ? parsePrice(priceElement.textContent) : 0,
            image: imageElement ? imageElement.src : '',
        };

        console.log('Đang thêm sản phẩm:', product);
        addItemToCart(product);

        // UI Feedback
        const originalText = btn.textContent;
        btn.textContent = 'Đã thêm!';
        btn.classList.add('added');
        btn.style.background = '#0d1b2a';
        btn.style.color = '#ffffff';
        btn.style.borderColor = '#0d1b2a';

        setTimeout(() => {
            btn.textContent = originalText;
            btn.classList.remove('added');
            btn.style.background = ''; // Revert to CSS style
            btn.style.color = '';
            btn.style.borderColor = '';
        }, 1200);

        // Pulse animation for all cart counts
        const cartCounts = document.querySelectorAll('.cart-count');
        cartCounts.forEach(count => {
            count.style.animation = 'pulse 0.6s ease';
            setTimeout(() => {
                count.style.animation = '';
            }, 600);
        });
    });

    document.querySelector('.cart-items')?.addEventListener('click', (event) => {
        const action = event.target.getAttribute('data-action');
        const cartItem = event.target.closest('.cart-item');
        const index = cartItem ? Number(cartItem.dataset.index) : -1;

        if (action === 'remove' && index >= 0) {
            removeCartItem(index);
        }

        if (action === 'decrease' && index >= 0) {
            updateCartQuantity(index, -1);
        }

        if (action === 'increase' && index >= 0) {
            updateCartQuantity(index, 1);
        }
    });

    document.querySelector('.cart-items')?.addEventListener('change', event => {
        if (!event.target.matches('.cart-item-select')) return;
        const itemElement = event.target.closest('.cart-item');
        const index = itemElement ? Number(itemElement.dataset.index) : -1;
        if (index < 0 || !cart[index]) return;
        cart[index].selected = event.target.checked;
        saveCart();
        renderCart();
    });

    document.querySelector('.cart-select-all')?.addEventListener('change', event => {
        cart.forEach(item => { item.selected = event.target.checked; });
        saveCart();
        renderCart();
    });

    // Tiếp tục từ giỏ hàng sang trang đặt hàng.
    document.querySelector('.checkout-btn')?.addEventListener('click', () => {
        loadCart();
        if (!cart.some(item => item.selected !== false)) {
            alert('Vui lòng chọn ít nhất một sản phẩm để đặt hàng.');
            return;
        }

        const currentUser = localStorage.getItem('currentUser');
        if (!currentUser) {
            const loginModal = document.getElementById('login-modal');
            if (loginModal) {
                loginModal.classList.add('active');
            } else {
                alert('Vui lòng đăng nhập tài khoản để thực hiện thanh toán!');
            }
            return;
        }

        window.location.assign(appUrl('/client/checkout.php'));
    });
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[character]);
}

function renderCheckoutSummary() {
    const container = document.querySelector('.checkout-order-items');
    const totalPrice = document.getElementById('checkout-total-price');
    if (!container || !totalPrice) return;

    loadCart();
    container.innerHTML = '';
    const selectedItems = cart.filter(item => item.selected !== false);
    if (selectedItems.length === 0) {
        container.innerHTML = '<p class="empty-cart">Chưa có sản phẩm nào được chọn. Quay lại giỏ hàng để chọn sản phẩm.</p>';
    }
    selectedItems.forEach(item => {
        const row = document.createElement('div');
        row.className = 'checkout-order-item';
        const image = document.createElement('img');
        image.src = getProductImageUrl(item.image);
        image.alt = '';
        image.onerror = () => { image.src = appUrl('/assets/banner/logo.png'); };
        const details = document.createElement('div');
        details.className = 'checkout-order-item-details';
        details.innerHTML = `<strong>${escapeHtml(item.name)}</strong><span>Số lượng: ${Number(item.quantity) || 0}</span>`;
        const price = document.createElement('strong');
        price.className = 'checkout-order-item-price';
        price.textContent = formatPrice((Number(item.price) || 0) * (Number(item.quantity) || 0));
        row.append(image, details, price);
        container.appendChild(row);
    });

    totalPrice.textContent = formatPrice(selectedItems.reduce((sum, item) =>
        sum + (Number(item.price) || 0) * (Number(item.quantity) || 0), 0));
}

function renderCheckoutReceipt(orderId, customer, items, total) {
    const success = document.getElementById('checkout-success');
    if (!success) return;

    success.querySelector('.checkout-success-order-id').textContent = `Mã đơn hàng: #${orderId}`;
    const customerDetails = success.querySelector('.checkout-receipt-customer');
    customerDetails.innerHTML = '';
    [
        ['Người nhận', customer.name],
        ['Số điện thoại', customer.phone],
        ['Email', customer.email],
        ['Địa chỉ giao hàng', customer.address]
    ].forEach(([label, value]) => {
        const row = document.createElement('p');
        const labelElement = document.createElement('strong');
        labelElement.textContent = `${label}: `;
        row.append(labelElement, document.createTextNode(value));
        customerDetails.appendChild(row);
    });

    const receiptItems = success.querySelector('.checkout-receipt-items');
    receiptItems.innerHTML = '';
    items.forEach(item => {
        const row = document.createElement('div');
        row.className = 'checkout-receipt-item';
        const name = document.createElement('strong');
        name.textContent = item.product_name || item.name;
        const quantity = document.createElement('span');
        quantity.textContent = `Số lượng: ${item.quantity}`;
        const price = document.createElement('span');
        price.textContent = formatPrice((Number(item.price ?? item.unit_price) || 0) * (Number(item.quantity) || 0));
        row.append(name, quantity, price);
        receiptItems.appendChild(row);
    });
    success.querySelector('.checkout-receipt-total').textContent = formatPrice(Number(total) || 0);
    success.hidden = false;
    document.querySelector('.checkout-page-layout').hidden = true;
}

async function loadCheckoutLocations() {
    const provinceSelect = document.getElementById('checkout-province');
    const wardSelect = document.getElementById('checkout-ward');
    if (!provinceSelect || !wardSelect) return;

    provinceSelect.innerHTML = '<option value="">Đang tải tỉnh/thành...</option>';
    try {
        const response = await fetch('https://provinces.open-api.vn/api/v2/p/');
        if (!response.ok) throw new Error(`Tải danh sách tỉnh/thành thất bại (${response.status}).`);
        const provinces = await response.json();
        if (!Array.isArray(provinces) || provinces.length === 0) {
            throw new Error('Dữ liệu tỉnh/thành không hợp lệ.');
        }

        provinceSelect.innerHTML = '<option value="">Chọn tỉnh/thành phố</option>';
        provinces.forEach(province => {
            const option = document.createElement('option');
            option.value = province.code;
            option.textContent = province.name;
            provinceSelect.appendChild(option);
        });
        provinceSelect.disabled = false;
        wardSelect.innerHTML = '<option value="">Chọn tỉnh/thành trước</option>';

        provinceSelect.addEventListener('change', async () => {
            const provinceCode = provinceSelect.value;
            wardSelect.disabled = true;
            wardSelect.innerHTML = '<option value="">Đang tải xã/phường...</option>';
            if (!provinceCode) {
                wardSelect.innerHTML = '<option value="">Chọn tỉnh/thành trước</option>';
                return;
            }

            try {
                const wardResponse = await fetch(`https://provinces.open-api.vn/api/v2/p/${encodeURIComponent(provinceCode)}?depth=2`);
                if (!wardResponse.ok) throw new Error(`Tải danh sách xã/phường thất bại (${wardResponse.status}).`);
                const selectedProvince = await wardResponse.json();
                if (provinceSelect.value !== provinceCode) return;
                if (!Array.isArray(selectedProvince.wards) || selectedProvince.wards.length === 0) {
                    throw new Error('Không có dữ liệu xã/phường cho tỉnh/thành đã chọn.');
                }

                wardSelect.innerHTML = '<option value="">Chọn xã/phường</option>';
                selectedProvince.wards.forEach(ward => {
                    const option = document.createElement('option');
                    option.value = ward.name;
                    option.textContent = ward.name;
                    wardSelect.appendChild(option);
                });
                wardSelect.disabled = false;
            } catch (error) {
                if (provinceSelect.value !== provinceCode) return;
                console.error('Không tải được xã/phường:', error);
                wardSelect.innerHTML = '<option value="">Không tải được dữ liệu — vui lòng thử lại</option>';
            }
        });
    } catch (error) {
        console.error('Không tải được tỉnh/thành:', error);
        provinceSelect.innerHTML = '<option value="">Không tải được dữ liệu — vui lòng tải lại trang</option>';
        wardSelect.innerHTML = '<option value="">Chọn tỉnh/thành trước</option>';
    }
}

function initCheckoutPage() {
    const form = document.getElementById('checkout-form');
    const submitButton = document.getElementById('submit-checkout');
    const message = document.getElementById('checkout-form-message');
    const loginNotice = document.getElementById('checkout-login-notice');
    if (!form || !submitButton || !message) return;

    renderCheckoutSummary();
    loadCheckoutLocations();

    const userJson = localStorage.getItem('currentUser');
    if (userJson) {
        try {
            const user = JSON.parse(userJson);
            if (!['admin', 'manager', 'staff'].includes(user.role)) {
                document.getElementById('checkout-name').value = user.username || '';
                document.getElementById('checkout-phone').value = user.phone || '';
            }
        } catch (error) {
            console.error('Không đọc được thông tin tài khoản:', error);
        }
    } else if (loginNotice) {
        loginNotice.hidden = false;
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        message.textContent = '';
        loadCart();
        const selectedItems = cart.filter(item => item.selected !== false);
        if (selectedItems.length === 0) {
            message.textContent = 'Chưa có sản phẩm nào được chọn. Vui lòng quay lại giỏ hàng để chọn sản phẩm.';
            return;
        }

        if (!localStorage.getItem('currentUser')) {
            if (loginNotice) loginNotice.hidden = false;
            document.getElementById('login-modal')?.classList.add('active');
            return;
        }

        if (!form.reportValidity()) return;
        const provinceSelect = document.getElementById('checkout-province');
        const wardSelect = document.getElementById('checkout-ward');
        if (!provinceSelect.value || !wardSelect.value || wardSelect.disabled) {
            message.textContent = 'Vui lòng chọn đầy đủ tỉnh/thành phố và xã/phường.';
            (provinceSelect.value ? wardSelect : provinceSelect).focus();
            return;
        }

        const formData = new FormData(form);
        const address = [
            formData.get('address_detail'),
            formData.get('ward'),
            formData.get('province')
                ? document.querySelector('#checkout-province option:checked').textContent
                : ''
        ].filter(Boolean).join(', ');
        const customer = {
            name: String(formData.get('name')).trim(),
            phone: String(formData.get('phone')).trim(),
            email: String(formData.get('email')).trim(),
            address
        };

        submitButton.disabled = true;
        submitButton.textContent = 'Đang xử lý đơn hàng...';
        try {
            const response = await fetch(appUrl('/api/orders/place_order.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    cart: selectedItems,
                    customer
                })
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'success') {
                throw new Error(result.message || 'Không thể đặt hàng. Vui lòng thử lại.');
            }

            cart = cart.filter(item => item.selected === false);
            saveCart();
            updateCartCount();
            renderCart();
            renderCheckoutSummary();
            form.hidden = true;
            renderCheckoutReceipt(result.order_id, customer, result.items || selectedItems, result.total_price);
        } catch (error) {
            console.error('Đặt hàng thất bại:', error);
            message.textContent = error.message || 'Không thể kết nối máy chủ. Vui lòng thử lại.';
            message.classList.remove('is-success');
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Hoàn tất đặt hàng';
        }
    });
}

// Helper to format price from DB safely without parsing dot decimals as cents
function formatDbPrice(priceStr) {
    if (!priceStr) return '';
    const str = priceStr.toString().trim();
    if (str.includes('.') || str.includes(',')) {
        return str;
    }
    const num = Number(str);
    return isNaN(num) ? str : num.toLocaleString('vi-VN');
}

function normalizeVietnamese(str) {
    if (!str) return '';
    return str
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '') // remove accent marks
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'd')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

// ==================== PAGINATION SYSTEM ====================
const PRODUCTS_PER_PAGE = 9; // 3 cột × 3 dòng
let _paginationCurrentPage = 1;
let _paginationAllProducts = []; // Lưu toàn bộ sản phẩm đã fetch (dùng cho phân trang)

function buildProductCardHTML(product) {
    const badgeHTML = product.discount ? `<div class="product-badge">${product.discount}</div>` : '';
    const giftHTML = (product.gift && product.gift.trim() !== '') ? `<p class="product-gift" style="font-size: 0.8rem; color: var(--gold); margin: 5px 0;">🎁 ${product.gift}</p>` : '';

    const priceSaleFormatted = formatDbPrice(product.price_sale);
    const priceOriginalHTML = product.price_original && parsePrice(product.price_original) > 0
        ? `<span class="price-original" style="text-decoration: line-through; color: #999; margin-left: 10px; font-size: 0.9rem;">${formatDbPrice(product.price_original)}<sup>đ</sup></span>`
        : '';

    const stock = parseInt(product.stock, 10) || 0;
    const soldCount = parseInt(product.sold_count, 10) || 0;

    const stockHTML = stock > 0
        ? `<span class="product-stock" style="font-size: 0.85rem; color: #27ae60;">Kho: <strong>${stock}</strong></span>`
        : `<span class="product-stock" style="font-size: 0.85rem; color: #e74c3c; font-weight: 600;">Hết hàng</span>`;

    const soldHTML = soldCount > 0
        ? `<span class="sold" style="font-size: 0.85rem; color: #e67e22; font-weight: 500;">Đã bán ${soldCount}</span>`
        : '';

    const metaHTML = `
        <div class="product-meta" style="display: flex; justify-content: space-between; align-items: center; margin: 6px 0 8px;">
            ${stockHTML}
            ${soldHTML}
        </div>
    `;

    const priceSaleRaw = (typeof product.price_sale_raw !== 'undefined') ? product.price_sale_raw : parsePrice(product.price_sale);
    const hasDiscount = Boolean((product.discount && product.discount.trim() !== '') || (product.price_original_raw > product.price_sale_raw));
    const hasGift = Boolean(product.gift && product.gift.trim() !== '');

    return `
        <div class="product-card" data-category="${product.category || ''}" data-db-id="${product.id}" data-price="${priceSaleRaw}" data-stock="${stock}" data-discount="${hasDiscount ? '1' : '0'}" data-gift="${hasGift ? '1' : '0'}" data-sold="${soldCount}" data-detail-url="${appUrl('/client/product.php')}?id=${encodeURIComponent(product.id)}" role="link" tabindex="0" aria-label="Xem chi tiết sản phẩm">
            ${badgeHTML}
            <button class="wishlist-btn" title="Yêu thích">♡</button>
            <img src="${getProductImageUrl(product.image)}" alt="${product.name}" class="product-image" onerror="this.src='${appUrl('/assets/banner/logo.png')}';">
            <div class="product-info">
                <h3>${product.name}</h3>
                <div class="product-price">
                    <span class="price-sale">${priceSaleFormatted}<sup>đ</sup></span>
                    ${priceOriginalHTML}
                </div>
                ${renderProductRating(product)}
                ${metaHTML}
                ${giftHTML}
                <button class="add-to-cart"${stock <= 0 ? ' disabled style="background:#bbb;cursor:not-allowed;"' : ''}>${stock <= 0 ? 'Tạm hết hàng' : 'Thêm vào giỏ'}</button>
            </div>
        </div>
    `;
}

// Tạo hoặc lấy container phân trang
function getOrCreatePaginationContainer() {
    let paginationEl = document.getElementById('pagination-controls');
    if (!paginationEl) {
        paginationEl = document.createElement('div');
        paginationEl.id = 'pagination-controls';
        paginationEl.className = 'pagination-controls';
        // Chèn sau product-grid, trước filter-empty-state
        const gridContainer = document.getElementById('category-products-container');
        if (gridContainer && gridContainer.parentNode) {
            const emptyState = document.getElementById('filter-empty-state');
            if (emptyState) {
                gridContainer.parentNode.insertBefore(paginationEl, emptyState);
            } else {
                gridContainer.parentNode.appendChild(paginationEl);
            }
        }
    }
    return paginationEl;
}

// Render phân trang UI
function renderPaginationUI(totalItems, currentPage, onPageChange) {
    const paginationEl = getOrCreatePaginationContainer();
    const totalPages = Math.ceil(totalItems / PRODUCTS_PER_PAGE);

    if (totalPages <= 1) {
        paginationEl.style.display = 'none';
        return;
    }

    paginationEl.style.display = 'flex';
    paginationEl.innerHTML = '';

    // Nút Trước
    const prevBtn = document.createElement('button');
    prevBtn.className = 'pagination-btn pagination-prev' + (currentPage <= 1 ? ' disabled' : '');
    prevBtn.innerHTML = '‹';
    prevBtn.title = 'Trang trước';
    prevBtn.disabled = currentPage <= 1;
    prevBtn.addEventListener('click', () => {
        if (currentPage > 1) onPageChange(currentPage - 1);
    });
    paginationEl.appendChild(prevBtn);

    // Tính toán dải số trang hiển thị
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    if (endPage - startPage + 1 < maxVisible) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }

    // Trang đầu + dấu ...
    if (startPage > 1) {
        paginationEl.appendChild(createPageBtn(1, currentPage, onPageChange));
        if (startPage > 2) {
            const dots = document.createElement('span');
            dots.className = 'pagination-dots';
            dots.textContent = '...';
            paginationEl.appendChild(dots);
        }
    }

    // Các nút trang
    for (let i = startPage; i <= endPage; i++) {
        paginationEl.appendChild(createPageBtn(i, currentPage, onPageChange));
    }

    // Dấu ... + trang cuối
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            const dots = document.createElement('span');
            dots.className = 'pagination-dots';
            dots.textContent = '...';
            paginationEl.appendChild(dots);
        }
        paginationEl.appendChild(createPageBtn(totalPages, currentPage, onPageChange));
    }

    // Nút Sau
    const nextBtn = document.createElement('button');
    nextBtn.className = 'pagination-btn pagination-next' + (currentPage >= totalPages ? ' disabled' : '');
    nextBtn.innerHTML = '›';
    nextBtn.title = 'Trang sau';
    nextBtn.disabled = currentPage >= totalPages;
    nextBtn.addEventListener('click', () => {
        if (currentPage < totalPages) onPageChange(currentPage + 1);
    });
    paginationEl.appendChild(nextBtn);
}

function createPageBtn(pageNum, currentPage, onPageChange) {
    const btn = document.createElement('button');
    btn.className = 'pagination-btn pagination-page' + (pageNum === currentPage ? ' active' : '');
    btn.textContent = pageNum;
    btn.addEventListener('click', () => {
        if (pageNum !== currentPage) onPageChange(pageNum);
    });
    return btn;
}

// Hàm chuyển trang
function goToPage(page) {
    _paginationCurrentPage = page;
    const container = document.getElementById('category-products-container');
    if (!container) return;

    renderDbProducts(_paginationAllProducts, container, page);
    applyUserRole();

    // Cuộn lên đầu lưới sản phẩm
    const categoryMain = document.querySelector('.category-main');
    if (categoryMain) {
        categoryMain.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Global function to render database products in a dynamic grid (with pagination)
function renderDbProducts(dbProducts, container, page) {
    container.innerHTML = '';
    const paginationEl = document.getElementById('pagination-controls');

    if (!dbProducts || dbProducts.length === 0) {
        container.innerHTML = '<p class="empty-cart" style="grid-column: 1/-1; text-align: center; padding: 40px; font-size: 1.1rem; color: #7a7a7a;">Không có sản phẩm nào được tìm thấy! 😢</p>';
        if (paginationEl) paginationEl.style.display = 'none';
        return;
    }

    // Lưu toàn bộ sản phẩm vào biến global
    _paginationAllProducts = dbProducts;

    // Xác định trang hiện tại
    const currentPage = page || 1;
    _paginationCurrentPage = currentPage;
    const totalPages = Math.ceil(dbProducts.length / PRODUCTS_PER_PAGE);

    // Cắt mảng sản phẩm theo trang
    const startIdx = (currentPage - 1) * PRODUCTS_PER_PAGE;
    const endIdx = startIdx + PRODUCTS_PER_PAGE;
    const pageProducts = dbProducts.slice(startIdx, endIdx);

    pageProducts.forEach(product => {
        container.insertAdjacentHTML('beforeend', buildProductCardHTML(product));
    });

    // Render phân trang
    renderPaginationUI(dbProducts.length, currentPage, goToPage);

    // Danh mục hiển thị sản phẩm theo trang.
    const loadMoreContainer = document.querySelector('.load-more-container');
    if (loadMoreContainer) loadMoreContainer.style.display = 'none';
}

function initSearch() {
    const searchInput = document.querySelector('.search');
    if (!searchInput) return;

    // 1. Wrap the search input in a wrapper for absolute positioning of results
    const wrapper = document.createElement('div');
    wrapper.className = 'search-wrapper';
    searchInput.parentNode.insertBefore(wrapper, searchInput);
    wrapper.appendChild(searchInput);

    // 2. Create the search results dropdown element
    const dropdown = document.createElement('div');
    dropdown.className = 'search-results-dropdown';
    wrapper.appendChild(dropdown);

    let allProducts = [];
    let isFetched = false;
    let fetchPromise = null;
    let loadError = false;

    // Preload products when user focuses the search input to ensure instant typing response
    async function preloadProducts() {
        if (isFetched) return allProducts;
        if (fetchPromise) return fetchPromise;

        fetchPromise = (async () => {
            try {
                const response = await fetch(appUrl('/api/catalog/get_products.php'));
                if (!response.ok) throw new Error('Không thể tải danh sách sản phẩm.');
                const products = await response.json();
                if (!Array.isArray(products)) throw new Error('Dữ liệu sản phẩm không hợp lệ.');
                allProducts = products;
                isFetched = true;
                loadError = false;
                console.log('🔍 Đã tải trước danh sách sản phẩm phục vụ tìm kiếm:', allProducts.length);
            } catch (error) {
                loadError = true;
                console.error('Lỗi tải danh sách sản phẩm cho tìm kiếm:', error);
            } finally {
                fetchPromise = null;
            }

            return allProducts;
        })();

        return fetchPromise;
    }

    searchInput.addEventListener('focus', async () => {
        await preloadProducts();
        if (searchInput.value.trim()) updateSearchResults();
    });

    searchInput.addEventListener('input', updateSearchResults);

    async function updateSearchResults() {
        const query = searchInput.value.trim().toLowerCase();
        if (!query) {
            dropdown.classList.remove('active');
            dropdown.innerHTML = '';
            return;
        }

        dropdown.textContent = 'Đang tìm sản phẩm...';
        dropdown.classList.add('active');
        const products = await preloadProducts();
        if (searchInput.value.trim().toLowerCase() !== query) return;
        if (loadError) {
            dropdown.textContent = 'Không tải được sản phẩm. Vui lòng thử lại.';
            dropdown.classList.add('active');
            return;
        }

        const filtered = products.filter(product =>
            product.name && product.name.toLowerCase().includes(query)
        );

        renderSearchResults(filtered, dropdown);
    }

    // Close search dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!wrapper.contains(e.target)) {
            dropdown.classList.remove('active');
        }
    });

    // Handle pressing Enter to search on page
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            const query = searchInput.value.trim();
            dropdown.classList.remove('active');

            if (query) {
                handleScreenSearch(query);
            }
        }
    });

    async function handleScreenSearch(query) {
        const container = document.getElementById('category-products-container');
        if (container) {
            // Make sure we have loaded all database products for this category/page or general
            let searchProducts = allProducts;
            if (searchProducts.length === 0) {
                try {
                    const response = await fetch(appUrl('/api/catalog/get_products.php'));
                    if (response.ok) {
                        searchProducts = await response.json();
                    }
                } catch (err) {
                    console.error(err);
                }
            }

            // Filter products
            const filtered = searchProducts.filter(product =>
                product.name && product.name.toLowerCase().includes(query.toLowerCase())
            );

            // Render matching products directly on page screen grid!
            renderDbProducts(filtered, container);
            applyUserRole(); // Ensure edit privileges are shown if Admin is logged in

            // Update screen title dynamically
            const pageTitle = document.querySelector('.page-title-left') || document.querySelector('.page-title');
            if (pageTitle) {
                pageTitle.textContent = `KẾT QUẢ TÌM KIẾM: "${query.toUpperCase()}"`;
            }
        } else {
            // If on homepage or other page without a product grid, redirect to sonmoi.html with query parameter
            window.location.href = `${appUrl('/index.php')}?search=${encodeURIComponent(query)}`;
        }
    }

    function renderSearchResults(products, container) {
        container.innerHTML = '';
        if (products.length === 0) {
            container.innerHTML = '<div class="search-result-empty">Không tìm thấy sản phẩm nào khớp! 😢</div>';
            container.classList.add('active');
            return;
        }

        products.forEach(product => {
            const priceSaleFormatted = formatDbPrice(product.price_sale) + ' đ';
            const imagePath = typeof product.image === 'string' ? product.image : '';
            const imageUrl = imagePath.startsWith('/') || /^https?:\/\//i.test(imagePath)
                ? imagePath
                : appUrl(imagePath.replace(/^(?:\.\.\/)+/, ''));

            const stock = parseInt(product.stock, 10) || 0;
            const stockInfo = stock > 0 ? `<small style="color: #27ae60; font-size: 0.8rem;">Kho: ${stock}</small>` : `<small style="color: #e74c3c; font-size: 0.8rem;">Hết hàng</small>`;

            const item = document.createElement('div');
            item.className = 'search-result-item';
            item.innerHTML = `
                <img src="${imageUrl}" alt="${product.name}" class="search-result-image" onerror="this.src='${appUrl('/assets/banner/logo.png')}';">
                <div class="search-result-info">
                    <p class="search-result-name">${product.name}</p>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <span class="search-result-price">${priceSaleFormatted}</span>
                        ${stockInfo}
                    </div>
                </div>
                <button class="search-result-btn"${stock <= 0 ? ' disabled style="background:#bbb;cursor:not-allowed;"' : ''}>${stock <= 0 ? 'Hết hàng' : 'Thêm vào giỏ'}</button>
            `;

            // Handle add to cart click
            const btn = item.querySelector('.search-result-btn');
            btn.addEventListener('click', (e) => {
                e.stopPropagation(); // Avoid triggering parent item click

                const prod = {
                    product_id: Number(product.id),
                    name: product.name,
                    price: parsePrice(product.price_sale), // Fixed pricing bug using parsePrice
                    image: product.image
                };

                addItemToCart(prod);

                // Temporary feedback state
                btn.textContent = 'Đã thêm!';
                btn.style.background = '#4CAF50';
                setTimeout(() => {
                    btn.textContent = 'Thêm vào giỏ';
                    btn.style.background = '';
                }, 1200);
            });

            // Make whole row clickable to add to cart
            item.addEventListener('click', () => {
                btn.click();
            });

            container.appendChild(item);
        });

        container.classList.add('active');
    }
}


// ==================== TAB FUNCTIONALITY ====================
function initTabs() {
    const tabBtns = document.querySelectorAll('.tab-btn');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Xóa class active từ tất cả buttons
            tabBtns.forEach(b => b.classList.remove('active'));
            // Thêm class active vào button được click
            btn.classList.add('active');

            const tabName = btn.getAttribute('data-tab');
            console.log('Switched to tab:', tabName);
            // Thêm logic để hiển thị/ẩn content theo tab
        });
    });
}

// ==================== AUTHENTICATION & RBAC ====================
const REQUIRED_PASSWORD_LENGTH = 8;

function initAuth() {
    const loginTriggers = document.querySelectorAll('.login-trigger');
    const loginModal = document.getElementById('login-modal');
    const closeLogin = document.getElementById('close-login');
    const submitLogin = document.getElementById('submit-login');
    const usernameInput = document.getElementById('login-username');
    const passwordInput = document.getElementById('login-password');
    const authContainer = document.getElementById('auth-container');

    const registerModal = document.getElementById('register-modal');
    const closeRegister = document.getElementById('close-register');

    // Mở modal đăng nhập
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('login-trigger')) {
            if (registerModal) registerModal.classList.remove('active');
            if (loginModal) loginModal.classList.add('active');
            const lm = document.getElementById('login-message');
            if (lm) lm.textContent = '';
        }
    });

    // Mở modal đăng ký
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('register-trigger')) {
            if (loginModal) loginModal.classList.remove('active');
            if (registerModal) registerModal.classList.add('active');
            const rm = document.getElementById('register-message');
            if (rm) rm.textContent = '';
        }
    });

    // Đóng modal
    if (closeLogin) {
        closeLogin.addEventListener('click', () => {
            loginModal.classList.remove('active');
        });
    }
    if (closeRegister) {
        closeRegister.addEventListener('click', () => {
            registerModal.classList.remove('active');
        });
    }

    // Xử lý đăng nhập qua PHP
    if (submitLogin) {
        submitLogin.addEventListener('click', async () => {
            const username = usernameInput.value.trim();
            const password = passwordInput.value.trim();
            const loginMsg = document.getElementById('login-message');

            const showLoginMsg = (text, color) => {
                if (loginMsg) {
                    loginMsg.textContent = text;
                    loginMsg.style.color = color || '#c0392b';
                }
            };

            if (!username || !password) {
                showLoginMsg('Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!');
                return;
            }

            showLoginMsg('Đang đăng nhập...', '#666');
            submitLogin.disabled = true;

            try {
                const response = await fetch(appUrl('/api/auth/login.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });

                const data = await response.json();

                if (data.status === 'success') {
                    // Tác dụng: Phân tách luồng sau đăng nhập - Khách hàng lưu phiên mua sắm, Quản trị viên chuyển tiếp về bảng quản trị
                    if (data.role === 'customer') {
                        const user = { username: data.username, role: data.role };
                        localStorage.setItem('currentUser', JSON.stringify(user));
                        loginModal.classList.remove('active');
                        applyUserRole();
                        window.location.reload();
                    } else {
                        localStorage.removeItem('currentUser');
                        loginModal.classList.remove('active');
                        window.location.href = appUrl('/admin/index.php');
                    }
                } else {
                    showLoginMsg(data.message || 'Tên đăng nhập hoặc mật khẩu không đúng.');
                }
            } catch (error) {
                console.error(error);
                showLoginMsg('Lỗi kết nối máy chủ. Vui lòng kiểm tra XAMPP.');
            } finally {
                submitLogin.disabled = false;
            }
        });
    }

    // Xử lý Đăng ký qua PHP
    const submitRegister = document.getElementById('submit-register');
    const registerUsernameInput = document.getElementById('register-username');
    const registerPhoneInput = document.getElementById('register-phone');
    const registerPasswordInput = document.getElementById('register-password');
    const registerConfirmPasswordInput = document.getElementById('register-confirm-password');

    if (submitRegister) {
        submitRegister.addEventListener('click', async () => {
            const username        = registerUsernameInput.value.trim();
            const phone           = registerPhoneInput ? registerPhoneInput.value.trim() : '';
            const password        = registerPasswordInput.value;
            const confirmPassword = registerConfirmPasswordInput.value;
            const regMsg          = document.getElementById('register-message');

            const showRegMsg = (text, color) => {
                if (regMsg) {
                    regMsg.textContent = text;
                    regMsg.style.color = color || '#c0392b';
                }
            };

            if (!username || !phone || !password || !confirmPassword) {
                showRegMsg('Vui lòng nhập đầy đủ thông tin để đăng ký!');
                return;
            }

            if (!/^0[0-9]{9}$/.test(phone.replace(/[\s().-]/g, ''))) {
                showRegMsg('Số điện thoại không hợp lệ (phải đúng 10 số, ví dụ: 0901234567)');
                return;
            }
            const isValidPassword = (pwd) => {
                return pwd.length >= 8 &&
                    /[a-z]/.test(pwd) &&
                    /[A-Z]/.test(pwd) &&
                    /[0-9]/.test(pwd) &&
                    /[^a-zA-Z0-9]/.test(pwd);
            };

            if (!isValidPassword(password)) {
                showRegMsg('Mật khẩu phải có tối thiểu 8 ký tự, gồm chữ thường, chữ in hoa, số và ký tự đặc biệt (ví dụ: Abc@1234).');
                return;
            }
            if (password !== confirmPassword) {
                showRegMsg('Mật khẩu nhập lại không khớp!');
                return;
            }

            showRegMsg('Đang xử lý...', '#666');
            submitRegister.disabled = true;

            try {
                const response = await fetch(appUrl('/api/auth/register.php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, phone, password })
                });

                const data = await response.json();

                if (data.status === 'success') {
                    alert(data.message);
                    registerModal?.classList.remove('active');
                    loginModal?.classList.add('active');
                    if (usernameInput) usernameInput.value = username;
                    if (registerPhoneInput) registerPhoneInput.value = '';
                    registerUsernameInput.value = '';
                    registerPasswordInput.value = '';
                    registerConfirmPasswordInput.value = '';
                } else {
                    showRegMsg(data.message || 'Không thể đăng ký lúc này.');
                }
            } catch (error) {
                console.error(error);
                showRegMsg('Lỗi kết nối máy chủ. Vui lòng kiểm tra XAMPP.');
            } finally {
                submitRegister.disabled = false;
            }
        });
    }

    // Đăng xuất qua PHP
    document.addEventListener('click', async (e) => {
        if (e.target.classList.contains('logout-btn')) {
            try {
                const response = await fetch(appUrl('/api/auth/logout.php'));
                const data = await response.json();
                if (data.status === 'success') {
                    localStorage.removeItem('currentUser');
                    applyUserRole();
                    alert('Đã đăng xuất thành công!');
                    window.location.reload(); // Load lại trang để cập nhật trạng thái sạch
                }
            } catch (error) {
                console.error(error);
                alert('Lỗi khi đăng xuất!');
            }
        }
    });

    // Kiểm tra session từ server khi khởi tạo
    checkSession();
}

// Tác dụng: Đồng bộ trạng thái đăng nhập của khách hàng từ máy chủ về trình duyệt, loại bỏ dữ liệu nếu phiên đã hết hạn
async function checkSession() {
    try {
        const response = await fetch(appUrl('/api/auth/check_session.php'));
        const data = await response.json();
        if (data.status === 'success' && data.role === 'customer') {
            const user = { username: data.username, role: data.role };
            localStorage.setItem('currentUser', JSON.stringify(user));
        } else {
            localStorage.removeItem('currentUser');
        }
        applyUserRole();
    } catch (error) {
        console.error('Session check failed:', error);
    }
}

// Tác dụng: Hiển thị thanh trạng thái người dùng tại giao diện khách hàng (chào tên khách hàng nếu đã đăng nhập hoặc hiển thị nút Đăng ký / Đăng nhập)
function applyUserRole() {
    const userJson = localStorage.getItem('currentUser');
    const authContainer = document.getElementById('auth-container');
    const adminFooterLink = document.getElementById('admin-footer-link');

    if (adminFooterLink) {
        adminFooterLink.style.display = 'none';
    }

    if (userJson) {
        try {
            const user = JSON.parse(userJson);
            if (user && user.role === 'customer') {
                if (authContainer) {
                    authContainer.innerHTML = `
                        <span style="font-weight: bold; color: var(--navy);">Xin chào, ${user.username}</span> | 
                        <span class="auth-btn logout-btn" style="cursor: pointer;">Đăng xuất</span>
                    `;
                }
                return;
            }
        } catch (e) {
            localStorage.removeItem('currentUser');
        }
    }

    // Hiển thị nút đăng ký / đăng nhập mặc định cho khách vãng lai
    if (authContainer) {
        authContainer.innerHTML = `
            <span class="auth-btn register-trigger" style="cursor: pointer;">Đăng ký</span> / 
            <span class="auth-btn login-trigger" style="cursor: pointer;">Đăng nhập</span>
        `;
    }
}

// ==================== MENU TOGGLE ====================
function initMenuToggle() {
    const menuToggle = document.querySelector('.menu-toggle');
    const menuContainer = document.querySelector('.menu-container');

    if (!menuToggle || !menuContainer) return;

    menuToggle.addEventListener('click', () => {
        menuContainer.classList.toggle('active');
    });
}

// ==================== HOVER EFFECTS ====================
function initHoverEffects() {
    const menuItems = document.querySelectorAll('.menu-item');

    menuItems.forEach(item => {
        item.addEventListener('mouseenter', function () {
            this.style.borderBottom = '3px solid var(--gold)';
            this.style.paddingBottom = '3px';
        });

        item.addEventListener('mouseleave', function () {
            this.style.borderBottom = 'none';
            this.style.paddingBottom = '6px';
        });
    });
}

// ==================== ANIMATIONS ====================
function addPulseAnimation() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.2);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease;
        }
    `;
    document.head.appendChild(style);
}

// ==================== LAZY LOADING IMAGES ====================
function initLazyLoading() {
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    observer.unobserve(img);
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
}

// ==================== SMOOTH SCROLL ====================
function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            if (href !== '#') {
                e.preventDefault();
                const target = document.querySelector(href);
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            }
        });
    });
}

// ==================== STICKY HEADER ====================
function initStickyHeader() {
    const header = document.querySelector('.header');
    const menu = document.querySelector('.menu');

    if (header && menu) {
        const wrapper = document.createElement('div');
        wrapper.className = 'sticky-header-wrapper';
        header.parentNode.insertBefore(wrapper, header);
        wrapper.appendChild(header);
        wrapper.appendChild(menu);

        if (document.body.classList.contains('home-page')) {
            const spacer = document.createElement('div');
            spacer.className = 'sticky-header-spacer';
            wrapper.parentNode.insertBefore(spacer, wrapper.nextSibling);
            const updateSpacer = () => {
                window.requestAnimationFrame(() => {
                    spacer.style.height = `${wrapper.getBoundingClientRect().height}px`;
                });
            };
            updateSpacer();
            if ('ResizeObserver' in window) {
                new ResizeObserver(updateSpacer).observe(wrapper);
            }
            wrapper.querySelectorAll('img').forEach(image => {
                image.addEventListener('load', updateSpacer, { once: true });
            });
            window.addEventListener('resize', updateSpacer);
        }
    }
}

// ==================== CATEGORY FILTERS & SORTING (SHOPEE STYLE) ====================
// Tác dụng: Quản lý logic lọc sản phẩm đa tiêu chí và sắp xếp phía Client không reload trang theo phong cách Shopee
function initCategoryFilters() {
    const productGrid = document.querySelector('.category-grid');
    if (!productGrid) return;

    // Các phần tử lọc giá
    const priceFromInput = document.getElementById('filter-price-from') || document.querySelectorAll('.price-filter input')[0];
    const priceToInput = document.getElementById('filter-price-to') || document.querySelectorAll('.price-filter input')[1];
    const applyBtn = document.getElementById('btn-apply-filter') || document.querySelector('.apply-filter-btn');
    const resetBtn = document.getElementById('btn-reset-all-filters');
    const priceChips = document.querySelectorAll('.price-chip');

    // Các checkbox tình trạng & ưu đãi
    const inStockCb = document.getElementById('filter-in-stock');
    const discountCb = document.getElementById('filter-has-discount');
    const giftCb = document.getElementById('filter-has-gift');

    // Thanh tag tiêu chí đang lọc & nút xóa tất cả
    const activeFilterBar = document.getElementById('active-filter-bar');
    const activeFilterTagsList = document.getElementById('active-filter-tags-list');
    const clearTagsBtn = document.getElementById('btn-clear-tags');

    // Khối rỗng khi không có kết quả
    const emptyState = document.getElementById('filter-empty-state');
    const emptyResetBtn = document.getElementById('btn-empty-reset');

    // Thanh sắp xếp Shopee
    const sortBtns = document.querySelectorAll('.shopee-sort-btn[data-sort]');
    const priceDropdown = document.getElementById('shopee-sort-price-dropdown');
    const priceDropdownToggle = document.getElementById('price-dropdown-toggle');
    const priceDropdownLabel = priceDropdownToggle?.querySelector('.price-dropdown-label');
    const priceDropdownItems = document.querySelectorAll('.price-dropdown-item');
    const legacySortSelect = document.querySelector('.sorting-options select') || document.getElementById('product-sort');

    let currentSort = 'popular'; // 'popular' | 'newest' | 'best_selling' | 'price_asc' | 'price_desc'

    // Hàm tiện ích: Làm sạch và chuẩn hóa số tiền từ chuỗi
    function parseVndNumber(val) {
        if (!val) return null;
        const cleaned = String(val).replace(/[^\d]/g, '');
        if (!cleaned) return null;
        let num = parseInt(cleaned, 10);
        return isNaN(num) ? null : num;
    }

    // Hàm tiện ích: Định dạng số tiền kiểu VNĐ (ví dụ 500000 -> 500.000)
    function formatVndNumber(num) {
        if (num === null || num === undefined || isNaN(num)) return '';
        return Number(num).toLocaleString('vi-VN');
    }

    // Tự động định dạng số tiền khi người dùng gõ vào ô nhập giá
    function bindInputFormatter(input) {
        if (!input) return;
        input.addEventListener('input', () => {
            const raw = parseVndNumber(input.value);
            input.value = raw !== null ? formatVndNumber(raw) : '';
            // Bỏ kích hoạt chips khi gõ tay tự do
            priceChips.forEach(chip => chip.classList.remove('active'));
        });
    }
    bindInputFormatter(priceFromInput);
    bindInputFormatter(priceToInput);

    // Xử lý click chọn nhanh khoảng giá dạng Chips/Pills
    priceChips.forEach(chip => {
        chip.addEventListener('click', () => {
            const wasActive = chip.classList.contains('active');
            priceChips.forEach(c => c.classList.remove('active'));

            if (wasActive) {
                if (priceFromInput) priceFromInput.value = '';
                if (priceToInput) priceToInput.value = '';
            } else {
                chip.classList.add('active');
                const minVal = chip.getAttribute('data-min');
                const maxVal = chip.getAttribute('data-max');

                if (priceFromInput) {
                    priceFromInput.value = minVal && minVal !== '0' ? formatVndNumber(minVal) : (minVal === '0' ? '0' : '');
                }
                if (priceToInput) {
                    priceToInput.value = maxVal ? formatVndNumber(maxVal) : '';
                }
            }
            applyFilters();
        });
    });

    // Lắng nghe thay đổi các checkbox ưu đãi & tình trạng kho
    [inStockCb, discountCb, giftCb].forEach(cb => {
        if (cb) {
            cb.addEventListener('change', applyFilters);
        }
    });

    // Nút Áp dụng khoảng giá
    if (applyBtn) {
        applyBtn.addEventListener('click', applyFilters);
    }

    // Đưa toàn bộ bộ lọc về trạng thái ban đầu
    function resetAllFilters() {
        if (priceFromInput) priceFromInput.value = '';
        if (priceToInput) priceToInput.value = '';
        priceChips.forEach(c => c.classList.remove('active'));
        if (inStockCb) inStockCb.checked = false;
        if (discountCb) discountCb.checked = false;
        if (giftCb) giftCb.checked = false;
        applyFilters();
    }

    if (resetBtn) resetBtn.addEventListener('click', resetAllFilters);
    if (clearTagsBtn) clearTagsBtn.addEventListener('click', resetAllFilters);
    if (emptyResetBtn) emptyResetBtn.addEventListener('click', resetAllFilters);

    // ================= SẮP XẾP PHONG CÁCH SHOPEE =================
    sortBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            sortBtns.forEach(b => b.classList.remove('active'));
            priceDropdownToggle?.classList.remove('active');
            if (priceDropdownLabel) priceDropdownLabel.textContent = 'Giá';
            priceDropdownItems.forEach(i => i.classList.remove('active'));

            btn.classList.add('active');
            currentSort = btn.getAttribute('data-sort') || 'popular';
            applySorting();
        });
    });

    // Toggle dropdown giá
    if (priceDropdownToggle && priceDropdown) {
        priceDropdownToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = priceDropdown.classList.toggle('open');
            priceDropdownToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        document.addEventListener('click', (e) => {
            if (!priceDropdown.contains(e.target)) {
                priceDropdown.classList.remove('open');
                priceDropdownToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Chọn kiểu sắp xếp giá trong dropdown
    priceDropdownItems.forEach(item => {
        item.addEventListener('click', () => {
            sortBtns.forEach(b => b.classList.remove('active'));
            priceDropdownToggle?.classList.add('active');
            if (priceDropdownLabel) {
                priceDropdownLabel.textContent = item.textContent.trim();
            }
            priceDropdownItems.forEach(i => i.classList.remove('active'));
            item.classList.add('active');

            currentSort = item.getAttribute('data-sort') || 'price_asc';
            priceDropdown?.classList.remove('open');
            priceDropdownToggle?.setAttribute('aria-expanded', 'false');
            applySorting();
        });
    });

    // Hỗ trợ thẻ select sắp xếp cũ (nếu có trên trang khác)
    if (legacySortSelect) {
        legacySortSelect.addEventListener('change', () => {
            const val = legacySortSelect.value;
            if (val === 'Giá từ thấp đến cao') currentSort = 'price_asc';
            else if (val === 'Giá từ cao đến thấp') currentSort = 'price_desc';
            else if (val === 'Bán chạy nhất') currentSort = 'best_selling';
            else if (val === 'Mới cập nhật') currentSort = 'newest';
            else currentSort = 'popular';
            applySorting();
        });
    }

    // ================= HÀM LỌC SẢN PHẨM (APPLY FILTERS) =================
    function applyFilters() {
        const minVal = parseVndNumber(priceFromInput?.value);
        const maxVal = parseVndNumber(priceToInput?.value);
        const reqInStock = inStockCb ? inStockCb.checked : false;
        const reqDiscount = discountCb ? discountCb.checked : false;
        const reqGift = giftCb ? giftCb.checked : false;

        const allCards = Array.from(productGrid.querySelectorAll('.product-card'));
        let matchCount = 0;

        allCards.forEach(card => {
            const cardPrice = Number(card.getAttribute('data-price')) || parsePrice(card.querySelector('.price-sale')?.textContent) || 0;
            const cardStock = card.hasAttribute('data-stock')
                ? parseInt(card.getAttribute('data-stock'), 10)
                : (card.querySelector('.product-stock')?.textContent.includes('Hết hàng') ? 0 : 10);

            const cardDiscount = card.getAttribute('data-discount') === '1'
                || Boolean(card.querySelector('.product-badge'))
                || Boolean(card.querySelector('.price-original'));

            const cardGift = card.getAttribute('data-gift') === '1'
                || Boolean(card.querySelector('.product-gift'));

            let passPrice = true;
            if (minVal !== null && cardPrice < minVal) passPrice = false;
            if (maxVal !== null && cardPrice > maxVal) passPrice = false;

            let passStock = !reqInStock || (cardStock > 0);
            let passDiscount = !reqDiscount || cardDiscount;
            let passGift = !reqGift || cardGift;

            if (passPrice && passStock && passDiscount && passGift) {
                card.style.display = '';
                matchCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // Ẩn/Hiện khối Empty State
        if (emptyState) {
            emptyState.style.display = (matchCount === 0) ? 'block' : 'none';
        }

        // Vẽ lại thanh tags tiêu chí đang lọc độc lập (không dính chữ)
        renderActiveFilterBadges(minVal, maxVal, reqInStock, reqDiscount, reqGift);

        // Áp dụng sắp xếp lại cho các sản phẩm đang hiển thị
        applySorting();
    }

    // ================= VẼ CÁC PILL BADGES TIÊU CHÍ ĐANG LỌC =================
    // Tác dụng: Khắc phục triệt để lỗi dính chữ, mỗi tiêu chí là một tag riêng biệt có nút (×) để xóa từng điều kiện
    function renderActiveFilterBadges(minVal, maxVal, reqInStock, reqDiscount, reqGift) {
        if (!activeFilterBar || !activeFilterTagsList) return;

        const badges = [];

        if (minVal !== null && maxVal !== null) {
            badges.push({ type: 'price', label: `${formatVndNumber(minVal)}₫ - ${formatVndNumber(maxVal)}₫` });
        } else if (minVal !== null) {
            badges.push({ type: 'price', label: `Từ ${formatVndNumber(minVal)}₫` });
        } else if (maxVal !== null) {
            badges.push({ type: 'price', label: `Đến ${formatVndNumber(maxVal)}₫` });
        }

        if (reqInStock) {
            badges.push({ type: 'stock', label: 'Còn hàng' });
        }
        if (reqDiscount) {
            badges.push({ type: 'discount', label: 'Đang giảm giá' });
        }
        if (reqGift) {
            badges.push({ type: 'gift', label: 'Có quà tặng' });
        }

        if (badges.length === 0) {
            activeFilterBar.style.display = 'none';
            activeFilterTagsList.innerHTML = '';
            return;
        }

        activeFilterBar.style.display = 'flex';
        activeFilterTagsList.innerHTML = badges.map(b => `
            <span class="filter-pill-badge" data-badge-type="${b.type}">
                <span>${b.label}</span>
                <button type="button" class="pill-remove-btn" data-remove="${b.type}" title="Xóa bộ lọc này">×</button>
            </span>
        `).join('');

        // Bắt sự kiện xóa lẻ từng tag điều kiện
        activeFilterTagsList.querySelectorAll('.pill-remove-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const removeType = btn.getAttribute('data-remove');
                if (removeType === 'price') {
                    if (priceFromInput) priceFromInput.value = '';
                    if (priceToInput) priceToInput.value = '';
                    priceChips.forEach(c => c.classList.remove('active'));
                } else if (removeType === 'stock') {
                    if (inStockCb) inStockCb.checked = false;
                } else if (removeType === 'discount') {
                    if (discountCb) discountCb.checked = false;
                } else if (removeType === 'gift') {
                    if (giftCb) giftCb.checked = false;
                }
                applyFilters();
            });
        });
    }

    // ================= SẮP XẾP SẢN PHẨM THEO TIÊU CHÍ =================
    function applySorting() {
        const visibleProducts = Array.from(productGrid.querySelectorAll('.product-card'));
        if (visibleProducts.length <= 1) return;

        visibleProducts.sort((a, b) => {
            const priceA = Number(a.getAttribute('data-price')) || parsePrice(a.querySelector('.price-sale')?.textContent) || 0;
            const priceB = Number(b.getAttribute('data-price')) || parsePrice(b.querySelector('.price-sale')?.textContent) || 0;
            const soldA = Number(a.getAttribute('data-sold')) || 0;
            const soldB = Number(b.getAttribute('data-sold')) || 0;
            const idA = Number(a.getAttribute('data-db-id')) || 0;
            const idB = Number(b.getAttribute('data-db-id')) || 0;

            if (currentSort === 'price_asc') return priceA - priceB;
            if (currentSort === 'price_desc') return priceB - priceA;
            if (currentSort === 'newest') return idB - idA;
            if (currentSort === 'best_selling') return soldB - soldA;

            // 'popular' (mặc định): ưu tiên sản phẩm bán chạy, sau đó tới mới nhất
            if (soldB !== soldA) return soldB - soldA;
            return idB - idA;
        });

        visibleProducts.forEach(card => {
            productGrid.appendChild(card);
        });
    }

    // Quản lý nút "Xem thêm" toàn cục (nếu có)
    const loadMoreBtn = document.getElementById('load-more-btn');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function () {
            const extraProducts = document.querySelectorAll('.extra-product');
            if (extraProducts.length === 0) return;

            const isHidden = extraProducts[0].style.display === 'none';
            extraProducts.forEach(prod => {
                prod.style.display = isHidden ? 'block' : 'none';
            });
            loadMoreBtn.textContent = isHidden ? 'Thu gọn' : 'Xem thêm';
        });
    }
}

// ==================== DYNAMIC CATEGORY PAGES ====================
async function initDynamicCategoryPages() {
    const container = document.getElementById('category-products-container');
    if (!container) return;

    const urlParams = new URLSearchParams(window.location.search);
    const searchQuery = urlParams.get('search');
    if (searchQuery) {
        const searchSection = document.getElementById('search-results-section');
        if (searchSection) searchSection.hidden = false;
        const searchInput = document.querySelector('.search');
        if (searchInput) searchInput.value = searchQuery;

        try {
            const response = await fetch(appUrl('/api/catalog/get_products.php'));
            if (!response.ok) throw new Error('Không thể tải danh sách sản phẩm để tìm kiếm.');
            const products = await response.json();
            const normalizedQuery = searchQuery.trim().toLowerCase();
            const filteredProducts = products.filter(product =>
                product.name && product.name.toLowerCase().includes(normalizedQuery)
            );

            renderDbProducts(filteredProducts, container);
            const pageTitle = document.querySelector('.page-title-left');
            if (pageTitle) pageTitle.textContent = `KẾT QUẢ TÌM KIẾM: "${searchQuery.toUpperCase()}"`;
            applyUserRole();
        } catch (error) {
            console.error('Lỗi khi tìm kiếm sản phẩm:', error);
            container.innerHTML = '<p class="empty-cart" style="grid-column: 1/-1; text-align: center;">Không tìm kiếm được sản phẩm. Vui lòng kiểm tra kết nối cơ sở dữ liệu.</p>';
        }
        return;
    }

    const dbCategory = container.getAttribute('data-db-category');
    if (!dbCategory) return;

    console.log(`Loading dynamic products for category: ${dbCategory}...`);
    try {
        const response = await fetch(`${appUrl('/api/catalog/get_products.php')}?category=${encodeURIComponent(dbCategory)}`);
        if (!response.ok) throw new Error('Network response was not ok');
        const dbProducts = await response.json();

        if (dbProducts && dbProducts.length > 0) {
            console.log(`Loaded ${dbProducts.length} products from database. Rendering dynamically...`);

            renderDbProducts(dbProducts, container);

            // Hiển thị hay ẩn nút "Xem thêm" được xử lý tự động trong renderDbProducts tùy theo số sản phẩm thực tế từ DB

            // Sau khi render xong các thẻ động, cập nhật lại hiển thị nút "Chỉnh sửa sản phẩm" dựa trên quyền Admin
            applyUserRole();
        } else {
            container.innerHTML = '<p class="empty-category-message">Danh mục hiện chưa có sản phẩm.</p>';
        }
    } catch (error) {
        console.error("Lỗi khi tải sản phẩm động:", error);
        container.innerHTML = '<p class="empty-cart" style="grid-column: 1/-1; text-align: center;">Không tải được sản phẩm. Vui lòng kiểm tra kết nối cơ sở dữ liệu.</p>';
    }
}

// ==================== INITIALIZE ALL ====================
document.addEventListener('DOMContentLoaded', async () => {
    console.log('🚀 Website đã sẵn sàng!');

    addPulseAnimation();
    initStickyHeader();
    initSlideshow();
    initComboSlider();
    initScrollToTop();
    initProductDetailsNavigation();
    initAddToCart();
    initCheckoutPage();
    initSearch();
    initTabs();
    initAuth();
    initMenuToggle();
    initHoverEffects();
    initLazyLoading();
    initSmoothScroll();

    // Tải động sản phẩm danh mục trước khi gắn bộ lọc
    await initDynamicCategoryPages();
    initCategoryFilters();

    console.log('✅ Tất cả các tính năng đã được khởi tạo!');
});

// ==================== VIEWPORT CHECK ====================
window.addEventListener('load', () => {
    console.log('📱 Window width:', window.innerWidth);
    console.log('📱 Window height:', window.innerHeight);
});

// ==================== DEBUG MODE ====================
const isDebugMode = false; // Thay đổi thành true để bật debug mode

if (isDebugMode) {
    console.log('🐛 DEBUG MODE ON');
    window.addEventListener('scroll', () => {
        console.log('Scroll Y:', window.scrollY);
    });
}

// ==================== TAB SYNC ====================
window.addEventListener('storage', (e) => {
    if (e.key === 'cart') {
        loadCart();
        updateCartCount();
        renderCart();
    }
});
