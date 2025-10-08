# Pricing Management Module Documentation

## Overview
The Pricing Management Module allows administrators to manage product pricing, create discounts, and set up promotional campaigns. It includes features for identifying products with low sales and adjusting prices accordingly.

## Features
1. **Price Management**
   - View all products with their current pricing
   - Update base prices for products
   - Track price history

2. **Discount Management**
   - Create percentage or fixed amount discounts
   - Set start and end dates for discounts
   - Apply discounts to individual products

3. **Promotion Management**
   - Create promotional campaigns (bulk, bundle, seasonal, holiday)
   - Apply promotions to multiple products
   - Set time-based promotions

4. **Sales Analytics**
   - Identify products with no sales in the last 90 days
   - Highlight low-performing products
   - Seasonal pricing adjustments

## Database Structure

### product_pricing
Stores base pricing information for products:
- `id`: Primary key
- `product_id`: Reference to products table
- `base_price`: Original product price
- `current_price`: Current selling price
- `cost_price`: Product cost for margin calculations
- `currency`: Currency code (default USD)
- `created_at`, `updated_at`: Timestamps

### product_discounts
Manages product-specific discounts:
- `id`: Primary key
- `product_id`: Reference to products table
- `discount_type`: 'percentage' or 'fixed_amount'
- `discount_value`: Discount amount
- `start_date`, `end_date`: Discount period
- `is_active`: Discount status
- `created_at`, `updated_at`: Timestamps

### product_promotions
Manages promotional campaigns:
- `id`: Primary key
- `name`: Promotion name
- `description`: Promotion description
- `promotion_type`: 'bulk', 'bundle', 'seasonal', 'holiday'
- `discount_type`: 'percentage' or 'fixed_amount'
- `discount_value`: Discount amount
- `start_date`, `end_date`: Promotion period
- `is_active`: Promotion status
- `created_at`, `updated_at`: Timestamps

### promotion_products
Links products to promotions:
- `id`: Primary key
- `promotion_id`: Reference to product_promotions
- `product_id`: Reference to products table

### seasonal_pricing
Manages seasonal price adjustments:
- `id`: Primary key
- `product_id`: Reference to products table
- `season`: Season identifier
- `price_multiplier`: Price adjustment multiplier
- `fixed_price`: Fixed seasonal price
- `start_date`, `end_date`: Seasonal period
- `is_active`: Seasonal pricing status
- `created_at`, `updated_at`: Timestamps

### price_history
Tracks price changes:
- `id`: Primary key
- `product_id`: Reference to products table
- `old_price`: Previous price
- `new_price`: New price
- `reason`: Reason for change
- `changed_by`: User who made the change
- `changed_at`: Timestamp of change

## Setup Instructions

1. Run the setup script:
   ```
   php setup-pricing-module.php
   ```

2. Access the pricing management interface:
   Navigate to `/admin/pricing-management.php`

## Usage

### Managing Prices
1. Go to the Pricing Management page
2. Find the product you want to update
3. Click the "Price" button
4. Enter the new price and reason for change
5. Click "Update Price"

### Creating Discounts
1. Go to the Pricing Management page
2. Find the product you want to discount
3. Click the "Discount" button
4. Select discount type (percentage or fixed amount)
5. Enter discount value
6. Set start and end dates
7. Click "Create Discount"

### Creating Promotions
1. Go to the Pricing Management page
2. Click "Create Promotion" button
3. Enter promotion details:
   - Name and description
   - Promotion type (bulk, bundle, seasonal, holiday)
   - Discount type and value
   - Select products to include
   - Set start and end dates
4. Click "Create Promotion"

## Seasonal Pricing Examples

### New Year's Eve Pricing
- Promotion Type: Seasonal
- Discount: 25% off selected products
- Dates: December 20 - January 5

### Mother's Day Pricing
- Promotion Type: Holiday
- Discount: 20% off gift items
- Dates: May 1 - May 15

## Identifying Low-Performing Products

The system automatically highlights products with:
- No sales in the last 90 days (shown with yellow alert)
- Low sales volume (less than 5 units in 90 days)

These products are prioritized for price adjustments to improve sales performance.

## Reports and Analytics

The dashboard shows:
- Total products
- Products with no recent sales
- Active promotions
- Active discounts

Use this information to make informed pricing decisions and optimize product performance.