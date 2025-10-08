# SEO Management Module Documentation

## Overview
The SEO Management Module allows merchants and administrators to optimize product listings for search engines and social media sharing. It includes features for managing meta tags, Open Graph tags, Twitter Cards, and structured data.

## Features
1. **Meta Tags Management**
   - Custom meta titles and descriptions
   - Keyword optimization
   - Canonical URLs
   - Robots directives

2. **Social Media Optimization**
   - Open Graph tags for Facebook sharing
   - Twitter Cards for Twitter sharing
   - Custom images for social media previews

3. **Structured Data**
   - Schema.org markup for rich snippets
   - Product structured data
   - Review aggregate data

4. **Admin Controls**
   - Bulk SEO updates
   - SEO status monitoring
   - Product-level SEO editing

## Database Structure

### Updated Products Table
New columns added to the existing products table:
- `meta_title` (VARCHAR 255) - Custom meta title
- `meta_description` (TEXT) - Custom meta description
- `meta_keywords` (TEXT) - Meta keywords
- `og_title` (VARCHAR 255) - Open Graph title
- `og_description` (TEXT) - Open Graph description
- `og_image` (VARCHAR 255) - Open Graph image URL
- `og_type` (VARCHAR 50) - Open Graph type (default: product)
- `twitter_title` (VARCHAR 255) - Twitter card title
- `twitter_description` (TEXT) - Twitter card description
- `twitter_image` (VARCHAR 255) - Twitter card image URL
- `canonical_url` (VARCHAR 255) - Canonical URL
- `robots` (VARCHAR 100) - Robots meta tag (default: index,follow)

### Product SEO Table
New table for advanced SEO features:
- `id` (INT) - Primary key
- `product_id` (INT) - Reference to products table
- `schema_type` (VARCHAR 50) - Schema.org type (default: Product)
- `schema_data` (JSON) - Custom schema.org data
- `social_media_templates` (JSON) - Custom social media templates
- `created_at`, `updated_at` - Timestamps

## Setup Instructions

1. Run the setup script:
   ```
   php setup-seo-module.php
   ```

2. Access the SEO management interface:
   Navigate to `/admin/seo-management.php`

## Usage

### Merchant Product Creation/Editing
1. When adding or editing a product, merchants can now:
   - Set custom meta titles and descriptions
   - Define meta keywords
   - Customize Open Graph tags for social sharing
   - Configure Twitter Card settings

### Admin SEO Management
1. Go to the SEO Management page
2. View SEO status of all products (Complete/Partial/Missing)
3. Click "Edit SEO" to modify individual product settings
4. Use bulk operations to apply SEO settings to multiple products

### Frontend Display
1. Product pages now include:
   - Proper meta tags for search engines
   - Open Graph tags for social media sharing
   - Twitter Card meta tags
   - Schema.org structured data for rich snippets

## API Endpoints

### Get Product SEO Data
```
GET /admin/api/seo-api.php?action=get_product_seo&product_id={id}
```

### Update Product SEO Data
```
POST /admin/api/seo-api.php?action=update_product_seo
```
Parameters:
- product_id (required)
- meta_title
- meta_description
- meta_keywords
- og_title
- og_description
- og_image
- twitter_title
- twitter_description
- twitter_image

### Get SEO Statistics
```
GET /admin/api/seo-api.php?action=get_seo_statistics
```

## Best Practices

### Meta Tags
- Keep meta titles between 50-60 characters
- Keep meta descriptions between 150-160 characters
- Use relevant keywords naturally
- Avoid keyword stuffing

### Social Media Tags
- Use high-quality images (1200x630px for Open Graph)
- Keep Open Graph descriptions under 300 characters
- Keep Twitter descriptions under 200 characters
- Test social media previews before publishing

### Structured Data
- Validate structured data with Google's Rich Results Test
- Include all required properties for Product schema
- Update structured data when product information changes

## Troubleshooting

### Common Issues
1. **Missing SEO Data**: If SEO fields are empty, default values are generated from product information
2. **Social Media Previews**: If previews don't appear correctly, check image URLs and dimensions
3. **Structured Data Errors**: Validate with Google's tools and check for missing required fields

### Testing
1. Use Google Search Console to test meta tags
2. Use Facebook's Sharing Debugger to test Open Graph tags
3. Use Twitter's Card Validator to test Twitter Cards
4. Use Google's Rich Results Test to validate structured data

## Future Enhancements
1. Automated SEO suggestions based on product content
2. SEO performance tracking and reporting
3. Integration with Google Search Console API
4. Advanced schema.org markup options
5. A/B testing for meta tags