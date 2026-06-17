# Notification Features

This project now includes support for:

- Website notifications (in-app)
- Email notifications
- Contact form message emails
- Password reset emails
- Email verification flow
- Certificate notification hooks (ready for future integration)
- Admin dashboard statistics (already present)

## Important setup

1. Copy `.env.example` to `.env`
2. Fill SMTP values with your real email provider details
3. Ensure your PHP environment can send mail

## Suggested next steps

- Add SMS integration later if needed
- Send course reminders and certificates automatically from scheduled jobs
- Add admin UI to manually send emails to selected students
