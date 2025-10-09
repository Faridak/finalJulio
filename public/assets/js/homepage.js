// Homepage Component Manager
class HomepageManager {
    constructor() {
        this.apiUrl = '/api/homepage';
        this.components = {};
        this.init();
    }

    async init() {
        await this.loadHomepageData();
        this.renderHomepage();
    }

    async loadHomepageData() {
        try {
            const response = await fetch(this.apiUrl);
            const result = await response.json();
            
            if (result.success) {
                this.components = result.data;
            } else {
                console.error('Failed to load homepage data:', result.error);
            }
        } catch (error) {
            console.error('Error loading homepage data:', error);
        }
    }

    renderHomepage() {
        // Render components based on layout
        const layout = this.components.layout || [];
        const container = document.getElementById('homepage-container');
        
        if (!container) {
            console.error('Homepage container not found');
            return;
        }
        
        container.innerHTML = '';
        
        layout.forEach(section => {
            const component = this.createComponent(section);
            if (component) {
                container.appendChild(component);
            }
        });
        
        // Show popups if any
        this.showPopups();
    }

    createComponent(section) {
        switch (section.section_type) {
            case 'banner':
                return this.createBannerComponent();
            case 'featured_products':
                return this.createFeaturedProductsComponent();
            case 'popup':
                // Popups are handled separately
                return null;
            case 'cta_button':
                return this.createCtaButtonComponent();
            default:
                return this.createCustomComponent(section);
        }
    }

    createBannerComponent() {
        const banners = this.components.banners || [];
        if (banners.length === 0) return null;

        // For simplicity, we'll use the first banner
        const banner = banners[0];
        
        const bannerElement = document.createElement('div');
        bannerElement.className = 'homepage-banner';
        bannerElement.innerHTML = `
            <div class="banner-image" style="background-image: url('${banner.image_url}')">
                <div class="banner-content">
                    <h2>${banner.title}</h2>
                    ${banner.text_overlay ? `<p>${banner.text_overlay}</p>` : ''}
                    ${banner.button_text ? `
                        <a href="${banner.button_link || '#'}" class="banner-button">
                            ${banner.button_text}
                        </a>
                    ` : ''}
                </div>
            </div>
        `;
        
        return bannerElement;
    }

    createFeaturedProductsComponent() {
        const products = this.components.featured_products || [];
        if (products.length === 0) return null;

        const productsElement = document.createElement('div');
        productsElement.className = 'featured-products';
        productsElement.innerHTML = `
            <h2>Featured Products</h2>
            <div class="products-grid">
                ${products.map(product => `
                    <div class="product-card" data-product-id="${product.product_id}">
                        <img src="${product.product_image || '/assets/images/placeholder.jpg'}" alt="${product.product_name}">
                        <h3>${product.product_name}</h3>
                        <p class="price">$${product.product_price}</p>
                        <button class="add-to-cart" data-product-id="${product.product_id}">Add to Cart</button>
                    </div>
                `).join('')}
            </div>
        `;
        
        return productsElement;
    }

    createCtaButtonComponent() {
        const buttons = this.components.cta_buttons || [];
        if (buttons.length === 0) return null;

        const container = document.createElement('div');
        container.className = 'cta-buttons';
        
        buttons.forEach(button => {
            const buttonElement = document.createElement('a');
            buttonElement.href = button.link;
            buttonElement.className = `cta-button cta-button-${button.button_style || 'primary'}`;
            buttonElement.textContent = button.text;
            container.appendChild(buttonElement);
        });
        
        return container;
    }

    createCustomComponent(section) {
        const customElement = document.createElement('div');
        customElement.className = `custom-section custom-section-${section.section_name}`;
        customElement.innerHTML = `
            <h2>${section.title || section.section_name}</h2>
            ${section.content ? `<div class="section-content">${section.content}</div>` : ''}
        `;
        return customElement;
    }

    showPopups() {
        const popups = this.components.popups || [];
        popups.forEach(popup => {
            this.showPopup(popup);
        });
    }

