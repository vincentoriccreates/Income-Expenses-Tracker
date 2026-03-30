# Income & Expenses Tracker
## PHP + MySQL System

---

## 📋 Requirements

- XAMPP (PHP 8.1+ with MySQL/MariaDB)
- Web Browser (Chrome, Firefox, Edge)
- No additional PHP extensions needed (uses PDO MySQL, built-in)

---

## 🚀 Setup Instructions (XAMPP)

### Step 1 — Copy Files
Copy the entire `tracker` folder to:
```
C:\xampp\htdocs\tracker\
```

### Step 2 — Start XAMPP Services
1. Open **XAMPP Control Panel**
2. Start **Apache**
3. Start **MySQL**

### Step 3 — Create the Database
1. Open your browser and go to:
   ```
   http://localhost/phpmyadmin
   ```
2. Click **Import** in the top menu
3. Click **Choose File** and select `income_expensetracker/database.sql`
4. Click **Go** to execute

   **OR** use the SQL tab and paste the contents of `database.sql` manually.

### Step 4 — Configure Database Connection
Open `config.php` and update if needed:
```php
define('DB_HOST', 'localhost');   // Usually 'localhost'
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password (blank for XAMPP default)
define('DB_NAME', 'income_expensetracker'); // Database name
```

### Step 5 — Access the Application
Open your browser and visit:
```
http://localhost/income_expensetracker/
```

---

## 📁 Project Structure

```
income_expensetracker/
├── index.php              # Dashboard (main page)
├── income.php             # All income records
├── expense.php            # All expense records
├── add_income.php         # Add income form
├── add_expense.php        # Add expense form
├── edit_income.php        # Edit income record
├── edit_expense.php       # Edit expense record
├── categories.php         # Manage categories
├── reports.php            # Monthly & weekly reports
├── import.php             # Import CSV data
├── delete.php             # Delete handler
├── config.php             # Database configuration
├── functions.php          # Helper functions
├── database.sql           # Database schema + sample data
├── includes/
│   ├── header.php         # Shared HTML header/sidebar
│   └── footer.php         # Shared HTML footer
├── assets/
│   ├── css/style.css      # Custom styles
│   └── js/app.js          # Custom JavaScript
└── exports/
    ├── export.php          # CSV export handler
    └── template.php        # CSV template downloader
```

---

## 🗄️ Database Tables

| Table | Description |
|---|---|
| `petty_expenses` | Small/petty expense records |
| `hl_expenses` | High/Low (H/L) expense records |
| `income_cash` | Cash income entries (Paid by Cash) |
| `income_card` | Card income entries (Paid by Card) |
| `income_roomcharged` | Room-charged income entries |
| `categories` | Income/expense category definitions |

---

## 📊 Excel Column Mapping

### Expenses Sheet
| Excel Column | Database Column | Table |
|---|---|---|
| DATE | date | petty_expenses / hl_expenses |
| PETTY EXPENSES DESCRIPTION | description | petty_expenses |
| PETTY EXPENSES AMOUNT | amount | petty_expenses |
| Column 1 (week number) | week_number | petty_expenses |
| Column 2 (month) | month | petty_expenses |
| H/L EXPENSES DESCRIPTION | description | hl_expenses |
| H/L EXPENSES AMOUNT | amount | hl_expenses |

### Income Sheet
| Excel Column | Database Column | Table |
|---|---|---|
| DATE | date | income_cash |
| PAID BY CASH | category | income_cash |
| AMOUNT | amount | income_cash |
| DATE | date | income_card |
| PAID BY CARD | category | income_card |
| AMOUNT | amount | income_card |
| DATE | date | income_roomcharged |
| ROOMCHARGED | room_reference | income_roomcharged |
| AMOUNT | amount | income_roomcharged |

---

## ✨ Features

### Dashboard
- Total Income, Total Expenses, Net Balance at a glance
- Cash / Card / Room Charged breakdown
- Monthly Income vs Expenses bar chart
- Income breakdown doughnut chart
- Recent transactions list
- Category summary with progress bars
- Month/Year filter

### Income Management
- View all income (Cash + Card + Room in one view)
- Filter by type, month, date range, search term
- Add / Edit / Delete records
- Export filtered data to CSV

### Expense Management
- Petty Expenses and H/L Expenses tabs
- Same filtering and export capabilities
- Full CRUD operations

### Categories
- Add / Edit / Delete income and expense categories
- Color coding for visual identification

### Reports
- Select any month/year
- See weekly breakdown chart
- Daily summary table with running balance
- Export full month report to CSV

### Import
- Upload CSV to import records in bulk
- Format guide built-in
- Download blank templates

---

## 🔧 Troubleshooting

**Blank page or errors?**
- Make sure Apache and MySQL are running in XAMPP
- Check `config.php` credentials match your MySQL setup
- Enable PHP error display: add `ini_set('display_errors', 1);` at top of `index.php`

**Database import fails?**
- Make sure you've created a database named `income_expensetracker` first in phpMyAdmin
- Try running the SQL in chunks if the file is too large

**CSV import issues?**
- Save your CSV as UTF-8 (in Excel: Save As → CSV UTF-8)
- Make sure the date column is in `YYYY-MM-DD` format
- First row must be the header row

---

## 📞 Support

Built for White Villas Resort (WVR) based on the income_expenseIncome___Expenses_Tracker.xlsx structure.

- Income types: Paid by Cash, Paid by Card, Room Charged
- Expense types: Petty Expenses, H/L Expenses
- Reporting: Weekly, Monthly, Daily summaries

---

*Generated: <?= date('F d, Y') ?>*
"# Income-Expenses-Tracker" 
