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
    if (/^https?:\/\//i.test(trimmed)) return trimmed;
    const clean = trimmed.replace(/^(?:\.\.\/)+/, '');
    const cleanPath = clean.startsWith('/') ? clean : '/' + clean;
    const v = window.ASSET_VERSION || Date.now();
    const sep = cleanPath.includes('?') ? '&' : '?';
    return appUrl(cleanPath) + sep + 'v=' + v;
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
    if (reviewCount <= 0 || !Number.isFinite(averageRating)) {
        return '<div class="product-rating product-rating--empty" aria-hidden="true"></div>';
    }

    return `
        <div class="product-rating" aria-label="Đánh giá ${averageRating.toFixed(1)} trên 5 sao, ${reviewCount} lượt">
            <span class="product-rating-star" aria-hidden="true">★</span>
            <span>${averageRating.toFixed(1)}</span>
            <span class="product-rating-count">(${reviewCount})</span>
        </div>
    `;
}

function renderProductMeta(soldCount, wishlistCount) {
    return '<div class="product-meta"><span class="product-meta-empty" aria-hidden="true"></span></div>';
}

function getProductQuantityControlMarkup(stock, name) {
    const maxQuantity = Math.min(stock, 99);
    const disabled = maxQuantity <= 0 ? ' disabled' : '';
    return `
        <div class="product-quantity-control" role="group" aria-label="Chọn số lượng ${escapeHtml(name)}">
            <button type="button" class="product-quantity-decrease" aria-label="Giảm số lượng"${disabled}>−</button>
            <input class="product-quantity" type="number" min="1" max="${Math.max(maxQuantity, 1)}" value="1" inputmode="numeric" aria-label="Số lượng ${escapeHtml(name)}"${disabled}>
            <button type="button" class="product-quantity-increase" aria-label="Tăng số lượng"${disabled}>+</button>
        </div>
    `;
}

