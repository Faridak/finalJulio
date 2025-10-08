# VentDepot API Documentation

This document provides information about the VentDepot REST API for mobile applications and third-party integrations.

## Authentication

All API requests require authentication using an API key. Include your API key in the header of each request:

```
Authorization: Bearer YOUR_API_KEY
Content-Type: application/json
```

## Base URL

```
https://yourdomain.com/api/v1
```

## Endpoints

### Authentication

#### POST /auth/login
Authenticate a user and obtain an access token.

**Request:**
```json
{
  "email": "user@example.com",
  "password": "user_password"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "id": 1,
      "username": "johndoe",
      "email": "user@example.com",
      "role": "customer"
    }
  }
}
```

#### POST /auth/register
Register a new user.

**Request:**
```json
{
  "username": "johndoe",
  "email": "user@example.com",
  "password": "secure_password",
  "first_name": "John",
  "last_name": "Doe"
}
```

### Products

#### GET /products
Retrieve a list of products with optional filtering.

**Parameters:**
- `category_id` (optional): Filter by category
- `search` (optional): Search term
- `limit` (optional, default: 20): Number of products to return
- `offset` (optional, default: 0): Offset for pagination

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Product Name",
      "slug": "product-name",
      "short_description": "Brief description",
      "price": 29.99,
      "compare_price": 39.99,
      "image": "https://yourdomain.com/images/product.jpg",
      "category": {
        "id": 1,
        "name": "Category Name"
      }
    }
  ],
  "pagination": {
    "total": 100,
    "limit": 20,
    "offset": 0
  }
}
```

#### GET /products/{id}
Retrieve details for a specific product.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Product Name",
    "slug": "product-name",
    "description": "Detailed product description",
    "short_description": "Brief description",
    "price": 29.99,
    "compare_price": 39.99,
    "sku": "PROD-001",
    "quantity": 100,
    "images": [
      {
        "id": 1,
        "url": "https://yourdomain.com/images/product1.jpg",
        "alt": "Product Image 1"
      }
    ],
    "category": {
      "id": 1,
      "name": "Category Name"
    },
    "merchant": {
      "id": 1,
      "name": "Merchant Name",
      "rating": 4.5
    }
  }
}
```

### Shopping Cart

#### GET /cart
Retrieve the current user's shopping cart.

**Response:**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "product_id": 1,
        "product_name": "Product Name",
        "product_image": "https://yourdomain.com/images/product.jpg",
        "quantity": 2,
        "price": 29.99,
        "total": 59.98
      }
    ],
    "subtotal": 59.98,
    "item_count": 2
  }
}
```

#### POST /cart
Add an item to the shopping cart.

**Request:**
```json
{
  "product_id": 1,
  "quantity": 2
}
```

#### PUT /cart/{id}
Update the quantity of an item in the cart.

**Request:**
```json
{
  "quantity": 3
}
```

#### DELETE /cart/{id}
Remove an item from the cart.

### Orders

#### GET /orders
Retrieve the current user's order history.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "order_number": "ORD-001",
      "status": "delivered",
      "total_amount": 89.97,
      "created_at": "2023-06-15 14:30:00",
      "items": [
        {
          "id": 1,
          "product_name": "Product Name",
          "quantity": 2,
          "price": 29.99
        }
      ]
    }
  ]
}
```

#### GET /orders/{id}
Retrieve details for a specific order.

#### POST /orders
Create a new order from the shopping cart.

**Request:**
```json
{
  "shipping_address_id": 1,
  "billing_address_id": 1,
  "payment_method": "stripe"
}
```

### User Profile

#### GET /user/profile
Retrieve the current user's profile information.

#### PUT /user/profile
Update the current user's profile information.

#### GET /user/addresses
Retrieve the current user's addresses.

#### POST /user/addresses
Add a new address for the current user.

#### PUT /user/addresses/{id}
Update an existing address.

#### DELETE /user/addresses/{id}
Delete an address.

## Error Responses

All error responses follow this format:

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human readable error message"
  }
}
```

Common error codes:
- `UNAUTHORIZED`: Authentication failed or missing
- `FORBIDDEN`: Insufficient permissions
- `NOT_FOUND`: Resource not found
- `VALIDATION_ERROR`: Request data failed validation
- `SERVER_ERROR`: Internal server error

## Rate Limiting

The API implements rate limiting to prevent abuse. Each API key is limited to 1000 requests per hour. Exceeding this limit will result in a 429 (Too Many Requests) response.

## Webhooks

The platform supports webhooks for real-time notifications. You can configure webhook URLs in the merchant dashboard to receive notifications for events such as:
- New orders
- Order status changes
- Payment confirmations
- Product updates

Webhook payloads will be sent as POST requests with a JSON body containing event details.

## Support

For API support, please contact the development team or refer to the main documentation.
