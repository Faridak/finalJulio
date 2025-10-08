# VentDepot - E-commerce Marketplace Platform

A comprehensive e-commerce marketplace platform built with PHP, MySQL, and modern web technologies. This platform supports multi-role users (customers, merchants, administrators) with features for product management, order processing, payment integration, and analytics.

## Features

- Multi-role user system (Customers, Merchants, Admins)
- Product catalog with categories and search functionality
- Shopping cart and checkout system
- Payment processing (Stripe, PayPal)
- Order management and tracking
- Merchant dashboard with sales analytics
- Admin panel for site management
- User profiles and account management
- Security features (2FA, CSRF protection, rate limiting)
- REST API for mobile applications
- Content Management System (CMS)
- Real-time notifications with WebSocket support
- Redis caching for improved performance

## Technology Stack

- **Backend**: PHP 8.0+, MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Payment Processing**: Stripe, PayPal
- **Caching**: Redis
- **Security**: OpenSSL, CSRF protection, 2FA
- **Real-time Features**: WebSocket
- **API**: RESTful API for mobile applications
- **Analytics**: Built-in sales and traffic analytics

## Prerequisites

- PHP 8.0 or higher
- MySQL 5.7 or higher
- Apache or Nginx web server
- Composer (for dependency management)
- Redis (for caching)
- SSL certificate (for production)

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/Faridak/finalJulio.git
   ```

2. Navigate to the project directory:
   ```bash
   cd finalJulio
   ```

3. Install PHP dependencies:
   ```bash
   composer install
   ```

4. Create a database and import the schema:
   ```bash
   mysql -u username -p database_name < migrations/001_initial_schema.sql
   ```

5. Copy the environment configuration:
   ```bash
   cp .env.example .env
   ```

6. Configure your environment variables in the `.env` file:
   - Database connection details
   - Payment gateway credentials
   - Email configuration
   - Redis connection details

7. Set proper file permissions:
   ```bash
   chmod -R 755 storage/
   chmod -R 755 public/uploads/
   ```

## Configuration

### Environment Variables

All configuration is handled through environment variables. See [.env.example](.env.example) for all available options.

Key configuration variables:
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` - Database connection
- `STRIPE_KEY`, `STRIPE_SECRET` - Stripe payment processing
- `PAYPAL_CLIENT_ID`, `PAYPAL_SECRET` - PayPal payment processing
- `REDIS_HOST`, `REDIS_PORT` - Redis caching
- `MAIL_DRIVER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` - Email configuration

## Deployment

For detailed deployment instructions to Linode, see [DEPLOYMENT.md](DEPLOYMENT.md).

## Directory Structure

```
├── api/                 # REST API endpoints
├── assets/              # CSS, JavaScript, images
├── config/              # Configuration files
├── controllers/         # Application controllers
├── core/                # Core application files
├── dashboard/           # Admin/merchant dashboards
├── includes/            # Shared utilities and helpers
├── migrations/          # Database migration files
├── models/              # Data models
├── public/              # Publicly accessible files
├── storage/             # Logs, cache, and temporary files
├── templates/           # HTML templates
├── tests/               # Unit and integration tests
└── views/               # View files
```

## Security Features

- Two-Factor Authentication (2FA)
- CSRF protection on all forms
- Rate limiting to prevent abuse
- Password hashing with bcrypt
- SQL injection prevention
- XSS protection
- Secure session management
- Input validation and sanitization

## API Documentation

The platform includes a RESTful API for mobile applications. See [API Documentation](api/README.md) for details.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a pull request

## License

This project is proprietary and not licensed for public use. All rights reserved.

## Support

For support, please contact the development team or open an issue on GitHub.