function createSliderItems(container, products) {
    products.forEach(product => {
        const rawOrig = product.priceOriginal || product.price_original;
        const rawSale = product.priceSale || product.price_sale;
        const origVal = rawOrig ? parsePrice(rawOrig) : 0;
        const saleVal = rawSale ? parsePrice(rawSale) : 0;
        let calcPercent = 0;
        if (origVal > saleVal && origVal > 0) {
            calcPercent = Math.round(((origVal - saleVal) / origVal) * 100);
        }
        const discountText = (product.discount && product.discount.trim() !== '')
            ? product.discount
            : (calcPercent > 0 ? `-${calcPercent}%` : '');
        const discountHTML = discountText ? `<div class="product-discount">${discountText}</div>` : '';
        const imagePath = typeof product.image === 'string' ? product.image : '';
        const imageUrl = getProductImageUrl(imagePath);

        const stock = Number(product.stock) || 0;
        const soldCount = Number(product.soldCount) || 0;
        const wishlistCount = Number(product.wishlistCount) || 0;

        const metaHTML = renderProductMeta(soldCount, wishlistCount);
        const percentTagHTML = calcPercent > 0 ? `<span class="price-discount-percent">-${calcPercent}%</span>` : '';

        const itemHTML = `
            <div class="product-card" data-db-id="${product.id || ''}" data-stock="${stock}" data-category="${product.category || ''}" data-detail-url="${appUrl('/client/san_pham.php')}?id=${encodeURIComponent(product.id || '')}" role="link" tabindex="0" aria-label="Xem chi tiết sản phẩm">
                ${discountHTML}
                <button type="button" class="wishlist-btn${(typeof isProductFavorited === 'function' && isProductFavorited(product.id, product.is_favorite)) ? ' active' : ''}" data-product-id="${product.id || ''}" title="${(typeof isProductFavorited === 'function' && isProductFavorited(product.id, product.is_favorite)) ? 'Bỏ yêu thích' : 'Yêu thích'}" aria-label="Yêu thích">${(typeof isProductFavorited === 'function' && isProductFavorited(product.id, product.is_favorite)) ? '♥' : '♡'}</button>
                <img src="${imageUrl}" alt="${product.name}" class="product-image" onerror="this.onerror=null;this.src='${appUrl('/assets/banner/logo.png')}';">
                <div class="product-info">
                    <h3>${product.name}</h3>
                    <div class="product-price">
                        <span class="price-sale">${product.priceSale || formatDbPrice(product.price_sale)}<sup>đ</sup></span>
                        ${origVal > saleVal && origVal > 0 ? `<span class="price-original">${product.priceOriginal || formatDbPrice(product.price_original)}<sup>đ</sup></span>${percentTagHTML}` : ''}
                    </div>
                    ${metaHTML}
                    ${renderProductRating(product)}
                    <div class="product-purchase-controls">
                        ${getProductQuantityControlMarkup(stock, product.name)}
                        <div class="product-card-actions">
                            <button type="button" class="add-to-cart"${stock <= 0 ? ' disabled' : ''}>${stock <= 0 ? 'Tạm hết hàng' : 'Thêm vào giỏ'}</button>
                            <button type="button" class="buy-now"${stock <= 0 ? ' disabled' : ''}>Mua ngay</button>
                        </div>
                    </div>
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
        const emptyMessages = {
            'featured-items-container': 'Chưa có sản phẩm bán thành công trong 30 ngày qua.',
            'suggest-items-container': 'Chưa có đủ dữ liệu mua hoặc yêu thích để gợi ý sản phẩm.',
            'new-items-container': 'Chưa có sản phẩm mới được ghi nhận thời điểm tạo.',
        };
        container.innerHTML = `<p class="home-empty-state">${emptyMessages[container.id] || 'Chưa có sản phẩm phù hợp.'}</p>`;
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

async function fetchHomepageSections() {
    try {
        const response = await fetch(`${appUrl('/api/catalog/lay_san_pham.php')}?category=home`);
        if (!response.ok) throw new Error('Không thể tải dữ liệu nhóm sản phẩm trang chủ.');
        const data = await response.json();
        const sectionKeys = ['new', 'featured', 'suggest'];
        if (!sectionKeys.every(key => Array.isArray(data[key]))) {
            throw new Error('Dữ liệu nhóm sản phẩm trang chủ không hợp lệ.');
        }
        return data;
    } catch (error) {
        console.error('Lỗi khi tải các nhóm sản phẩm trang chủ:', error);
        ['featured-items-container', 'suggest-items-container', 'new-items-container'].forEach(containerId => {
            const container = document.getElementById(containerId);
            if (container) container.dataset.loadError = 'true';
        });
        return { new: [], featured: [], suggest: [] };
    }
}

async function initComboSlider() {
    const sections = await fetchHomepageSections();
    initProductSlider('featured-items-container', 'featured-prev', 'featured-next', sections.featured, []);
    initProductSlider('suggest-items-container', 'suggest-prev', 'suggest-next', sections.suggest, []);
    initProductSlider('new-items-container', 'new-prev', 'new-next', sections.new, []);
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

// ==================== THÊM VÀO GIỎ HÀNG ====================
let cart = [];
let cartAvailabilityState = 'unchecked';
const DIRECT_CHECKOUT_KEY = 'direct_checkout_product';
let currentCustomerUsername = typeof window.CUSTOMER_USERNAME === 'string'
    ? window.CUSTOMER_USERNAME
    : null;

function getCurrentCustomer() {
    return currentCustomerUsername;
}

function getCartKey() {
    const username = getCurrentCustomer();
    return username ? 'cart_' + username : null;
}

function loadCart() {
    const key = getCartKey();
    if (!key) {
        cart = [];
        return;
    }

    try {
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
    const key = getCartKey();
    if (!key) return;

    try {
        localStorage.setItem(key, JSON.stringify(cart));
    } catch (e) {
        console.error('Error saving cart:', e);
    }
}

// Gọi loadCart ngay lập tức
loadCart();

function parsePrice(priceText) {
    if (priceText === null || priceText === undefined) return 0;
    if (typeof priceText === 'number') return isNaN(priceText) ? 0 : priceText;
    return Number(String(priceText).replace(/[^\d]/g, '')) || 0;
}

function formatPrice(value) {
    return value.toLocaleString('vi-VN') + ' đ';
}

function updateCartCount() {
    const cartCountElements = document.querySelectorAll('.cart-count');
    const isCustomerLoggedIn = Boolean(getCurrentCustomer());
    const totalItems = isCustomerLoggedIn
        ? cart.reduce((sum, item) => sum + item.quantity, 0)
        : 0;

    cartCountElements.forEach(el => {
        el.textContent = isCustomerLoggedIn ? totalItems : '';
        el.style.display = isCustomerLoggedIn ? '' : 'none';
        el.setAttribute('aria-hidden', isCustomerLoggedIn ? 'false' : 'true');
        el.classList.toggle('has-items', totalItems > 0);
    });
}

function promptCustomerLogin() {
    document.getElementById('register-modal')?.classList.remove('active');
    const loginModal = document.getElementById('login-modal');
    if (loginModal) {
        loginModal.classList.add('active');
        return;
    }
    window.location.assign(`${appUrl('/index.php')}?login_required=cart`);
}

function renderCart() {
    const cartItemsContainer = document.querySelector('.cart-items');
    const cartTotalPrices = document.querySelectorAll('.cart-total-price');
    const cartTotalCount = document.querySelector('.cart-total-count');
    const selectAll = document.querySelector('.cart-select-all');
    const checkoutButton = document.querySelector('.checkout-btn');

    if (!cartItemsContainer || cartTotalPrices.length === 0 || !cartTotalCount) return;

    if (cartAvailabilityState !== 'verified') {
        const statusText = cartAvailabilityState === 'checking'
            ? 'Đang kiểm tra tình trạng sản phẩm trong giỏ hàng...'
            : 'Không thể xác minh sản phẩm. Vui lòng tải lại trang.';
        cartItemsContainer.innerHTML = `<p class="empty-cart" role="status">${statusText}</p>`;
        cartTotalPrices.forEach(element => { element.textContent = '0 đ'; });
        cartTotalCount.textContent = cartAvailabilityState === 'checking'
            ? 'Đang kiểm tra sản phẩm...'
            : 'Chưa thể xác nhận sản phẩm trong giỏ hàng';
        if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
            selectAll.disabled = true;
        }
        if (checkoutButton) checkoutButton.disabled = true;
        return;
    }

    if (cart.length === 0) {
        cartItemsContainer.innerHTML = '<p class="empty-cart">Giỏ hàng đang trống. Thêm sản phẩm nào đó nhé!</p>';
        cartTotalPrices.forEach(element => { element.textContent = '0 đ'; });
        cartTotalCount.textContent = 'Đã chọn 0 / 0 sản phẩm';
        if (selectAll) {
            selectAll.checked = false;
            selectAll.indeterminate = false;
            selectAll.disabled = false;
        }
        if (checkoutButton) checkoutButton.disabled = true;
        return;
    }

    cartItemsContainer.innerHTML = '';
    const selectedItems = cart.filter(item => item.selected !== false);
    const selectedQuantitiesByProduct = new Map();
    selectedItems.forEach(item => {
        const productId = Number(item.product_id);
        if (Number.isInteger(productId) && productId > 0) {
            selectedQuantitiesByProduct.set(
                productId,
                (selectedQuantitiesByProduct.get(productId) || 0) + Number(item.quantity)
            );
        }
    });
    const hasSelectedStockIssue = selectedItems.some(item => {
        const productId = Number(item.product_id);
        const stock = Number(item.stock);
        const selectedProductQuantity = selectedQuantitiesByProduct.get(productId) || 0;
        return !Number.isInteger(stock)
            || stock <= 0
            || selectedProductQuantity > stock;
    });
    const totalPrice = selectedItems.reduce((sum, item) => sum + item.price * item.quantity, 0);
    const selectedQty = selectedItems.reduce((sum, item) => sum + item.quantity, 0);
    const totalQty = cart.reduce((sum, item) => sum + item.quantity, 0);

    cart.forEach((item, index) => {
        const imageUrl = escapeHtml(getProductImageUrl(item.image));
        const stock = Number(item.stock);
        const productId = Number(item.product_id);
        const selectedProductQuantity = selectedQuantitiesByProduct.get(productId) || 0;
        const outOfStock = stock <= 0;
        const quantityExceedsStock = !outOfStock && selectedProductQuantity > stock;
        const stockMessage = outOfStock
            ? 'Tạm hết hàng'
            : quantityExceedsStock
                ? `Tổng số lượng đã chọn vượt tồn kho (${stock})`
                : `Còn ${stock} sản phẩm`;
        const itemMarkup = `
            <div class="cart-item" data-index="${index}">
                <label class="cart-item-select-label">
                    <input type="checkbox" class="cart-item-select" aria-label="Chọn ${escapeHtml(item.name)}" ${item.selected !== false ? 'checked' : ''} ${outOfStock ? 'disabled' : ''}>
                </label>
                <img src="${imageUrl}" alt="${escapeHtml(item.name)}" class="cart-item-image" onerror="this.onerror=null;this.src='${appUrl('/assets/banner/logo.png')}';">
                <div class="cart-item-details">
                    <p class="cart-item-name">${escapeHtml(item.name)}</p>
                    <p class="cart-item-stock${outOfStock || quantityExceedsStock ? ' unavailable' : ''}">${stockMessage}</p>
                    <div class="cart-item-actions">
                        <div class="cart-qty-controls">
                            <button class="qty-minus" data-action="decrease" ${item.quantity <= 1 ? 'disabled' : ''}>−</button>
                            <span class="cart-qty">${item.quantity}</span>
                            <button class="qty-plus" data-action="increase" ${outOfStock || selectedProductQuantity >= stock || item.quantity >= 99 ? 'disabled' : ''}>+</button>
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
        selectAll.disabled = false;
    }
    if (checkoutButton) {
        checkoutButton.disabled = cartAvailabilityState !== 'verified'
            || selectedItems.length === 0
            || hasSelectedStockIssue;
    }
}

async function refreshCartAvailability() {
    const cartItemsContainer = document.querySelector('.cart-items');
    if (!cartItemsContainer) return;

    loadCart();
    cartAvailabilityState = 'checking';
    renderCart();

    try {
        const productIds = [...new Set(cart
            .map(item => Number(item.product_id))
            .filter(id => Number.isInteger(id) && id > 0))];
        const availableProducts = new Map();

        for (let offset = 0; offset < productIds.length; offset += 100) {
            const ids = productIds.slice(offset, offset + 100);
            const params = new URLSearchParams({ ids: ids.join(',') });
            const response = await fetch(`${appUrl('/api/catalog/lay_san_pham.php')}?${params}`, { cache: 'no-store' });
            if (!response.ok) throw new Error('Không thể xác minh sản phẩm trong giỏ hàng.');

            const products = await response.json();
            if (!Array.isArray(products)) throw new Error('Phản hồi kiểm tra sản phẩm không hợp lệ.');
            products.forEach(product => {
                const id = Number(product.id);
                const stock = Number(product.stock);
                if (!Number.isInteger(id) || id < 1 || !Number.isInteger(stock) || stock < 0) {
                    throw new Error('Dữ liệu tồn kho sản phẩm không hợp lệ.');
                }
                availableProducts.set(id, { stock });
            });
        }

        const previousCount = cart.length;
        cart = cart.filter(item => {
            const id = Number(item.product_id);
            return Number.isInteger(id) && id > 0 && availableProducts.has(id);
        });
        const removedCount = previousCount - cart.length;
        let outOfStockCount = 0;
        cart.forEach(item => {
            const product = availableProducts.get(Number(item.product_id));
            item.stock = product.stock;
            if (product.stock === 0) {
                item.selected = false;
                outOfStockCount++;
            }
        });
        saveCart();
        cartAvailabilityState = 'verified';
        updateCartCount();
        renderCart();

        if (removedCount > 0) {
            showToast(`Đã bỏ ${removedCount} sản phẩm không còn được kinh doanh khỏi giỏ hàng.`);
        }
        if (outOfStockCount > 0) {
            showToast(`${outOfStockCount} sản phẩm đã hết hàng và không thể đặt mua.`);
        }
    } catch (error) {
        console.error('Không thể xác minh sản phẩm trong giỏ hàng:', error);
        cartAvailabilityState = 'failed';
        renderCart();
    }
}

function addItemToCart(product) {
    if (!getCurrentCustomer()) {
        promptCustomerLogin();
        return false;
    }

    loadCart(); // Đồng bộ dữ liệu mới nhất

    if (!product || !product.name) {
        console.error('Sản phẩm không hợp lệ:', product);
        return false;
    }
    const quantity = Number(product.quantity) || 1;
    const availableStock = Number(product.stock);
    if (!Number.isInteger(quantity) || quantity < 1 || quantity > 99) {
        showToast('Số lượng sản phẩm không hợp lệ.');
        return false;
    }
    if (!Number.isInteger(availableStock) || availableStock <= 0) {
        showToast('Sản phẩm hiện đã hết hàng.');
        return false;
    }

    const existingProduct = cart.find(item =>
        product.product_id
            ? Number(item.product_id) === Number(product.product_id) && item.name === product.name
            : item.name === product.name
    );

    const nextQuantity = (existingProduct ? Number(existingProduct.quantity) : 0) + quantity;
    const currentProductQuantity = product.product_id
        ? cart.reduce((sum, item) => (
            Number(item.product_id) === Number(product.product_id) ? sum + Number(item.quantity) : sum
        ), 0)
        : (existingProduct ? Number(existingProduct.quantity) : 0);
    if (nextQuantity > 99 || currentProductQuantity + quantity > Math.min(99, availableStock)) {
        showToast('Số lượng vượt quá sản phẩm còn trong kho.');
        return false;
    }

    if (existingProduct) {
        existingProduct.quantity = nextQuantity;
        existingProduct.selected = true;
    } else {
        cart.push({
            product_id: product.product_id || null,
            name: product.name,
            price: product.price || 0,
            image: product.image || 'assets/banner/logo.png',
            stock: availableStock,
            quantity
        });
    }

    saveCart();
    updateCartCount();
    renderCart();
    showToast(`Đã thêm "${product.name}" vào giỏ hàng`);
    console.log('Đã thêm sản phẩm vào giỏ:', product.name);
    return true;
}

function isDirectCheckout() {
    return new URLSearchParams(window.location.search).get('direct') === '1';
}

function getDirectCheckoutItem() {
    if (!isDirectCheckout()) return null;

    try {
        const savedItem = sessionStorage.getItem(DIRECT_CHECKOUT_KEY);
        if (!savedItem) return null;
        const item = JSON.parse(savedItem);
        if (!item || !Number.isInteger(Number(item.product_id)) || Number(item.product_id) < 1
            || !Number.isInteger(Number(item.quantity)) || Number(item.quantity) < 1 || Number(item.quantity) > 99
            || !Number.isFinite(Number(item.price)) || Number(item.price) < 0
            || typeof item.name !== 'string' || item.name.trim() === '') {
            throw new Error('Dữ liệu sản phẩm mua ngay không hợp lệ.');
        }
        return item;
    } catch (error) {
        console.error('Không đọc được sản phẩm mua ngay:', error);
        return null;
    }
}

function getCheckoutItems() {
    if (isDirectCheckout()) {
        const directItem = getDirectCheckoutItem();
        return directItem ? [directItem] : [];
    }
    loadCart();
    return cart.filter(item => item.selected !== false);
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
    if (delta > 0) {
        const productId = Number(item.product_id);
        const stock = Number(item.stock);
        const cartProductQuantity = cart.reduce((sum, cartItem) => (
            Number(cartItem.product_id) === productId ? sum + Number(cartItem.quantity) : sum
        ), 0);
        if (!Number.isInteger(stock) || stock <= 0 || cartProductQuantity >= stock || item.quantity >= 99) {
            showToast(stock <= 0 ? 'Sản phẩm hiện đã hết hàng.' : 'Số lượng đã đạt giới hạn tồn kho.');
            return;
        }
    }
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
    const isCartPage = Boolean(document.querySelector('.cart-items'));
    if (isCartPage) cartAvailabilityState = 'checking';

    // Render initial state
    renderCart();
    updateCartCount();
    if (isCartPage) refreshCartAvailability();

    document.addEventListener('click', event => {
        const cartLink = event.target.closest('.cart-button');
        if (!cartLink || getCurrentCustomer()) return;

        event.preventDefault();
        promptCustomerLogin();
    });

    document.addEventListener('click', event => {
        const quantityButton = event.target.closest('.product-quantity-decrease, .product-quantity-increase');
        if (!quantityButton) return;

        const card = quantityButton.closest('.product-card');
        const quantityInput = card?.querySelector('.product-quantity');
        if (!quantityInput) return;

        const min = Number(quantityInput.min) || 1;
        const max = Number(quantityInput.max) || 99;
        const delta = quantityButton.classList.contains('product-quantity-increase') ? 1 : -1;
        const current = Number.parseInt(quantityInput.value, 10);
        quantityInput.value = String(Math.max(min, Math.min(max, (Number.isFinite(current) ? current : min) + delta)));
    });

    document.addEventListener('change', event => {
        if (!event.target.matches('.product-quantity')) return;
        const input = event.target;
        const min = Number(input.min) || 1;
        const max = Number(input.max) || 99;
        const quantity = Number.parseInt(input.value, 10);
        input.value = String(Math.max(min, Math.min(max, Number.isFinite(quantity) ? quantity : min)));
    });

    document.addEventListener('click', event => {
        const buyButton = event.target.closest('.buy-now');
        if (!buyButton) return;

        event.preventDefault();
        if (!getCurrentCustomer()) {
            promptCustomerLogin();
            return;
        }

        const card = buyButton.closest('.product-card');
        const name = card?.querySelector('h3, h1')?.textContent.trim();
        const price = card?.querySelector('.price-sale')?.textContent;
        const image = card?.querySelector('.product-image')?.src;
        const quantity = Number.parseInt(card?.querySelector('.product-quantity')?.value || '', 10);
        const productId = Number(card?.dataset.dbId);
        const stock = Number(card?.dataset.stock);
        if (!name || !Number.isInteger(productId) || productId < 1 || !Number.isInteger(quantity)
            || quantity < 1 || quantity > Math.min(stock, 99)) {
            showToast('Vui lòng kiểm tra sản phẩm và số lượng còn hàng.');
            return;
        }

        const colorSelect = card.querySelector('.color-select');
        const productName = colorSelect?.value ? `${name} - Màu: ${colorSelect.value}` : name;
        const directItem = {
            product_id: productId,
            name: productName,
            price: parsePrice(price || ''),
            image: image || appUrl('/assets/banner/logo.png'),
            quantity
        };
        try {
            sessionStorage.setItem(DIRECT_CHECKOUT_KEY, JSON.stringify(directItem));
            window.location.assign(`${appUrl('/client/dat_hang.php')}?direct=1`);
        } catch (error) {
            console.error('Không thể bắt đầu mua ngay:', error);
            showToast('Không thể bắt đầu đặt hàng. Vui lòng thử lại.');
        }
    });

    // Event Delegation for "Add to Cart" buttons
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.add-to-cart');
        if (!btn) return;

        e.preventDefault();
        if (!getCurrentCustomer()) {
            promptCustomerLogin();
            return;
        }

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
            quantity: Number.parseInt(productCard.querySelector('.product-quantity')?.value || '1', 10),
            stock: Number(productCard.dataset.stock)
        };

        console.log('Đang thêm sản phẩm:', product);
        if (!addItemToCart(product)) return;

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
        if (cartAvailabilityState === 'checking') return;
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
        if (cartAvailabilityState === 'checking') return;
        if (!event.target.matches('.cart-item-select')) return;
        const itemElement = event.target.closest('.cart-item');
        const index = itemElement ? Number(itemElement.dataset.index) : -1;
        if (index < 0 || !cart[index]) return;
        if (Number(cart[index].stock) <= 0) {
            event.target.checked = false;
            return;
        }
        cart[index].selected = event.target.checked;
        saveCart();
        renderCart();
    });

    document.querySelector('.cart-select-all')?.addEventListener('change', event => {
        if (cartAvailabilityState === 'checking') return;
        cart.forEach(item => {
            item.selected = event.target.checked && Number(item.stock) > 0;
        });
        saveCart();
        renderCart();
    });

    // Tiếp tục từ giỏ hàng sang trang đặt hàng.
    document.querySelector('.checkout-btn')?.addEventListener('click', () => {
        loadCart();
        if (cartAvailabilityState !== 'verified') {
            showToast('Chưa thể xác minh tình trạng sản phẩm. Vui lòng tải lại trang trước khi đặt hàng.');
            return;
        }
        if (!cart.some(item => item.selected !== false)) {
            alert('Vui lòng chọn ít nhất một sản phẩm để đặt hàng.');
            return;
        }

        if (!getCurrentCustomer()) {
            promptCustomerLogin();
            return;
        }

        window.location.assign(appUrl('/client/dat_hang.php'));
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
    const selectedItems = getCheckoutItems();
    if (selectedItems.length === 0) {
        container.innerHTML = isDirectCheckout()
            ? '<p class="empty-cart">Không tải được sản phẩm mua ngay. Vui lòng quay lại trang sản phẩm và thử lại.</p>'
            : '<p class="empty-cart">Chưa có sản phẩm nào được chọn. Quay lại giỏ hàng để chọn sản phẩm.</p>';
    }
    selectedItems.forEach(item => {
        const row = document.createElement('div');
        row.className = 'checkout-order-item';
        const image = document.createElement('img');
        image.src = getProductImageUrl(item.image);
        image.alt = '';
        image.onerror = () => {
            image.onerror = null;
            image.src = appUrl('/assets/banner/logo.png');
        };
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

function renderCheckoutReceipt(orderId, customer, items, total, paymentMethod = 'COD', discountAmount = '0.00', subtotal = null) {
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
        price.textContent = formatPrice(Number(item.line_total ?? ((Number(item.price ?? item.unit_price) || 0) * (Number(item.quantity) || 0))));
        row.append(name, quantity);
        if (item.product_sku) {
            const sku = document.createElement('span');
            sku.textContent = `SKU: ${item.product_sku}`;
            row.appendChild(sku);
        }
        if (item.attributes_snapshot) {
            const attributes = document.createElement('span');
            attributes.textContent = item.attributes_snapshot;
            row.appendChild(attributes);
        }
        if (item.category_name_snapshot) {
            const category = document.createElement('span');
            category.textContent = `Phân loại: ${item.category_name_snapshot}`;
            row.appendChild(category);
        }
        if (Number(item.discount_amount) > 0) {
            const discount = document.createElement('span');
            discount.textContent = `Giảm: ${formatPrice(item.discount_amount)}`;
            row.appendChild(discount);
        }
        row.appendChild(price);
        receiptItems.appendChild(row);
    });
    const receiptSubtotal = success.querySelector('.checkout-receipt-subtotal');
    if (receiptSubtotal) {
        receiptSubtotal.textContent = formatPrice(Number(subtotal ?? total) || 0);
    }
    success.querySelector('.checkout-receipt-total').textContent = formatPrice(Number(total) || 0);
    const receiptDiscount = success.querySelector('.checkout-receipt-discount');
    if (receiptDiscount) receiptDiscount.textContent = `−${formatPrice(Number(discountAmount) || 0)}`;
    const receiptPayment = success.querySelector('.checkout-receipt-payment');
    if (paymentMethod === 'BANK_QR') {
        receiptPayment.textContent = 'Chuyển khoản qua VietQR — chờ cửa hàng đối soát';
    } else {
        receiptPayment.textContent = 'Thanh toán khi nhận hàng';
    }
    success.hidden = false;
    document.querySelector('.checkout-page-layout').hidden = true;
}

/**
 * Biến <select> thành ô chọn có tìm kiếm (không phân biệt dấu/hoa thường).
 * <select> gốc vẫn giữ nguyên giá trị, sự kiện 'change' và kiểm tra bắt buộc;
 * ô hiển thị tự đồng bộ khi mã khác đổi options, disabled hoặc select.value.
 */
function makeSelectSearchable(select, searchPlaceholder) {
    if (!select || select.dataset.searchable === '1') return;
    select.dataset.searchable = '1';

    const normalize = text => String(text).toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/đ/g, 'd');

    const wrap = document.createElement('div');
    wrap.className = 'ss-wrap';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.classList.add('ss-native');
    select.tabIndex = -1;

    const display = document.createElement('button');
    display.type = 'button';
    display.className = 'ss-display';
    display.setAttribute('aria-haspopup', 'listbox');
    wrap.appendChild(display);

    const panel = document.createElement('div');
    panel.className = 'ss-panel';
    panel.hidden = true;
    const search = document.createElement('input');
    search.type = 'text';
    search.className = 'ss-search';
    search.placeholder = searchPlaceholder;
    search.autocomplete = 'off';
    const list = document.createElement('ul');
    list.className = 'ss-list';
    list.setAttribute('role', 'listbox');
    panel.append(search, list);
    wrap.appendChild(panel);

    let activeIndex = -1;

    function refreshDisplay() {
        const selected = select.options[select.selectedIndex];
        display.textContent = selected ? selected.textContent : '';
        display.classList.toggle('ss-placeholder', !select.value);
        display.disabled = select.disabled;
        if (select.disabled) closePanel();
    }

    function visibleItems() {
        return Array.from(list.children).filter(item => !item.hidden);
    }

    function setActive(index) {
        const items = visibleItems();
        items.forEach(item => item.classList.remove('ss-active'));
        if (!items.length) { activeIndex = -1; return; }
        activeIndex = Math.max(0, Math.min(index, items.length - 1));
        items[activeIndex].classList.add('ss-active');
        items[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function renderList() {
        list.innerHTML = '';
        Array.from(select.options).forEach(option => {
            if (option.value === '') return;
            const item = document.createElement('li');
            item.setAttribute('role', 'option');
            item.textContent = option.textContent;
            item.dataset.value = option.value;
            item.dataset.key = normalize(option.textContent);
            if (option.value === select.value) item.classList.add('ss-selected');
            list.appendChild(item);
        });
    }

    function applyFilter() {
        const keyword = normalize(search.value.trim());
        let shown = 0;
        Array.from(list.children).forEach(item => {
            item.hidden = keyword !== '' && !item.dataset.key.includes(keyword);
            if (!item.hidden) shown++;
        });
        let empty = list.querySelector('.ss-empty');
        if (!shown) {
            if (!empty) {
                empty = document.createElement('li');
                empty.className = 'ss-empty';
                empty.textContent = 'Không tìm thấy kết quả phù hợp';
                list.appendChild(empty);
            }
        } else if (empty) {
            empty.remove();
        }
        setActive(0);
    }

    function openPanel() {
        if (select.disabled) return;
        renderList();
        search.value = '';
        applyFilter();
        panel.hidden = false;
        wrap.classList.add('ss-open');
        const selectedItem = list.querySelector('.ss-selected');
        if (selectedItem) {
            const items = visibleItems();
            setActive(items.indexOf(selectedItem));
        }
        search.focus();
    }

    function closePanel() {
        panel.hidden = true;
        wrap.classList.remove('ss-open');
    }

    function choose(value) {
        select.value = value;
        refreshDisplay();
        closePanel();
        select.dispatchEvent(new Event('change', { bubbles: true }));
        display.focus();
    }

    display.addEventListener('click', () => (panel.hidden ? openPanel() : closePanel()));
    search.addEventListener('input', applyFilter);
    search.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown') { event.preventDefault(); setActive(activeIndex + 1); }
        else if (event.key === 'ArrowUp') { event.preventDefault(); setActive(activeIndex - 1); }
        else if (event.key === 'Enter') {
            event.preventDefault();
            const item = visibleItems()[activeIndex];
            if (item && item.dataset.value !== undefined) choose(item.dataset.value);
        } else if (event.key === 'Escape') { closePanel(); display.focus(); }
    });
    list.addEventListener('mousedown', event => event.preventDefault());
    list.addEventListener('click', event => {
        const item = event.target.closest('li[data-value]');
        if (item) choose(item.dataset.value);
    });
    document.addEventListener('click', event => {
        if (!wrap.contains(event.target)) closePanel();
    });

    // Đồng bộ khi mã khác thay đổi select (options, disabled, .value)
    new MutationObserver(refreshDisplay).observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled'] });
    select.addEventListener('change', refreshDisplay);
    const valueDescriptor = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
    Object.defineProperty(select, 'value', {
        configurable: true,
        get() { return valueDescriptor.get.call(this); },
        set(newValue) { valueDescriptor.set.call(this, newValue); refreshDisplay(); }
    });

    refreshDisplay();
}

async function loadCheckoutLocations() {
    const provinceSelect = document.getElementById('checkout-province');
    const wardSelect = document.getElementById('checkout-ward');
    if (!provinceSelect || !wardSelect) return null;

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

        async function loadWards(provinceCode) {
            wardSelect.disabled = true;
            wardSelect.innerHTML = '<option value="">Đang tải xã/phường...</option>';
            if (!provinceCode) {
                wardSelect.innerHTML = '<option value="">Chọn tỉnh/thành trước</option>';
                return false;
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
                return true;
            } catch (error) {
                if (provinceSelect.value !== provinceCode) return;
                console.error('Không tải được xã/phường:', error);
                wardSelect.innerHTML = '<option value="">Không tải được dữ liệu — vui lòng thử lại</option>';
                return false;
            }
        }

        provinceSelect.addEventListener('change', () => {
            void loadWards(provinceSelect.value);
        });

        return {
            async selectAddress(provinceName, wardName) {
                const provinceOption = Array.from(provinceSelect.options)
                    .find(option => option.textContent.trim().toLocaleLowerCase() === provinceName.trim().toLocaleLowerCase());
                if (!provinceOption) return false;
                provinceSelect.value = provinceOption.value;
                if (!await loadWards(provinceOption.value)) return false;
                const wardOption = Array.from(wardSelect.options)
                    .find(option => option.textContent.trim().toLocaleLowerCase() === wardName.trim().toLocaleLowerCase());
                if (!wardOption) return false;
                wardSelect.value = wardOption.value;
                return true;
            }
        };
    } catch (error) {
        console.error('Không tải được tỉnh/thành:', error);
        provinceSelect.innerHTML = '<option value="">Không tải được dữ liệu — vui lòng tải lại trang</option>';
        wardSelect.innerHTML = '<option value="">Chọn tỉnh/thành trước</option>';
        return null;
    }
}

