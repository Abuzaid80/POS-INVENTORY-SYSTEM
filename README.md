# POS Inventory Sales System

A modern Point of Sale (POS) and Inventory Management System built with PHP, MySQL, and Bootstrap.

## Features

- **User Authentication**
  - Secure login system
  - Session management
  - User roles and permissions

- **Product Management**
  - Add, edit, and delete products
  - Category management
  - Stock tracking
  - Low stock alerts

- **Sales Management**
  - Easy-to-use POS interface
  - Real-time cart management
  - Multiple payment methods
  - Sales history

- **Inventory Management**
  - Automatic stock updates
  - Inventory logs
  - Stock level tracking
  - Low stock notifications

- **Dashboard**
  - Sales overview
  - Top-selling products
  - Low stock alerts
  - Recent activities

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

## Installation

1. Clone or download the repository to your web server directory:
   ```bash
   git clone https://github.com/yourusername/pos-inventory-system.git
   ```

2. Create a new MySQL database:
   ```sql
   CREATE DATABASE pos_inventory;
   ```

3. Import the database structure:
   ```bash
   mysql -u your_username -p pos_inventory < database.sql
   ```

4. Configure the database connection:
   - Open `config/database.php`
   - Update the database credentials:
     ```php
     private $host = "localhost";
     private $db_name = "pos_inventory";
     private $username = "your_username";
     private $password = "your_password";
     ```

5. Set up your web server:
   - For Apache, ensure mod_rewrite is enabled
   - Point your web server to the project directory
   - Set appropriate permissions:
     ```bash
     chmod 755 -R pos-inventory-system/
     chmod 777 -R pos-inventory-system/uploads/  # If you have an uploads directory
     ```

## Default Login

- Username: admin
- Password: admin123

## Usage

1. Access the system through your web browser:
   ```
   http://localhost/pos-inventory-system/
   ```

2. Log in using the default credentials
3. Change the default password
4. Start by adding categories and products
5. Use the POS interface for sales
6. Monitor inventory and sales through the dashboard

## Security Recommendations

1. Change the default admin password immediately
2. Use HTTPS for production environments
3. Regularly backup your database
4. Keep PHP and all dependencies updated
5. Implement rate limiting for login attempts
6. Use strong passwords

## Performance Optimization

The system is designed for fast loading with:
- Minimal database queries
- Efficient indexing
- Client-side caching
- Optimized JavaScript and CSS
- Lazy loading of images
- AJAX for dynamic updates

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, please create an issue in the GitHub repository or contact the development team. 