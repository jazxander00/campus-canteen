# 🍴 Campus Canteen

A multi-vendor canteen ordering system built with **plain PHP 8 + MySQLi**.
Students browse the campus stalls, place pickup orders and follow the status
live; each stall has its own dashboard for its menu and incoming orders.

> Final project for a Web Systems / Database course. No frameworks, no build
> step — just upload it to `htdocs` and import one SQL file.

---

## Features

**Students**
- Browse all canteen shops and search by name or category
- Per-shop menus, including three serving sizes for milk tea and juice stalls
- Cart stored in the browser, with stock-aware quantity limits
- Place pickup orders split automatically per shop
- Live order tracker (polls every 10 s) and self-service cancellation
- Register, log in, and reset a forgotten password by e-mail token

**Staff**
- Dashboard scoped to their own shop only
- Toggle the shop open / closed
- Add, edit and archive menu items, with image upload
- Live incoming orders with status updates
- Daily sales and completed-order history

**Admin**
- Everything staff can do, across every shop
- Shop switcher on the dashboard

## Built with

| Layer    | Choice                                   |
| -------- | ---------------------------------------- |
| Backend  | PHP 8.2 (MySQLi, prepared statements)    |
| Database | MariaDB 10.4 / MySQL 5.7+                |
| Frontend | Server-rendered HTML, vanilla JS, CSS    |
| Fonts    | System font stack                        |

## Requirements

- [XAMPP](https://www.apachefriends.org/) (or any Apache + PHP 8 + MySQL stack)
- PHP extensions: `mysqli`, `gd`/`fileinfo` (bundled with XAMPP)

## Setup

1. **Copy the project** into your web root so the folder looks like:
   `C:\xampp\htdocs\campus_canteen`

2. **Start Apache and MySQL** from the XAMPP Control Panel.

3. **Create the database**
   - Open <http://localhost/phpmyadmin>
   - *New* → database name `campus_canteen`, collation `utf8mb4_general_ci`
   - Select it → *Import* → choose `database/campus_canteen.sql` → *Go*

   Or from a terminal:

   ```bash
   mysql -u root -e "CREATE DATABASE campus_canteen CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
   mysql -u root campus_canteen < database/campus_canteen.sql
   ```

4. **Check the configuration** in `config.php`.
   The defaults match a fresh XAMPP install (`root`, empty password).
   Every value can be overridden with an environment variable instead:

   ```bash
   set DB_USER=root
   set DB_PASS=secret
   set APP_ENV=production
   set APP_URL=https://canteen.example.com
   ```

5. **Open the site** at <http://localhost/campus_canteen/>.

## Demo accounts

| Role    | Email                 | Password      |
| ------- | --------------------- | ------------- |
| Admin   | `admin@canteen.com`   | `password123` |
| Staff   | `store1@canteen.com`  | `password123` |
| Staff   | `store2@canteen.com`  | `password123` |
| …       | `store3` … `store6`   | `password123` |
| Student | `student@example.com` | `password123` |

**Change these before deploying publicly.** The password-reset mailer uses
PHP's `mail()`, which needs a configured SMTP server; on `localhost` the reset
link is shown on screen instead so the flow can still be tested.

## Project structure

```
campus_canteen/
├── config.php                    # DB credentials + app constants
├── db.php                        # Session + MySQLi bootstrap
├── functions.php                 # CSRF, auth guards, flash messages, helpers
├── header.php / footer.php       # Shared layout
├── index.php                     # Shop list                        (public)
├── menu.php                      # Shop menu                        (public)
├── cart.php                      # Cart + server-side checkout      (student)
├── my_orders.php                 # Order history + cancellation     (student)
├── get_student_orders_ajax.php   # Polling fragment                 (student)
├── login.php / register.php      # Authentication                   (public)
├── forgot_password.php           # Request reset link               (public)
├── reset_password.php            # Consume reset token              (public)
├── logout.php                    # POST + CSRF logout
├── staff_menu.php                # Staff / admin dashboard
├── style.css                     # Single shared stylesheet
├── database/
│   └── campus_canteen.sql        # Schema + demo shops, menus, accounts
├── images/                       # Bundled product and shop photos
└── uploads/                      # Runtime uploads (git-ignored)
```

## How an order is processed

1. The browser keeps a friendly cart in `localStorage`.
2. On checkout the cart (ids, sizes, quantities) is POSTed — **not the prices**.
3. `cart.php` re-reads every item from the database, recalculates the price for
   the chosen size, checks that the shop is open and that stock is sufficient.
4. A transaction inserts one order per shop, its items, and decrements stock
   with `UPDATE … WHERE stock >= ?` so two customers cannot oversell the last
   item.
5. Cancelling an order (student while *Pending*, or staff) puts the stock back.

## Security notes

- Prepared statements everywhere; no string-concatenated SQL with user input.
- CSRF tokens on every state-changing form; destructive actions are POST-only.
- Passwords hashed with `password_hash()` (bcrypt) and rehashed on login.
- Session ID regenerated on login; cookie is `HttpOnly` + `SameSite=Lax`.
- Uploads validated by real image type, size-limited, renamed randomly, and
  the `uploads/` + `images/` folders refuse to execute scripts.
- Login throttling after five failed attempts.
- Password reset uses single-use, hashed, one-hour tokens.
- Database dumps and raw table files are blocked by `.htaccess`.

## License

Released for educational use. The bundled food photographs belong to their
respective owners.