function initCheckoutAddressPicker(locationsPromise) {
    const savedAddresses = Array.isArray(window.CHECKOUT_ADDRESSES) ? window.CHECKOUT_ADDRESSES : [];
    const addressFieldset = document.getElementById('checkout-saved-addresses');
    const addressOptions = document.getElementById('checkout-address-options');
    const otherAddresses = document.getElementById('checkout-other-addresses');
    const otherAddressOptions = document.getElementById('checkout-other-address-options');
    const addressDropdownToggle = document.getElementById('checkout-address-dropdown-toggle');
    const addAddressButton = document.getElementById('checkout-add-address');
    const nameInput = document.getElementById('checkout-name');
    const phoneInput = document.getElementById('checkout-phone');
    const defaultInput = document.getElementById('checkout-default-address');
    const addressIdInput = document.getElementById('checkout-address-id');
    const editNote = document.getElementById('checkout-address-edit-note');
    const mapElement = document.getElementById('checkout-address-map');
    const mapSection = document.getElementById('checkout-map-section');
    const mapStatus = document.getElementById('checkout-map-status');
    const mapSearchButton = document.getElementById('checkout-map-search');
    const suggestions = document.getElementById('checkout-address-suggestions');
    const provinceSelect = document.getElementById('checkout-province');
    const wardSelect = document.getElementById('checkout-ward');
    const addressDetail = document.getElementById('checkout-address-detail');
    if (!addressOptions || !mapElement || !mapSection || !mapStatus || !mapSearchButton || !suggestions) return null;

    let activeAddress = null;
    let activeAddressHasEdits = false;
    let isNewAddressMode = false;
    let selectedCoordinates = null;
    let locationEdited = false;
    let map = null;
    let marker = null;
    let lookupTimer = null;
    let lookupController = null;
    let lookupVersion = 0;
    let selectionVersion = 0;
    const profileName = nameInput.value;
    const profilePhone = phoneInput.value;

    function setLocation(latitude, longitude, userInitiated) {
        selectedCoordinates = { latitude, longitude };
        if (userInitiated) {
            locationEdited = true;
            syncEditedSelection();
        }
        if (!window.L || !map) {
            mapStatus.textContent = `Đã ghi nhận tọa độ: ${latitude.toFixed(6)}, ${longitude.toFixed(6)}.`;
            return;
        }
        if (marker) {
            marker.setLatLng([latitude, longitude]);
        } else {
            marker = window.L.marker([latitude, longitude], { draggable: true }).addTo(map);
            marker.on('dragend', () => {
                const position = marker.getLatLng();
                setLocation(position.lat, position.lng, true);
            });
        }
        map.setView([latitude, longitude], Math.max(map.getZoom(), 16));
        mapStatus.textContent = `Đã ghim vị trí: ${latitude.toFixed(6)}, ${longitude.toFixed(6)}. Có thể kéo ghim để điều chỉnh.`;
    }

    function clearLocation(statusText) {
        selectedCoordinates = null;
        if (marker && map) {
            map.removeLayer(marker);
            marker = null;
        }
        mapStatus.textContent = statusText;
    }

    function getAddressForMap() {
        if (!provinceSelect.value || !wardSelect.value || !addressDetail.value.trim()) return '';
        const provinceName = provinceSelect.options[provinceSelect.selectedIndex].textContent;
        return [addressDetail.value.trim(), wardSelect.value, provinceName].join(', ');
    }

    function getMapQuery() {
        if (!isAddressComplete()) return '';
        return getLookupQuery();
    }

    function getLookupQueries() {
        const detail = addressDetail.value.trim();
        if (detail.length < 3) return [];
        const provinceName = provinceSelect.value
            ? provinceSelect.options[provinceSelect.selectedIndex].textContent
            : '';
        return Array.from(new Set([
            [detail, wardSelect.value, provinceName, 'Việt Nam'],
            [detail, provinceName, 'Việt Nam'],
            [detail, 'Việt Nam']
        ].map(parts => parts.filter(Boolean).join(', '))));
    }

    function getLookupQuery() {
        return getLookupQueries()[0] || '';
    }

    function splitSavedAddress(address) {
        const parts = String(address || '').split(',').map(part => part.trim());
        if (parts.length < 3) {
            return { detail: String(address || ''), ward: '', province: '' };
        }
        return {
            detail: parts.slice(0, -2).join(', '),
            ward: parts[parts.length - 2],
            province: parts[parts.length - 1]
        };
    }

    function isAddressComplete() {
        return Boolean(provinceSelect.value && wardSelect.value && !wardSelect.disabled && addressDetail.value.trim());
    }

    function syncEditedSelection() {
        const hasEdits = Boolean(activeAddress && isAddressComplete() && (
            locationEdited
            ||
            nameInput.value.trim() !== String(activeAddress.recipient_name).trim()
            || phoneInput.value.trim() !== String(activeAddress.phone).trim()
            || getAddressForMap() !== String(activeAddress.address).trim()
        ));
        addressIdInput.value = activeAddress && !hasEdits ? String(activeAddress.id) : '';
        const selectedDefault = activeAddress && Number(activeAddress.is_default) === 1;
        defaultInput.disabled = Boolean(selectedDefault && !hasEdits);
        if (selectedDefault && !hasEdits) defaultInput.checked = true;
        if (hasEdits && selectedDefault && !activeAddressHasEdits) defaultInput.checked = false;
        activeAddressHasEdits = hasEdits;
        if (editNote) {
            editNote.hidden = !activeAddress && !isNewAddressMode;
            editNote.textContent = hasEdits
                ? 'Thông tin sửa chỉ được lưu thành địa chỉ mặc định mới nếu bạn tích chọn lưu mặc định; nếu không, địa chỉ chỉ dùng cho đơn hàng này.'
                : isNewAddressMode
                    ? 'Địa chỉ mới chỉ được lưu vào tài khoản khi bạn tích chọn lưu làm mặc định.'
                    : 'Địa chỉ đã lưu được dùng cho đơn hàng này. Nếu chỉnh sửa, chỉ lưu bản mới khi bạn tích chọn lưu mặc định.';
        }
    }

    function updateMapVisibility() {
        const complete = isAddressComplete();
        mapSection.hidden = !complete;
        if (complete) window.setTimeout(() => map?.invalidateSize(), 0);
        return complete;
    }

    function normalizeAddressWords(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[đĐ]/g, 'd')
            .toLocaleLowerCase()
            .replace(/[^a-z0-9]+/g, ' ')
            .trim()
            .split(/\s+/)
            .filter(Boolean);
    }

    function getFeatureMatchScore(feature, detail) {
        const properties = feature.properties || {};
        const names = [
            properties.name,
            properties['name:vi'],
            properties['name:en'],
            properties.street
        ].filter(Boolean);
        const queryWords = new Set(normalizeAddressWords(detail));
        if (queryWords.size < 2) return 0;
        const featureWords = new Set(names.flatMap(normalizeAddressWords));
        const matchedWords = Array.from(queryWords).filter(word => featureWords.has(word)).length;
        return matchedWords / queryWords.size;
    }

    function getFeaturePriority(feature) {
        const properties = feature.properties || {};
        if (properties.osm_key === 'amenity' && properties.osm_value === 'bus_station') return 3;
        if (properties.osm_key === 'amenity') return 2;
        if (properties.osm_key === 'highway' && properties.osm_value === 'bus_stop') return -2;
        return 0;
    }

    function normalizeAreaName(value) {
        const words = normalizeAddressWords(value);
        const prefixes = [
            ['thanh', 'pho'], ['thi', 'tran'], ['phuong'], ['xa'], ['tinh'],
            ['quan'], ['huyen'], ['ward'], ['commune'], ['district'], ['city'], ['province']
        ];
        const prefix = prefixes.find(candidate =>
            candidate.every((word, index) => words[index] === word)
        );
        if (prefix) words.splice(0, prefix.length);
        const suffixes = ['ward', 'commune', 'district', 'city', 'province'];
        if (suffixes.includes(words[words.length - 1])) words.pop();
        return words.join('');
    }

    function isFeatureInSelectedProvince(feature) {
        if (!provinceSelect.value) return true;
        const selectedProvince = normalizeAreaName(
            provinceSelect.options[provinceSelect.selectedIndex].textContent
        );
        const properties = feature.properties || {};
        const featureProvinces = [properties.city, properties.state]
            .filter(Boolean)
            .map(normalizeAreaName)
            .filter(Boolean);
        return featureProvinces.includes(selectedProvince);
    }

    function isFeatureInSelectedArea(feature) {
        if (!isFeatureInSelectedProvince(feature)) return false;
        if (!wardSelect.value) return true;
        const selectedWard = normalizeAreaName(wardSelect.value);
        const properties = feature.properties || {};
        const featureWards = [properties.locality, properties.suburb, properties.district]
            .filter(Boolean)
            .map(normalizeAreaName)
            .filter(Boolean);
        return featureWards.includes(selectedWard);
    }

    function rankAddressFeatures(features, detail) {
        return features.slice().sort((left, right) =>
            getFeatureMatchScore(right, detail) - getFeatureMatchScore(left, detail)
            || getFeaturePriority(right) - getFeaturePriority(left)
        );
    }

    function findMatchingFeature(features, detail) {
        const houseNumber = detail.match(/^\s*(?:số\s*)?(\d+[a-z]?(?:\/\d+[a-z]?)?)/i);
        if (houseNumber) {
            const normalizePart = value => normalizeAddressWords(value).join('');
            const normalizedDetail = normalizePart(detail);
            return features.find(feature => {
                if (!isFeatureInSelectedArea(feature)) return false;
                const properties = feature.properties || {};
                const normalizedStreet = normalizePart(properties.street || properties.name).replace(/^duong/, '');
                return String(properties.housenumber || '').toLocaleLowerCase() === houseNumber[1].toLocaleLowerCase()
                    && normalizedStreet !== ''
                    && normalizedDetail.includes(normalizedStreet);
            }) || null;
        }

        return rankAddressFeatures(features.filter(isFeatureInSelectedArea), detail)
            .find(feature => getFeatureMatchScore(feature, detail) >= 0.6) || null;
    }

    function renderSuggestions(features, version) {
        suggestions.innerHTML = '';
        features.slice(0, 5).forEach(feature => {
            const properties = feature.properties || {};
            const coordinates = feature.geometry?.coordinates;
            if (!Array.isArray(coordinates) || coordinates.length < 2) return;
            const labelParts = [
                properties.housenumber,
                properties.street,
                properties.name,
                properties['name:vi'],
                properties.suburb,
                properties.locality,
                properties.district,
                properties.city,
                properties.state,
                properties.country
            ].map(part => String(part || '').trim()).filter(Boolean);
            const labelText = Array.from(new Set(labelParts)).join(', ');
            if (!labelText) return;
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'checkout-address-suggestion';
            option.setAttribute('role', 'option');
            option.textContent = labelText;
            option.addEventListener('click', () => {
                if (version !== lookupVersion) return;
                const properties = feature.properties || {};
                const specificDetail = [
                    properties.housenumber,
                    properties.street,
                    properties.name || properties['name:vi'] || properties['name:en']
                ].map(part => String(part || '').trim()).filter(Boolean).join(', ');
                if (specificDetail) {
                    addressDetail.value = specificDetail.slice(0, 350);
                    syncEditedSelection();
                }
                setLocation(Number(coordinates[1]), Number(coordinates[0]), true);
                suggestions.hidden = true;
                mapStatus.textContent = `Đã cập nhật địa chỉ theo gợi ý: ${labelText}. Có thể kéo ghim để điều chỉnh.`;
            });
            suggestions.appendChild(option);
        });
        suggestions.hidden = suggestions.childElementCount === 0;
    }

    async function lookupAddress(query) {
        const version = ++lookupVersion;
        if (lookupController) lookupController.abort();
        lookupController = new AbortController();
        mapStatus.textContent = 'Đang tìm địa chỉ thực tế...';
        try {
            const detail = addressDetail.value.trim();
            const queries = Array.from(new Set([query, ...getLookupQueries()].filter(Boolean)));
            let features = [];
            for (const searchQuery of queries) {
                const url = `https://photon.komoot.io/api/?q=${encodeURIComponent(searchQuery)}&limit=5`;
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    signal: lookupController.signal
                });
                if (!response.ok) throw new Error(`Tìm địa chỉ thất bại (${response.status}).`);
                const result = await response.json();
                if (version !== lookupVersion) return;
                const foundFeatures = Array.isArray(result.features) ? result.features : [];
                const existingFeatures = new Set(features.map(feature => {
                    const properties = feature.properties || {};
                    const coordinates = feature.geometry?.coordinates || [];
                    return `${properties.osm_type || ''}:${properties.osm_id || coordinates.join(',')}:${properties.name || ''}`;
                }));
                features = features.concat(foundFeatures.filter(feature => {
                    const properties = feature.properties || {};
                    const coordinates = feature.geometry?.coordinates || [];
                    const key = `${properties.osm_type || ''}:${properties.osm_id || coordinates.join(',')}:${properties.name || ''}`;
                    if (existingFeatures.has(key)) return false;
                    existingFeatures.add(key);
                    return true;
                }));
                if (findMatchingFeature(foundFeatures, detail)) break;
            }

            const localFeatures = features.filter(isFeatureInSelectedArea);
            const rankedFeatures = rankAddressFeatures(localFeatures, detail);
            renderSuggestions(rankedFeatures, version);
            if (!isAddressComplete()) {
                mapStatus.textContent = rankedFeatures.length
                    ? 'Chọn gợi ý nếu phù hợp; bản đồ sẽ hiện sau khi điền đủ tỉnh/thành và xã/phường.'
                    : features.length
                        ? 'Kết quả tìm được không khớp địa giới đã chọn; hãy kiểm tra lại tỉnh/thành và xã/phường.'
                        : 'Chưa tìm thấy gợi ý. Thử nhập thêm tên đường hoặc số nhà.';
                return;
            }
            const validFeatures = rankedFeatures.filter(feature =>
                Array.isArray(feature.geometry?.coordinates)
                && feature.geometry.coordinates.length >= 2
                && isFeatureInSelectedArea(feature)
            );
            if (validFeatures.length === 0) {
                clearLocation(features.length
                    ? 'Kết quả tìm được không thuộc đúng tỉnh/thành và xã/phường đã chọn; hãy kiểm tra địa chỉ hoặc khu vực.'
                    : 'Không tìm thấy vị trí khớp. Bạn có thể điều chỉnh địa chỉ hoặc ghim trực tiếp trên bản đồ.');
                return;
            }

            const exactFeature = findMatchingFeature(validFeatures, detail);
            if (exactFeature) {
                setLocation(Number(exactFeature.geometry.coordinates[1]), Number(exactFeature.geometry.coordinates[0]));
                mapStatus.textContent = detail.match(/^\s*(?:số\s*)?\d+/i)
                    ? 'Đã ghim vị trí có số nhà và đường phù hợp. Hãy kiểm tra ghim trước khi đặt hàng.'
                    : 'Đã ghim địa điểm phù hợp với tên bạn nhập. Hãy kiểm tra ghim trước khi đặt hàng.';
            } else {
                clearLocation('Chưa tìm được điểm khớp đủ tin cậy. Chọn một gợi ý phù hợp hoặc nhấn đúng vị trí trên bản đồ để ghim.');
                const nearest = validFeatures[0].geometry.coordinates;
                map?.setView([Number(nearest[1]), Number(nearest[0])], 16);
            }
        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('Không tìm được địa chỉ thực tế:', error);
            mapStatus.textContent = error.message || 'Không thể tìm vị trí lúc này. Bạn có thể ghim trực tiếp trên bản đồ.';
        }
    }

    function scheduleAddressLookup() {
        updateMapVisibility();
        window.clearTimeout(lookupTimer);
        if (lookupController) lookupController.abort();
        lookupVersion += 1;
        suggestions.innerHTML = '';
        suggestions.hidden = true;
        const query = getLookupQuery();
        if (!query) {
            clearLocation('Nhập ít nhất 3 ký tự địa chỉ cụ thể để xem gợi ý.');
            return;
        }
        clearLocation('Đang tìm gợi ý địa chỉ...');
        locationEdited = false;
        lookupTimer = window.setTimeout(() => {
            void lookupAddress(query);
        }, 500);
    }

    function renderCurrentAddress(address, isNewAddress) {
        addressOptions.innerHTML = '';
        const card = document.createElement(address ? 'button' : 'div');
        card.className = `checkout-address-option checkout-address-current is-selected${address ? ' is-actionable' : ''}`;
        if (address) {
            card.type = 'button';
            card.addEventListener('click', () => {
                void chooseAddress(address.id);
            });
        }
        const details = document.createElement('span');
        const title = document.createElement('strong');
        const phoneText = document.createElement('span');
        const addressText = document.createElement('span');

        if (address) {
            title.textContent = address.recipient_name;
            phoneText.textContent = address.phone;
            addressText.textContent = address.address;
            details.append(title, phoneText, addressText);
            if (Number(address.is_default) === 1) {
                const defaultLabel = document.createElement('em');
                defaultLabel.textContent = 'Địa chỉ mặc định';
                details.appendChild(defaultLabel);
            }
        } else {
            title.textContent = isNewAddress ? 'Địa chỉ mới' : 'Chưa có địa chỉ mặc định';
            phoneText.textContent = isNewAddress
                ? 'Nhập thông tin nhận hàng bên dưới.'
                : 'Chọn địa chỉ trong danh sách hoặc thêm địa chỉ mới.';
            details.append(title, phoneText);
        }

        card.appendChild(details);
        addressOptions.appendChild(card);
    }

    function addAddressOption(container, address) {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'checkout-address-option checkout-address-menu-option';
        const details = document.createElement('span');
        const title = document.createElement('strong');
        const phoneText = document.createElement('span');
        const addressText = document.createElement('span');
        title.textContent = address.recipient_name;
        phoneText.textContent = address.phone;
        addressText.textContent = address.address;
        details.append(title, phoneText, addressText);
        if (Number(address.is_default) === 1) {
            const defaultLabel = document.createElement('em');
            defaultLabel.textContent = 'Địa chỉ mặc định';
            details.appendChild(defaultLabel);
        }
        option.appendChild(details);
        option.addEventListener('click', () => {
            void chooseAddress(address.id);
        });
        container.appendChild(option);
    }

    function renderOtherAddressOptions() {
        otherAddressOptions.innerHTML = '';
        savedAddresses
            .filter(address => !activeAddress || String(address.id) !== String(activeAddress.id))
            .forEach(address => addAddressOption(otherAddressOptions, address));
    }

    async function chooseAddress(addressId) {
        const currentSelection = ++selectionVersion;
        window.clearTimeout(lookupTimer);
        if (lookupController) lookupController.abort();
        lookupVersion += 1;
        suggestions.innerHTML = '';
        suggestions.hidden = true;
        const selected = savedAddresses.find(address => String(address.id) === String(addressId));
        activeAddress = selected || null;
        activeAddressHasEdits = false;
        isNewAddressMode = !selected;
        locationEdited = false;
        addressIdInput.value = selected ? String(selected.id) : '';
        renderCurrentAddress(selected, isNewAddressMode);
        renderOtherAddressOptions();
        otherAddresses.hidden = true;
        addressDropdownToggle.setAttribute('aria-expanded', 'false');

        if (selected) {
            nameInput.value = selected.recipient_name;
            phoneInput.value = selected.phone;
            defaultInput.checked = Number(selected.is_default) === 1;
            const parts = splitSavedAddress(selected.address);
            addressDetail.value = parts.detail;
            provinceSelect.value = '';
            wardSelect.disabled = true;
            wardSelect.innerHTML = '<option value="">Chọn tỉnh/thành trước</option>';
            const locations = await locationsPromise;
            let locationPartsLoaded = false;
            if (currentSelection === selectionVersion && locations && parts.province && parts.ward) {
                locationPartsLoaded = await locations.selectAddress(parts.province, parts.ward);
            }
            if (currentSelection !== selectionVersion) return;
            if (!locationPartsLoaded) {
                addressDetail.value = selected.address;
                provinceSelect.value = '';
                wardSelect.disabled = true;
                wardSelect.innerHTML = '<option value="">Chọn tỉnh/thành trước</option>';
            }
            syncEditedSelection();
            const complete = updateMapVisibility();
            const latitude = Number(selected.latitude);
            const longitude = Number(selected.longitude);
            if (complete && selected.latitude !== null && selected.longitude !== null
                && selected.latitude !== '' && selected.longitude !== ''
                && Number.isFinite(latitude) && Number.isFinite(longitude)) {
                setLocation(latitude, longitude);
            } else {
                scheduleAddressLookup();
            }
        } else {
            nameInput.value = profileName;
            phoneInput.value = profilePhone;
            addressIdInput.value = '';
            locationEdited = false;
            if (editNote) editNote.hidden = true;
            defaultInput.disabled = false;
            defaultInput.checked = false;
            provinceSelect.value = '';
            wardSelect.disabled = true;
            wardSelect.innerHTML = '<option value="">Chọn tỉnh/thành trước</option>';
            addressDetail.value = '';
            syncEditedSelection();
            scheduleAddressLookup();
        }
    }

    const defaultAddress = savedAddresses.find(address => Number(address.is_default) === 1) || null;
    if (savedAddresses.length > 0) {
        addressFieldset.hidden = false;
        addressDropdownToggle.hidden = false;
        addressDropdownToggle.addEventListener('click', () => {
            const isExpanded = addressDropdownToggle.getAttribute('aria-expanded') === 'true';
            addressDropdownToggle.setAttribute('aria-expanded', String(!isExpanded));
            otherAddresses.hidden = isExpanded;
        });
        addAddressButton.addEventListener('click', () => {
            void chooseAddress('new');
        });
    }

    if (!window.L) {
        mapSearchButton.disabled = true;
        mapStatus.textContent = 'Không tải được thư viện bản đồ. Gợi ý địa chỉ và đặt hàng vẫn hoạt động bình thường.';
    } else {
        map = window.L.map(mapElement, { scrollWheelZoom: false }).setView([16.0, 107.5], 5);
        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap contributors</a>'
        }).addTo(map);
        map.on('click', event => setLocation(event.latlng.lat, event.latlng.lng, true));

        mapSearchButton.addEventListener('click', async () => {
            const query = getMapQuery();
            if (!query) {
                mapStatus.textContent = 'Vui lòng nhập đầy đủ tỉnh/thành, xã/phường và địa chỉ cụ thể trước.';
                return;
            }
            await lookupAddress(query);
        });
    }

    void chooseAddress(defaultAddress ? defaultAddress.id : 'new');

    return {
        getAddressId: () => addressIdInput.value ? Number(addressIdInput.value) : null,
        isEditingSavedAddress: () => Boolean(activeAddress && !addressIdInput.value),
        getAddress: getAddressForMap,
        getCoordinates: () => selectedCoordinates,
        isAddressComplete,
        scheduleAddressLookup,
        syncEditedSelection
    };
}

