# Free deployment guide

This project is a PHP application with a MySQL/MariaDB database. A good free hosting path is:

- Host: InfinityFree, 000webhost, or a similar free PHP + MySQL host
- PHP version: 8.0+
- Database: MySQL

## What to upload
Upload the full project folder to the public root of your host.

## Environment setup
Create the following values in your host's environment or in a .env file:

```env
DB_HOST=your_database_host
DB_PORT=3306
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASS=your_database_password
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=your_email@gmail.com
SMTP_PASSWORD=your_app_password
SMTP_FROM_EMAIL=your_email@gmail.com
SMTP_FROM_NAME=Sofnyas LMS
```

## Database import
Import the supplied database.sql file into your hosted database.

## Notes
- The app will read .env automatically if present.
- For shared hosting, it will use the DB_HOST/DB_NAME/DB_USER/DB_PASS values instead of the local XAMPP socket path.
- If your host does not allow SMTP, email features may need a provider like Gmail or SendGrid.