    showPopup(popup) {
        // Check if popup should be shown (based on show_once setting, etc.)
        if (popup.show_once) {
            const shownPopups = JSON.parse(localStorage.getItem('shownPopups') || '[]');
            if (shownPopups.includes(popup.id)) {
                return; // Don't show if already shown
            }
        }

        const popupElement = document.createElement('div');
        popupElement.className = 'popup-overlay';
        popupElement.innerHTML = `
            <div class="popup-content">
                <span class="popup-close">&times;</span>
                <h3>${popup.title}</h3>
                ${popup.image_url ? `<img src="${popup.image_url}" alt="${popup.title}" class="popup-image">` : ''}
                <div class="popup-text">${popup.content}</div>
                ${popup.product_id ? `
                    <div class="popup-product">
                        <button class="popup-product-button" data-product-id="${popup.product_id}">
                            View Product
                        </button>
                    </div>
                ` : ''}
            </div>
        `;

        document.body.appendChild(popupElement);

        // Add event listeners
        popupElement.querySelector('.popup-close').addEventListener('click', () => {
            popupElement.remove();
            if (popup.show_once) {
                const shownPopups = JSON.parse(localStorage.getItem('shownPopups') || '[]');
                shownPopups.push(popup.id);
                localStorage.setItem('shownPopups', JSON.stringify(shownPopups));
            }
        });

        // Close popup when clicking outside
        popupElement.addEventListener('click', (e) => {
            if (e.target === popupElement) {
                popupElement.remove();
                if (popup.show_once) {
                    const shownPopups = JSON.parse(localStorage.getItem('shownPopups') || '[]');
                    shownPopups.push(popup.id);
                    localStorage.setItem('shownPopups', JSON.stringify(shownPopups));
                }
            }
        });
    }
}

// Admin Panel Component Manager
class AdminHomepageManager {
    constructor() {
        this.apiUrl = '/api/homepage';
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadBanners();
    }

    bindEvents() {
        // Banner form submission
        const bannerForm = document.getElementById('banner-form');
        if (bannerForm) {
            bannerForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createBanner();
            });
        }

        // Layout form submission
        const layoutForm = document.getElementById('layout-form');
        if (layoutForm) {
            layoutForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.updateLayout();
            });
        }
    }

    async loadBanners() {
        try {
            const response = await fetch(`${this.apiUrl}/banners`);
            const result = await response.json();
            
            if (result.success) {
                this.renderBanners(result.data);
            }
        } catch (error) {
            console.error('Error loading banners:', error);
        }
    }

    renderBanners(banners) {
        const container = document.getElementById('banners-list');
        if (!container) return;

        container.innerHTML = banners.map(banner => `
            <div class="banner-item" data-id="${banner.id}">
                <img src="${banner.image_url}" alt="${banner.title}" class="banner-preview">
                <div class="banner-info">
                    <h4>${banner.title}</h4>
                    <p>${banner.text_overlay || ''}</p>
                    <div class="banner-actions">
                        <button class="edit-banner" data-id="${banner.id}">Edit</button>
                        <button class="delete-banner" data-id="${banner.id}">Delete</button>
                    </div>
                </div>
            </div>
        `).join('');

        // Bind edit and delete events
        container.querySelectorAll('.edit-banner').forEach(button => {
            button.addEventListener('click', (e) => {
                const id = e.target.dataset.id;
                this.editBanner(id);
            });
        });

        container.querySelectorAll('.delete-banner').forEach(button => {
            button.addEventListener('click', (e) => {
                const id = e.target.dataset.id;
                this.deleteBanner(id);
            });
        });
    }

    async createBanner() {
        const form = document.getElementById('banner-form');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch(`${this.apiUrl}/banners`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            
            if (result.success) {
                alert('Banner created successfully');
                form.reset();
                this.loadBanners();
            } else {
                alert('Error: ' + result.error);
            }
        } catch (error) {
            console.error('Error creating banner:', error);
            alert('Error creating banner');
        }
    }

    editBanner(id) {
        // In a real implementation, you would load the banner data and populate the form
        alert(`Edit banner ${id} - Implementation needed`);
    }

    async deleteBanner(id) {
        if (!confirm('Are you sure you want to delete this banner?')) {
            return;
        }

        try {
            const response = await fetch(`${this.apiUrl}/banners/${id}`, {
                method: 'DELETE'
            });

            const result = await response.json();
            
            if (result.success) {
                alert('Banner deleted successfully');
                this.loadBanners();
            } else {
                alert('Error: ' + result.error);
            }
        } catch (error) {
            console.error('Error deleting banner:', error);
            alert('Error deleting banner');
        }
    }

    async updateLayout() {
        // In a real implementation, you would collect the layout data from the UI
        // and send it to the API
        alert('Update layout - Implementation needed');
    }
}

// Initialize the appropriate manager based on the page
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('homepage-container')) {
        new HomepageManager();
    } else if (document.getElementById('admin-homepage-panel')) {
        new AdminHomepageManager();
    }
});