function initCheckoutPage() {
    const form = document.getElementById('checkout-form');
    const submitButton = document.getElementById('submit-checkout');
    const message = document.getElementById('checkout-form-message');
    const loginNotice = document.getElementById('checkout-login-notice');
    if (!form || !submitButton || !message) return;

    const paymentCards = Array.from(document.querySelectorAll('[data-payment-card]'));
    const qrPanel = document.getElementById('checkout-qr-panel');
    const qrAmount = document.getElementById('checkout-qr-amount');
    const syncPaymentMethod = () => {
        const selected = form.querySelector('input[name="payment_method"]:checked')?.value || 'COD';
        paymentCards.forEach(card => card.classList.toggle('is-selected', card.dataset.paymentCard === selected));
        if (qrPanel) qrPanel.hidden = selected !== 'BANK_QR';
        submitButton.textContent = selected === 'BANK_QR' ? 'Lấy mã QR' : 'Hoàn tất đặt hàng';
        if (qrAmount) {
            const total = getCheckoutItems().reduce((sum, item) =>
                sum + (Number(item.price) || 0) * (Number(item.quantity) || 0), 0);
            qrAmount.textContent = formatPrice(total);
        }
    };
    paymentCards.forEach(card => {
        card.addEventListener('click', () => {
            const radio = card.querySelector('input[name="payment_method"]');
            if (radio && !radio.disabled) radio.checked = true;
            syncPaymentMethod();
        });
        card.querySelector('input[name="payment_method"]')?.addEventListener('change', syncPaymentMethod);
    });
    syncPaymentMethod();

    const phoneInput = document.getElementById('checkout-phone');
    const emailInput = document.getElementById('checkout-email');
    if (phoneInput) {
        phoneInput.addEventListener('input', () => phoneInput.setCustomValidity(''));
        phoneInput.addEventListener('invalid', () => {
            phoneInput.setCustomValidity(phoneInput.validity.valueMissing
                ? 'Vui lòng nhập số điện thoại.'
                : 'Số điện thoại phải gồm đúng 10 chữ số và bắt đầu bằng 0.');
        });
    }
    if (emailInput) {
        emailInput.addEventListener('input', () => emailInput.setCustomValidity(''));
        emailInput.addEventListener('invalid', () => {
            emailInput.setCustomValidity(emailInput.validity.valueMissing
                ? 'Vui lòng nhập email.'
                : 'Vui lòng nhập địa chỉ email hợp lệ, ví dụ: ten@example.com.');
        });
    }

    renderCheckoutSummary();
    makeSelectSearchable(document.getElementById('checkout-province'), 'Tìm tỉnh/thành phố...');
    makeSelectSearchable(document.getElementById('checkout-ward'), 'Tìm xã/phường...');
    const checkoutLocations = loadCheckoutLocations();

    const currentCustomer = getCurrentCustomer();
    const userJson = localStorage.getItem('currentUser');
    if (currentCustomer) {
        try {
            const user = userJson ? JSON.parse(userJson) : {};
            if (!['admin', 'manager', 'staff'].includes(user.role)) {
                document.getElementById('checkout-name').value = currentCustomer || user.username || '';
                document.getElementById('checkout-phone').value = user.phone || '';
            }
        } catch (error) {
            console.error('Không đọc được thông tin tài khoản:', error);
        }
    } else if (loginNotice) {
        loginNotice.hidden = false;
    }
    const addressPicker = initCheckoutAddressPicker(checkoutLocations);

    const provinceSelect = document.getElementById('checkout-province');
    const wardSelect = document.getElementById('checkout-ward');
    const addressDetail = document.getElementById('checkout-address-detail');
    const resetAddressForRegionChange = () => {
        addressDetail.value = '';
        if (addressPicker) {
            addressPicker.syncEditedSelection();
            addressPicker.scheduleAddressLookup();
        }
    };
    [provinceSelect, wardSelect].forEach(field => {
        field?.addEventListener('change', resetAddressForRegionChange);
    });
    addressDetail?.addEventListener('change', () => {
        if (addressPicker) {
            addressPicker.syncEditedSelection();
            addressPicker.scheduleAddressLookup();
        }
    });
    addressDetail?.addEventListener('input', () => {
        if (addressPicker) {
            addressPicker.syncEditedSelection();
            addressPicker.scheduleAddressLookup();
        }
    });
    [document.getElementById('checkout-name'), document.getElementById('checkout-phone')].forEach(field => {
        field?.addEventListener('input', () => {
            if (addressPicker) addressPicker.syncEditedSelection();
        });
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();
        message.textContent = '';
        const selectedItems = getCheckoutItems();
        if (selectedItems.length === 0) {
            message.textContent = isDirectCheckout()
                ? 'Không tải được sản phẩm mua ngay. Vui lòng quay lại trang sản phẩm và thử lại.'
                : 'Chưa có sản phẩm nào được chọn. Vui lòng quay lại giỏ hàng để chọn sản phẩm.';
            return;
        }

        if (!getCurrentCustomer()) {
            if (loginNotice) loginNotice.hidden = false;
            document.getElementById('login-modal')?.classList.add('active');
            return;
        }

        if (!form.reportValidity()) return;
        const provinceSelect = document.getElementById('checkout-province');
        const wardSelect = document.getElementById('checkout-ward');
        const savedAddressId = addressPicker ? addressPicker.getAddressId() : null;
        if (!savedAddressId && (!provinceSelect.value || !wardSelect.value || wardSelect.disabled)) {
            message.textContent = 'Vui lòng chọn đầy đủ tỉnh/thành phố và xã/phường.';
            (provinceSelect.value ? wardSelect : provinceSelect).focus();
            return;
        }

        const formData = new FormData(form);
        const address = savedAddressId
            ? addressPicker.getAddress()
            : [
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
        const paymentMethod = String(formData.get('payment_method') || 'COD');

        submitButton.disabled = true;
        submitButton.textContent = 'Đang xử lý đơn hàng...';
        try {
            const response = await fetch(appUrl('/api/orders/dat_hang.php'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    cart: selectedItems.map(item => ({
                        product_id: item.product_id,
                        quantity: item.quantity
                    })),
                    customer,
                    address_id: savedAddressId,
                    save_as_new_address: addressPicker ? addressPicker.isEditingSavedAddress() : false,
                    make_default: document.getElementById('checkout-default-address').checked,
                    coordinates: addressPicker ? addressPicker.getCoordinates() : null,
                    payment_method: paymentMethod
                })
            });
            const result = await response.json();
            if (!response.ok || result.status !== 'success') {
                throw new Error(result.message || 'Không thể đặt hàng. Vui lòng thử lại.');
            }

            if (isDirectCheckout()) {
                try {
                    sessionStorage.removeItem(DIRECT_CHECKOUT_KEY);
                } catch (error) {
                    console.error('Không thể xóa dữ liệu mua ngay sau khi đặt hàng:', error);
                }
            } else {
                cart = cart.filter(item => item.selected === false);
                saveCart();
                renderCart();
                renderCheckoutSummary();
            }

// Cập nhật số lượng giỏ hàng
updateCartCount();

// Nếu khách hàng chọn thanh toán QR
if (paymentMethod === 'BANK_QR') {

    // Chuyển đến trang thanh toán riêng
    window.location.href = appUrl(
        '/client/thanh_toan.php?order_id=' +
        encodeURIComponent(result.order_id)
    );

    return;
}

// Nếu chọn COD thì giữ nguyên luồng cũ
form.hidden = true;

renderCheckoutReceipt(
    result.order_id,
    customer,
    result.items || [],
    result.total_price,
    paymentMethod,
    result.discount_amount,
    result.subtotal
);

        } catch (error) {
            console.error('Đặt hàng thất bại:', error);
            message.textContent = error.message || 'Không thể kết nối máy chủ. Vui lòng thử lại.';
            message.classList.remove('is-success');
        } finally {
            submitButton.disabled = false;
            syncPaymentMethod();
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
const PRODUCTS_PER_PAGE = 12; // 3 cột × 4 dòng = 12 sản phẩm (chuẩn 4 dòng theo yêu cầu)
let _paginationCurrentPage = 1;
let _paginationAllProducts = []; // Lưu toàn bộ sản phẩm đã fetch (dùng cho phân trang)

function buildProductCardHTML(product) {
    const rawOrig = product.price_original;
    const rawSale = product.price_sale;
    const origPriceVal = rawOrig ? parsePrice(rawOrig) : 0;
    const salePriceVal = rawSale ? parsePrice(rawSale) : 0;
    let calcPercent = 0;
    if (origPriceVal > salePriceVal && origPriceVal > 0) {
        calcPercent = Math.round(((origPriceVal - salePriceVal) / origPriceVal) * 100);
    }
    const discountText = (product.discount && product.discount.trim() !== '')
        ? product.discount
        : (calcPercent > 0 ? `-${calcPercent}%` : '');
    const badgeHTML = discountText ? `<div class="product-badge">${discountText}</div>` : '';

    const priceSaleFormatted = formatDbPrice(product.price_sale);
    const percentTagHTML = calcPercent > 0 ? `<span class="price-discount-percent">-${calcPercent}%</span>` : '';
    const priceOriginalHTML = rawOrig && origPriceVal > salePriceVal && origPriceVal > 0
        ? `<span class="price-original" style="text-decoration: line-through; color: #999; margin-left: 10px; font-size: 0.9rem;">${formatDbPrice(product.price_original)}<sup>đ</sup></span>${percentTagHTML}`
        : '';

    const stock = parseInt(product.stock, 10) || 0;
    const soldCount = parseInt(product.sold_count, 10) || 0;
    const wishlistCount = parseInt(product.wishlist_count, 10) || 0;

    const metaHTML = renderProductMeta(soldCount, wishlistCount);

    const priceSaleRaw = (typeof product.price_sale_raw !== 'undefined') ? product.price_sale_raw : parsePrice(product.price_sale);
    const hasDiscount = Boolean((product.discount && product.discount.trim() !== '') || (product.price_original_raw > product.price_sale_raw));
    const hasGift = Boolean(product.gift && product.gift.trim() !== '');

    return `
        <div class="product-card" data-category="${product.category || ''}" data-db-id="${product.id}" data-price="${priceSaleRaw}" data-stock="${stock}" data-discount="${hasDiscount ? '1' : '0'}" data-gift="${hasGift ? '1' : '0'}" data-sold="${soldCount}" data-detail-url="${appUrl('/client/san_pham.php')}?id=${encodeURIComponent(product.id)}" role="link" tabindex="0" aria-label="Xem chi tiết sản phẩm">
            <div class="product-image-wrap">
                ${badgeHTML}
                <button type="button" class="wishlist-btn${(typeof isProductFavorited === 'function' && isProductFavorited(product.id, product.is_favorite)) ? ' active' : ''}" data-product-id="${product.id}" title="${(typeof isProductFavorited === 'function' && isProductFavorited(product.id, product.is_favorite)) ? 'Bỏ yêu thích' : 'Yêu thích'}" aria-label="Yêu thích">${(typeof isProductFavorited === 'function' && isProductFavorited(product.id, product.is_favorite)) ? '♥' : '♡'}</button>
                <img src="${getProductImageUrl(product.image)}" alt="${product.name}" class="product-image" onerror="this.onerror=null;this.src='${appUrl('/assets/banner/logo.png')}';">
            </div>
            <div class="product-info">
                <h3>${product.name}</h3>
                <div class="product-price">
                    <span class="price-sale">${priceSaleFormatted}<sup>đ</sup></span>
                    ${priceOriginalHTML}
                </div>
                ${metaHTML}
                ${renderProductRating(product)}
                <div class="product-purchase-controls">
                    ${getProductQuantityControlMarkup(stock, product.name)}
                    <div class="product-card-actions">
                        <button type="button" class="add-to-cart"${stock <= 0 ? ' disabled' : ''}>${stock <= 0 ? 'Tạm hết hàng' : 'Thêm vào giỏ'}</button>
                        <button type="button" class="buy-now"${stock <= 0 ? ' disabled' : ''}>Mua ngay</button>
                    </div>
                </div>
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
            const emptyState = document.getElementById('filter-empty-state') || document.getElementById('search-empty-state');
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

    // Cuộn lên đầu lưới sản phẩm (tính toán trừ đi chiều cao header cố định)
    const categoryMain = document.querySelector('.category-main');
    if (categoryMain) {
        const headerHeight = document.querySelector('.sticky-header-wrapper')?.offsetHeight || 160;
        const targetPos = categoryMain.getBoundingClientRect().top + window.pageYOffset - headerHeight - 16;
        window.scrollTo({
            top: Math.max(0, targetPos),
            behavior: 'smooth'
        });
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

    if (typeof wishlistIds !== 'undefined' && wishlistIds.size > 0) {
        wishlistIds.forEach(id => updateWishlistBtns(id, true));
    }

    // Render phân trang
    renderPaginationUI(dbProducts.length, currentPage, goToPage);

    // Danh mục hiển thị sản phẩm theo trang.
    const loadMoreContainer = document.querySelector('.load-more-container');
    if (loadMoreContainer) loadMoreContainer.style.display = 'none';
}

function renderDbProductPage(products, container, totalItems, currentPage, onPageChange) {
    container.innerHTML = '';
    products.forEach(product => {
        container.insertAdjacentHTML('beforeend', buildProductCardHTML(product));
    });
    renderPaginationUI(totalItems, currentPage, onPageChange);
    applyUserRole();
    wishlistIds.forEach(id => updateWishlistBtns(id, true));
}

function normalizeSearchQuery(value) {
    return String(value ?? '').replace(/\s+/g, ' ').trim();
}

function initSearch() {
    const searchInput = document.querySelector('.search');
    if (!searchInput) return;

    let wrapper = searchInput.closest('.search-wrapper');
    if (!wrapper) {
        wrapper = document.createElement('div');
        wrapper.className = 'search-wrapper';
        searchInput.parentNode.insertBefore(wrapper, searchInput);
        wrapper.appendChild(searchInput);
    }

    // Create the search results dropdown element
    const dropdown = document.createElement('div');
    dropdown.className = 'search-results-dropdown';
    wrapper.appendChild(dropdown);

    let searchDebounceTimer = null;
    let searchRequestController = null;
    let searchRequestSequence = 0;

    searchInput.addEventListener('focus', () => {
        if (normalizeSearchQuery(searchInput.value)) updateSearchResults();
    });
    searchInput.addEventListener('input', updateSearchResults);

    function updateSearchResults() {
        const query = normalizeSearchQuery(searchInput.value);
        window.clearTimeout(searchDebounceTimer);
        if (searchRequestController) searchRequestController.abort();
        const requestId = ++searchRequestSequence;

        if (!query) {
            dropdown.classList.remove('active');
            dropdown.innerHTML = '';
            return;
        }

        dropdown.textContent = 'Đang tìm sản phẩm...';
        dropdown.classList.add('active');
        searchDebounceTimer = window.setTimeout(async () => {
            const controller = new AbortController();
            searchRequestController = controller;
            const params = new URLSearchParams({ search: query, suggest: '1', sort: 'relevance' });
            try {
                const response = await fetch(`${appUrl('/api/catalog/lay_san_pham.php')}?${params}`, {
                    signal: controller.signal
                });
                const data = await response.json();
                if (!response.ok || !Array.isArray(data.products)) {
                    throw new Error(data.message || 'Không thể tìm sản phẩm.');
                }
                if (requestId !== searchRequestSequence
                    || normalizeSearchQuery(searchInput.value) !== query) return;
                renderSearchResults(data.products, dropdown);
            } catch (error) {
                if (error.name === 'AbortError' || requestId !== searchRequestSequence) return;
                console.error('Lỗi tìm kiếm gợi ý:', error);
                dropdown.textContent = 'Không thể tìm sản phẩm. Vui lòng thử lại.';
                dropdown.classList.add('active');
            }
        }, 300);
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
            const query = normalizeSearchQuery(searchInput.value);
            dropdown.classList.remove('active');

            if (query) {
                handleScreenSearch(query);
            }
        }
    });

    function handleScreenSearch(query) {
        window.location.href = `${appUrl('/index.php')}?search=${encodeURIComponent(query)}`;
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

            const item = document.createElement('a');
            item.className = 'search-result-item';
            item.href = `${appUrl('/client/san_pham.php')}?id=${encodeURIComponent(product.id)}`;
            item.setAttribute('aria-label', `Xem chi tiết ${product.name}`);

            const image = document.createElement('img');
            image.src = imageUrl;
            image.alt = '';
            image.className = 'search-result-image';
            image.onerror = () => {
                image.onerror = null;
                image.src = appUrl('/assets/banner/logo.png');
            };

            const info = document.createElement('div');
            info.className = 'search-result-info';
            const name = document.createElement('p');
            name.className = 'search-result-name';
            name.textContent = product.name;
            const price = document.createElement('span');
            price.className = 'search-result-price';
            price.textContent = priceSaleFormatted;
            info.append(name, price);
            item.append(image, info);

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

    if (
        ['cart', 'profile'].includes(new URLSearchParams(window.location.search).get('login_required'))
        && !getCurrentCustomer()
    ) {
        loginModal?.classList.add('active');
    }

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
                const response = await fetch(appUrl('/api/auth/dang_nhap.php'), {
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
                        currentCustomerUsername = data.username;
                        loginModal.classList.remove('active');
                        applyUserRole();
                        const loginRequired = new URLSearchParams(window.location.search).get('login_required');
                        if (loginRequired === 'cart') {
                            window.location.assign(appUrl('/client/gio_hang.php'));
                        } else if (loginRequired === 'profile') {
                            window.location.assign(appUrl('/client/ho_so.php'));
                        } else {
                            window.location.reload();
                        }
                    } else {
                        localStorage.removeItem('currentUser');
                        currentCustomerUsername = null;
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
                const response = await fetch(appUrl('/api/auth/dang_ky.php'), {
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
                const response = await fetch(appUrl('/api/auth/dang_xuat.php'));
                const data = await response.json();
                if (data.status === 'success') {
                    localStorage.removeItem('currentUser');
                    currentCustomerUsername = null;
                    loadCart();
                    updateCartCount();
                    renderCart();
                    applyUserRole();
                    alert('Đã đăng xuất thành công!');
                    if (window.location.pathname.toLowerCase().endsWith('/client/ho_so.php')) {
                        // Trang hồ sơ cần đăng nhập: sau khi đăng xuất thì về trang chủ
                        window.location.assign(appUrl('/index.php'));
                    } else {
                        window.location.reload(); // Load lại trang để cập nhật trạng thái sạch
                    }
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
        const response = await fetch(appUrl('/api/auth/kiem_tra_phien.php'));
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.message || 'Không thể kiểm tra phiên đăng nhập.');
        }
        if (data.status === 'success' && data.role === 'customer') {
            const user = { username: data.username, role: data.role };
            currentCustomerUsername = data.username;
            localStorage.setItem('currentUser', JSON.stringify(user));
        } else {
            currentCustomerUsername = null;
            localStorage.removeItem('currentUser');
        }
        const isCartPage = Boolean(document.querySelector('.cart-items'));
        if (isCartPage) cartAvailabilityState = 'checking';
        applyUserRole();
        loadCart();
        updateCartCount();
        renderCart();
        if (isCartPage) refreshCartAvailability();
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
                        <a class="auth-btn profile-link" href="${window.APP_BASE_URL || ''}/client/ho_so.php" title="Hồ sơ của tôi"><span>Xin chào, ${escapeHtml(user.username)}</span> <svg class="profile-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"></path></svg></a>
                    `;
                }
                return;
            }
        } catch (e) {
            localStorage.removeItem('currentUser');
        }
    }

    // Keep the server-rendered admin identity visible on client pages when no customer is signed in.
    if (window.ACCOUNT_ROLE === 'admin') return;

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
        let wrapper = header.closest('.sticky-header-wrapper');
        if (!wrapper) {
            wrapper = document.createElement('div');
            wrapper.className = 'sticky-header-wrapper';
            header.parentNode.insertBefore(wrapper, header);
            wrapper.appendChild(header);
            wrapper.appendChild(menu);
        }

        let spacer = wrapper.nextElementSibling;
        if (!spacer || !spacer.classList.contains('sticky-header-spacer')) {
            spacer = document.createElement('div');
            spacer.className = 'sticky-header-spacer';
            wrapper.parentNode.insertBefore(spacer, wrapper.nextSibling);
        }

        const updateSpacer = () => {
            window.requestAnimationFrame(() => {
                if (wrapper && spacer) {
                    const h = wrapper.getBoundingClientRect().height;
                    if (h > 0) {
                        spacer.style.height = `${h}px`;
                        document.documentElement.style.setProperty('--header-height', `${h}px`);
                    }
                }
            });
        };

        updateSpacer();

        if ('ResizeObserver' in window) {
            new ResizeObserver(updateSpacer).observe(wrapper);
        }

        wrapper.querySelectorAll('img').forEach(image => {
            if (image.complete) {
                updateSpacer();
            } else {
                image.addEventListener('load', updateSpacer, { once: true });
            }
        });

        window.addEventListener('resize', updateSpacer);
    }
}

