# Tender Management System

A web-based tender management system that allows organizations to create and manage tenders, while enabling bidders to submit their proposals.

## Features

### Admin Features
- Create and manage tenders
- View and manage bids
- Approve/reject bids
- View statistics and analytics
- Manage tender statuses
- View registered bidders

### Bidder Features
- View available tenders
- Submit bids
- Track bid status
- View tender details and requirements

## Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP (recommended for local development)

## Installation

1. Clone the repository to your local machine:
```bash
git clone [repository-url]
```

2. Import the database:
   - Create a new MySQL database named `tender`
   - Import the database schema from the provided SQL file

3. Configure the database connection:
   - Open `config.php`
   - Update the database credentials:
     ```php
     $servername = "localhost";
     $username = "your_username";
     $password = "your_password";
     $dbname = "tender";
     ```

4. Set up the web server:
   - Place the project files in your web server's root directory (e.g., `htdocs` for XAMPP)
   - Ensure the web server has write permissions for any upload directories

## Project Structure

```
tender/
├── admin/
│   ├── admin-dashboard.php
│   ├── create-tender.php
│   ├── manage-tenders.php
│   └── bidders.php
├── bidder/
│   ├── bidder-dashboard.php
│   ├── view-tenders.php
│   └── submit-bid.php
├── includes/
│   ├── config.php
│   └── functions.php
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
└── README.md
```

## Usage

1. Access the system through your web browser:
   - Admin interface: `http://localhost/tender/admin/admin-dashboard.php`
   - Bidder interface: `http://localhost/tender/bidder/bidder-dashboard.php`

2. Login with appropriate credentials:
   - Admin: Use admin credentials to access the admin dashboard
   - Bidder: Register as a bidder and login to access the bidder dashboard

## Security Features

- User authentication and authorization
- Session management
- Input validation and sanitization
- Password hashing
- CSRF protection

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## Support

For support, please contact [venky23489@gmail.com] or create an issue in the repository. 