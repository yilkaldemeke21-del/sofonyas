# Google Sheets Service Account Setup (PHP)

Follow these steps to enable server-side sync from PHP to Google Sheets using a service account.

1. Enable Google Sheets API
   - Go to https://console.cloud.google.com/apis/library/sheets.googleapis.com and enable the API for your project.

2. Create a Service Account
   - In the Cloud Console go to IAM & Admin → Service Accounts → Create Service Account.
   - Give it a name (e.g., `sofony-exam-sync`).
   - Grant it the role `Project -> Editor` or a more restrictive role that includes Sheets access.

3. Create and download a JSON key
   - After creating the service account, create a JSON key and download it.
   - Save it into your project folder (e.g., `service-account.json`) or somewhere secure.

4. Share the Google Sheet with the service account
   - Open your Google Sheet and share it with the service account email (found in the JSON key) with `Editor` permissions.

5. Install PHP Google API client
   - From your project folder run:

```bash
composer require google/apiclient:^2.0
```

6. Configure environment variables
   - Set these in your web server or `.env` (ensure your app loads them):

```
GOOGLE_SERVICE_ACCOUNT_JSON=/full/path/to/service-account.json
GOOGLE_SHEETS_ID=ABCDEFGHIJK1234567890
GOOGLE_SHEETS_NAME=ExamSubmissions
```

7. Test from the app
   - `submit_exam.php` will automatically use the service account helper if `GOOGLE_SERVICE_ACCOUNT_JSON` and `GOOGLE_SHEETS_ID` are configured.

8. Troubleshooting
   - Check web server error logs for messages emitted by the helper.
   - Ensure the service account has access to the spreadsheet and the JSON path is readable by PHP.