// ==================== CATEGORY FILTERS & SORTING (SHOPEE STYLE) ====================

function initCategoryFilters() {
    const productGrid = document.querySelector('.category-grid');
    if (!productGrid) return;

    const container = document.getElementById('category-products-container');
    if (!container) return;

    const dbCategory = container.getAttribute('data-db-category');
    const categorySelect = document.getElementById('filter-category');
    if (categorySelect) {
        categorySelect.addEventListener('change', () => {
            if (categorySelect.value) {
                window.location.href = `${appUrl('/client/danh_muc.php')}?slug=${encodeURIComponent(categorySelect.value)}`;
            }
        });
    }
    if (!dbCategory) return;

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
    let resultsSummary = document.getElementById('category-results-count');
    if (!resultsSummary) {
        resultsSummary = document.createElement('p');
        resultsSummary.id = 'category-results-count';
        resultsSummary.className = 'category-results-count';
        container.parentNode.insertBefore(resultsSummary, container);
    }
    let filterError = document.getElementById('category-filter-error');
    if (!filterError) {
        filterError = document.createElement('p');
        filterError.id = 'category-filter-error';
        filterError.className = 'category-filter-error';
        filterError.hidden = true;
        container.parentNode.insertBefore(filterError, container);
    }
    let requestSequence = 0;

    // Thanh sắp xếp Shopee
    const sortBtns = document.querySelectorAll('.shopee-sort-btn[data-sort]');
    const priceDropdown = document.getElementById('shopee-sort-price-dropdown');
    const priceDropdownToggle = document.getElementById('price-dropdown-toggle');
    const priceDropdownLabel = priceDropdownToggle?.querySelector('.price-dropdown-label');
    const priceDropdownItems = document.querySelectorAll('.price-dropdown-item');
    const legacySortSelect = document.querySelector('.sorting-options select') || document.getElementById('product-sort');

    let currentSort = 'best_selling';

    // Hàm tiện ích: Làm sạch và chuẩn hóa số tiền từ chuỗi
    function parseVndNumber(val) {
        if (!val) return null;
        const cleaned = String(val).replace(/[^\d]/g, '');
        if (!cleaned) return null;
        let num = parseInt(cleaned, 10);
        return isNaN(num) ? null : num;
    }

    // Hàm tiện ích: Định dạng số tiền kiểu VNĐ
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
            applyFiltersAndSort();
        });
    });

    // Lắng nghe thay đổi các checkbox
    [inStockCb, discountCb, giftCb].forEach(cb => {
        if (cb) {
            // Không truyền thẳng hàm vì tham số đầu là số trang, không phải đối tượng sự kiện.
            cb.addEventListener('change', () => applyFiltersAndSort(1));
        }
    });

    // Nút Áp dụng khoảng giá
    if (applyBtn) {
        applyBtn.addEventListener('click', () => applyFiltersAndSort(1));
    }

    // Đưa toàn bộ bộ lọc về trạng thái ban đầu
    function resetAllFilters() {
        if (priceFromInput) priceFromInput.value = '';
        if (priceToInput) priceToInput.value = '';
        priceChips.forEach(c => c.classList.remove('active'));
        if (inStockCb) inStockCb.checked = false;
        if (discountCb) discountCb.checked = false;
        if (giftCb) giftCb.checked = false;
        applyFiltersAndSort();
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
            currentSort = btn.getAttribute('data-sort') || 'best_selling';
            applyFiltersAndSort();
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
            applyFiltersAndSort();
        });
    });

    // Hỗ trợ thẻ select sắp xếp cũ
    if (legacySortSelect) {
        legacySortSelect.addEventListener('change', () => {
            const val = legacySortSelect.value;
            if (val === 'Giá từ thấp đến cao') currentSort = 'price_asc';
            else if (val === 'Giá từ cao đến thấp') currentSort = 'price_desc';
            else if (val === 'Bán chạy nhất') currentSort = 'best_selling';
            else if (val === 'Mới cập nhật') currentSort = 'newest';
            else currentSort = 'suggest';
            applyFiltersAndSort();
        });
    }

    // ================= HÀM GỌI API VỚI FILTER + SORT (SQL-SIDE) =================
    async function applyFiltersAndSort(page = 1) {
        const requestId = ++requestSequence;
        const minVal = parseVndNumber(priceFromInput?.value);
        const maxVal = parseVndNumber(priceToInput?.value);
        const reqInStock  = inStockCb  ? inStockCb.checked  : false;
        const reqDiscount = discountCb ? discountCb.checked : false;
        const reqGift     = giftCb     ? giftCb.checked     : false;

        // Vẽ lại thanh tags tiêu chí đang lọc
        renderActiveFilterBadges(minVal, maxVal, reqInStock, reqDiscount, reqGift);

        if (minVal !== null && maxVal !== null && minVal > maxVal) {
            filterError.textContent = 'Giá thấp nhất không được lớn hơn giá cao nhất.';
            filterError.hidden = false;
            resultsSummary.textContent = '';
            container.innerHTML = '';
            if (emptyState) emptyState.style.display = 'none';
            const paginationEl = document.getElementById('pagination-controls');
            if (paginationEl) paginationEl.style.display = 'none';
            return;
        }
        filterError.hidden = true;

        // Xây dựng query string gửi lên API
        const params = new URLSearchParams({ category: dbCategory, sort: currentSort, page: String(page) });
        if (minVal !== null) params.set('min_price', minVal);
        if (maxVal !== null) params.set('max_price', maxVal);
        if (reqInStock)  params.set('in_stock',     '1');
        if (reqDiscount) params.set('has_discount',  '1');
        if (reqGift)     params.set('has_gift',      '1');

        try {
            const response = await fetch(`${appUrl('/api/catalog/lay_san_pham.php')}?${params}`);
            const data = await response.json();
            if (!response.ok || !Array.isArray(data.products)) {
                throw new Error(data.message || 'Không thể tải sản phẩm.');
            }
            if (requestId !== requestSequence) return;

            const products = data.products;
            const totalItems = Number(data.total) || 0;
            const currentPage = Number(data.page) || 1;
            resultsSummary.textContent = `Tìm thấy ${totalItems.toLocaleString('vi-VN')} sản phẩm`;
            if (emptyState) {
                emptyState.style.display = totalItems === 0 ? 'block' : 'none';
            }

            if (totalItems === 0) {
                container.innerHTML = '';
                const paginationEl = document.getElementById('pagination-controls');
                if (paginationEl) paginationEl.style.display = 'none';
                return;
            }
            renderDbProductPage(products, container, totalItems, currentPage, nextPage => {
                applyFiltersAndSort(nextPage);
                const categoryMain = document.querySelector('.category-main');
                if (categoryMain) {
                    const headerHeight = document.querySelector('.sticky-header-wrapper')?.offsetHeight || 160;
                    const targetPos = categoryMain.getBoundingClientRect().top + window.pageYOffset - headerHeight - 16;
                    window.scrollTo({ top: Math.max(0, targetPos), behavior: 'smooth' });
                }
            });
        } catch (error) {
            console.error('Lỗi khi tải sản phẩm:', error);
            if (requestId === requestSequence) {
                filterError.textContent = error.message || 'Không thể tải sản phẩm. Vui lòng thử lại.';
                filterError.hidden = false;
                resultsSummary.textContent = '';
                container.innerHTML = '';
                if (emptyState) emptyState.style.display = 'none';
                const paginationEl = document.getElementById('pagination-controls');
                if (paginationEl) paginationEl.style.display = 'none';
            }
        }
    }

    // ================= VẼ CÁC PILL BADGES TIÊU CHÍ ĐANG LỌC =================
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

        if (reqInStock)  badges.push({ type: 'stock',    label: 'Còn hàng' });
        if (reqDiscount) badges.push({ type: 'discount',  label: 'Đang giảm giá' });
        if (reqGift)     badges.push({ type: 'gift',      label: 'Có quà tặng' });

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
                applyFiltersAndSort();
            });
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

    applyFiltersAndSort(1);
}

