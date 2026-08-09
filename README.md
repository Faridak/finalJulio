# VentDepot E-commerce Platform

A comprehensive e-commerce marketplace platform similar to AliExpress, built with PHP and modern web technologies.

## Features

- Product catalog with search and filtering
- Shopping cart and checkout system
- User authentication and profiles
- Merchant/vendor dashboard
- Admin panel for platform management
- Payment integrations (Stripe, PayPal)
- Order management and tracking
- Reviews and ratings system
- Flash deals and promotional features
- Responsive design with Tailwind CSS

## Technology Stack

- **Backend**: PHP 8.0+
- **Frontend**: HTML5, CSS3 (Tailwind CSS), JavaScript
- **Database**: MySQL
- **Dependencies**: Managed via Composer
- **Environment**: Configured with PHP Dotenv

## Installation

### Prerequisites

- PHP 8.0 or higher
- MySQL database
- Composer (PHP dependency manager)
- Web server (Apache/Nginx)

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd finalJulio
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   ```
   Edit the `.env` file to set your database credentials and other configuration values.

4. **Set up the database**
   - Create a database (e.g., `ventdepot`)
   - Import the schema using migration files in the `migrations/` directory
   - Or run any provided setup scripts

5. **Configure web server**
   - Point your web server to the `public/` directory
   - Ensure `.htaccess` files are enabled (for Apache)

6. **Set file permissions**
   ```bash
   chmod -R 755 storage/
   chmod -R 755 public/uploads/
   ```

7. **Access the application**
   Visit your configured URL in a web browser.

## Configuration

The application uses environment variables stored in `.env`. Key configuration sections include:

- **Application Settings**: App name, environment, debug mode, URL
- **Database**: Connection details for MySQL
- **Redis**: Caching and session storage
- **Payment Gateways**: Stripe and PayPal API keys
- **Email**: SMTP configuration for transactional emails
- **Security**: Session lifetime, rate limiting, salt
- **File Storage**: Upload handling
- **Third-party Services**: AWS, Pusher for notifications

## Directory Structure

```
finalJulio/
├── admin/                # Admin panel
├── api/                  # API endpoints
├── classes/              # Core business logic
├── config/               # Configuration files
├── cron/                 # Scheduled tasks
├── includes/             # Helper functions
├── merchant/             # Merchant/vendor dashboard
├── migrations/           # Database schema migrations
├── public/               # Web-accessible assets
├── src/                  # Main application source (PSR-4 autoloaded)
├── storage/              # Storage for logs, cache, sessions
├── tests/                # Unit and feature tests
├── vendor/               # Composer dependencies (gitignored)
├── websockets/           # Real-time functionality
└── webhooks/             # External service webhooks
```

## Development

### Coding Standards

- Follow PSR-12 coding standards
- Use meaningful variable and function names
- Comment complex logic
- Follow existing code patterns in the project

### Running Tests

```bash
# Run PHPUnit tests
vendor/bin/phpunit

# Check code style
vendor/bin/phpcs

# Fix code style issues
vendor/bin/phpcbf
```

### Database Migrations

Migration files are located in the `migrations/` directory. Apply them using your preferred MySQL client or migration tool.

## API Endpoints

The platform provides RESTful API endpoints under the `/api/` directory. Refer to the `api/README.md` for detailed documentation.

## Security

- Environment variables protect sensitive configuration
- Input validation and sanitization throughout
- Prepared statements for database queries
- CSRF protection on forms
- Secure password hashing
- Rate limiting on authentication endpoints

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is proprietary software. All rights reserved.

## Support

For support, please contact the development team at development@ventdepot.com