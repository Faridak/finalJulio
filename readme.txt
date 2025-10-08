graph TD
    A[Homepage] --> B[Search/Filter Products]
    B --> C[Product Detail Page]
    C --> D[Add to Cart]
    D --> E[Checkout]
    E --> F[Shipping/Banking Selection]
    F --> G[Order Confirmation]
    G --> H[Order Tracking]

Screen	Tech	Features
Homepage	index.php + Tailwind	Hero banner, AI recommendations, "Top Deals" grid
Product Detail	product.php + Alpine.js	Image gallery, color swatches, "Add to Cart" (smooth animation), 5-star ratings
Checkout	checkout.php	Shipping calculator, 3 payment options (mocked), order summary


graph LR
    A[Merchant Login] --> B[Dashboard]
    B --> C[Add New Product]
    C --> D[Upload Images/Details]
    D --> E[Set Price/Inventory]
    E --> F[Product Approval]
    F --> G[Sales Dashboard]

Screen	Tech	Features
Product Creation	merchant/add_product.php	Drag-and-drop image uploader (Dropzone.js), inventory tracking, category selector
Sales Dashboard	merchant/dashboard.php	Real-time sales graph (Chart.js), order management, commission report


graph TD
    A[Admin Login] --> B[Dashboard]
    B --> C[Manage Users]
    C --> D[Verify Merchants]
    B --> E[Monitor Orders]
    E --> F[Refund Requests]
    B --> G[Analytics]
Key Screens (Modern UX)
Screen	Tech	Features
Merchant Approval	admin/merchants.php	List with "Approve/Reject" buttons (AJAX), email notification
Order Management	admin/orders.php	Filter by status (Shipped/Processing), click to view shipping details
Analytics	admin/analytics.php	Interactive charts (Chart.js), revenue by category