async function initSearchResults(searchQuery, container) {
    const query = normalizeSearchQuery(searchQuery);
    if (!query) return;

    const section = document.getElementById('search-results-section');
    const title = section?.querySelector('.page-title-left');
    const categoryFilter = document.getElementById('search-filter-category');
    const minPriceFilter = document.getElementById('search-filter-min-price');
    const maxPriceFilter = document.getElementById('search-filter-max-price');
    const inStockFilter = document.getElementById('search-filter-in-stock');
    const discountFilter = document.getElementById('search-filter-discount');
    const giftFilter = document.getElementById('search-filter-gift');
    const sortFilter = document.getElementById('search-filter-sort');
    const applyButton = document.getElementById('search-filter-apply');
    const resetButton = document.getElementById('search-filter-reset');
    const emptyResetButton = document.getElementById('search-empty-reset');
    const resultsCount = document.getElementById('search-results-count');
    const resultsError = document.getElementById('search-results-error');
    const emptyState = document.getElementById('search-empty-state');
    const urlParams = new URLSearchParams(window.location.search);
    const allowedSorts = ['relevance', 'best_selling', 'newest', 'price_asc', 'price_desc', 'name_asc'];

    if (section) section.hidden = false;
    if (title) title.textContent = `KẾT QUẢ TÌM KIẾM: "${query.toLocaleUpperCase('vi')}"`;
    const searchInput = document.querySelector('.search');
    if (searchInput) searchInput.value = query;

    if (categoryFilter) categoryFilter.value = urlParams.get('category') || '';
    if (minPriceFilter) minPriceFilter.value = urlParams.get('min_price') || '';
    if (maxPriceFilter) maxPriceFilter.value = urlParams.get('max_price') || '';
    if (inStockFilter) inStockFilter.checked = urlParams.get('in_stock') === '1';
    if (discountFilter) discountFilter.checked = urlParams.get('has_discount') === '1';
    if (giftFilter) giftFilter.checked = urlParams.get('has_gift') === '1';
    if (sortFilter) {
        const requestedSort = urlParams.get('sort') || 'relevance';
        sortFilter.value = allowedSorts.includes(requestedSort) ? requestedSort : 'relevance';
    }

    let requestSequence = 0;
    async function loadSearchPage(page = 1) {
        const requestId = ++requestSequence;
        const readPrice = input => {
            if (!input || input.value.trim() === '') return null;
            const value = Number(input.value);
            return Number.isSafeInteger(value) && value >= 0 ? value : NaN;
        };
        const minPrice = readPrice(minPriceFilter);
        const maxPrice = readPrice(maxPriceFilter);
        if (Number.isNaN(minPrice) || Number.isNaN(maxPrice)
            || (minPrice !== null && maxPrice !== null && minPrice > maxPrice)) {
            if (resultsError) {
                resultsError.textContent = 'Hãy nhập khoảng giá hợp lệ; giá từ không được lớn hơn giá đến.';
                resultsError.hidden = false;
            }
            if (resultsCount) resultsCount.textContent = '';
            if (emptyState) emptyState.hidden = true;
            container.innerHTML = '';
            const pagination = document.getElementById('pagination-controls');
            if (pagination) pagination.style.display = 'none';
            return;
        }

        if (resultsError) resultsError.hidden = true;
        const params = new URLSearchParams({
            search: query,
            sort: sortFilter?.value || 'relevance',
            page: String(page),
        });
        if (categoryFilter?.value) params.set('category', categoryFilter.value);
        if (minPrice !== null) params.set('min_price', String(minPrice));
        if (maxPrice !== null) params.set('max_price', String(maxPrice));
        if (inStockFilter?.checked) params.set('in_stock', '1');
        if (discountFilter?.checked) params.set('has_discount', '1');
        if (giftFilter?.checked) params.set('has_gift', '1');

        window.history.replaceState(null, '', `${window.location.pathname}?${params.toString()}${window.location.hash}`);
        try {
            const response = await fetch(`${appUrl('/api/catalog/lay_san_pham.php')}?${params}`);
            const data = await response.json();
            if (!response.ok || !Array.isArray(data.products)) {
                throw new Error(data.message || 'Không thể tìm sản phẩm.');
            }
            if (requestId !== requestSequence) return;

            const totalItems = Number(data.total) || 0;
            const currentPage = Number(data.page) || 1;
            params.set('page', String(currentPage));
            window.history.replaceState(null, '', `${window.location.pathname}?${params.toString()}${window.location.hash}`);
            if (resultsCount) resultsCount.textContent = `Tìm thấy ${totalItems.toLocaleString('vi-VN')} sản phẩm`;
            if (emptyState) emptyState.hidden = totalItems > 0;
            if (totalItems === 0) {
                container.innerHTML = '';
                const pagination = document.getElementById('pagination-controls');
                if (pagination) pagination.style.display = 'none';
                return;
            }

            renderDbProductPage(data.products, container, totalItems, currentPage, nextPage => {
                loadSearchPage(nextPage);
                const headerHeight = document.querySelector('.sticky-header-wrapper')?.offsetHeight || 160;
                const targetPos = container.getBoundingClientRect().top + window.pageYOffset - headerHeight - 16;
                window.scrollTo({ top: Math.max(0, targetPos), behavior: 'smooth' });
            });
        } catch (error) {
            if (requestId !== requestSequence) return;
            console.error('Lỗi khi tìm kiếm sản phẩm:', error);
            if (resultsError) {
                resultsError.textContent = error.message || 'Không thể tìm sản phẩm. Vui lòng thử lại.';
                resultsError.hidden = false;
            }
            if (resultsCount) resultsCount.textContent = '';
            if (emptyState) emptyState.hidden = true;
            container.innerHTML = '';
            const pagination = document.getElementById('pagination-controls');
            if (pagination) pagination.style.display = 'none';
        }
    }

    function resetSearchFilters() {
        if (categoryFilter) categoryFilter.value = '';
        if (minPriceFilter) minPriceFilter.value = '';
        if (maxPriceFilter) maxPriceFilter.value = '';
        if (inStockFilter) inStockFilter.checked = false;
        if (discountFilter) discountFilter.checked = false;
        if (giftFilter) giftFilter.checked = false;
        if (sortFilter) sortFilter.value = 'relevance';
        loadSearchPage(1);
    }

    categoryFilter?.addEventListener('change', () => loadSearchPage(1));
    sortFilter?.addEventListener('change', () => loadSearchPage(1));
    [inStockFilter, discountFilter, giftFilter].forEach(filter => {
        filter?.addEventListener('change', () => loadSearchPage(1));
    });
    applyButton?.addEventListener('click', () => loadSearchPage(1));
    resetButton?.addEventListener('click', resetSearchFilters);
    emptyResetButton?.addEventListener('click', resetSearchFilters);

    const requestedPage = Number.parseInt(urlParams.get('page') || '1', 10);
    await loadSearchPage(Number.isSafeInteger(requestedPage) && requestedPage > 0 ? requestedPage : 1);
}

// ==================== DYNAMIC CATEGORY PAGES ====================
async function initDynamicCategoryPages() {
    const container = document.getElementById('category-products-container');
    if (!container) return;

    const urlParams = new URLSearchParams(window.location.search);
    const searchQuery = urlParams.get('search');
    if (searchQuery !== null && normalizeSearchQuery(searchQuery)) {
        const searchSection = document.getElementById('search-results-section');
        if (searchSection) searchSection.hidden = false;
        await initSearchResults(searchQuery, container);
        return;
    }

    const dbCategory = container.getAttribute('data-db-category');
    if (!dbCategory) return;

    // Category listings are loaded with backend filters and pagination by initCategoryFilters().
    if (dbCategory) return;
}

// ==================== WISHLIST ====================
let wishlistIds = new Set();

function isProductFavorited(productId, isFavoriteFromApi) {
    return wishlistIds.has(Number(productId)) || isFavoriteFromApi === true;
}

function updateWishlistBtns(productId, favorited) {
    document.querySelectorAll(`.wishlist-btn[data-product-id="${productId}"]`).forEach(btn => {
        btn.classList.toggle('active', favorited);
        btn.textContent = favorited ? '♥' : '♡';
        btn.title = favorited ? 'Bỏ yêu thích' : 'Yêu thích';
    });
}

function updateWishlistBadge(count) {
    const badge = document.querySelector('.wishlist-count');
    if (!badge) return;
    if (count > 0) {
        badge.textContent = count;
        badge.style.display = '';
    } else {
        badge.style.display = 'none';
    }
}

function saveWishlistToLocal() {
    localStorage.setItem('ecrin_wishlist', JSON.stringify([...wishlistIds]));
}

async function syncWishlistToServer() {
    const localRaw = localStorage.getItem('ecrin_wishlist');
    const localIds = localRaw ? JSON.parse(localRaw) : [];
    if (localIds.length === 0) return;
    try {
        await fetch(appUrl('/api/catalog/yeu_thich.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'sync', sync_ids: localIds })
        });
    } catch (e) {
        console.warn('Wishlist sync failed:', e);
    }
}

function handleWishlistPageRemoval(productId, favorited) {
    if (!document.querySelector('.wishlist-page')) return;
    if (favorited) return;
    document.querySelectorAll(`.wishlist-page .product-card[data-db-id="${productId}"]`).forEach(card => {
        card.style.transition = 'all 0.3s ease';
        card.style.opacity = '0';
        card.style.transform = 'scale(0.85)';
        setTimeout(() => {
            card.remove();
            const container = document.getElementById('wishlist-products-container');
            const remaining = container ? container.querySelectorAll('.product-card').length : 0;
            const badge = document.getElementById('wishlist-total-badge');
            if (badge) badge.textContent = remaining + ' sản phẩm';
            const emptyBox = document.getElementById('empty-wishlist');
            if (emptyBox && remaining === 0) {
                emptyBox.style.display = 'block';
            }
        }, 300);
    });
}

// Flag tránh gắn trùng listener nhiều lần
let isWishlistInitialized = false;

async function initWishlist() {
    // Load wishlist IDs on startup
    if (getCurrentCustomer()) {
        // Logged in: sync localStorage → server then load from server
        await syncWishlistToServer();
        try {
            const res = await fetch(appUrl('/api/catalog/yeu_thich.php') + '?type=ids');
            const data = await res.json();
            if (data.status === 'success' && Array.isArray(data.ids)) {
                wishlistIds = new Set(data.ids.map(Number));
                localStorage.setItem('ecrin_wishlist', JSON.stringify([...wishlistIds]));
                updateWishlistBadge(wishlistIds.size);
            }
        } catch (e) {
            console.warn('Could not load wishlist from server:', e);
        }
    } else {
        // Guests cannot keep favorites; account favorites are fetched again after login.
        localStorage.removeItem('ecrin_wishlist');
        wishlistIds = new Set();
        updateWishlistBadge(0);
    }

    // Highlight all heart buttons already on page
    wishlistIds.forEach(id => updateWishlistBtns(id, true));

    if (isWishlistInitialized) return;
    isWishlistInitialized = true;

    // Event delegation for all .wishlist-btn clicks
    document.addEventListener('click', e => {
        const btn = e.target.closest('.wishlist-btn');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();

        const productId = Number(btn.dataset.productId);
        if (!productId) return;

        if (!getCurrentCustomer()) {
            promptCustomerLogin();
            return;
        }

        // Pop animation
        btn.classList.add('animating');
        setTimeout(() => btn.classList.remove('animating'), 400);

        // Favorite changes are stored by the server account only.
        fetch(appUrl('/api/catalog/yeu_thich.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle', product_id: productId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                if (data.favorited) wishlistIds.add(productId);
                else wishlistIds.delete(productId);
                updateWishlistBtns(productId, data.favorited);
                updateWishlistBadge(data.count);
                showToast(data.message);
                saveWishlistToLocal();
                handleWishlistPageRemoval(productId, data.favorited);
            } else if (data.require_login) {
                promptCustomerLogin();
            } else {
                showToast(data.message || 'Có lỗi xảy ra!');
            }
        })
        .catch(() => showToast('Lỗi kết nối. Vui lòng thử lại!'));
    });
}

// ==================== INITIALIZE ALL ====================
document.addEventListener('DOMContentLoaded', async () => {
    console.log('🚀 Website đã sẵn sàng!');

    addPulseAnimation();
    initStickyHeader();
    initSlideshow();
    await initWishlist();
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

    // Đồng bộ lại các nút tim và số lượng yêu thích sau khi tải động
    wishlistIds.forEach(id => updateWishlistBtns(id, true));
    updateWishlistBadge(wishlistIds.size);

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
