# Maayi Industries CMS

A PHP + MySQL (PDO) content management system.
Covers requirements **2.1, 2.2, 2.3, 2.4, 2.7, 2.9, 7.1, 7.2, 7.3, 7.4 and 7.5** — 44 marks.

## Setup

1. Copy the `cms` folder into your web root
   (XAMPP: `htdocs/`, MAMP: `htdocs/`, Laragon: `www/`).

2. Import the database: open **phpMyAdmin → Import**, choose `database.sql`, click Go.
   (Or run `mysql -u root -p < database.sql` from a terminal.)

3. If your MySQL username or password is not `root` with a blank password,
   edit the four values at the top of `includes/db.php`.

4. Visit **`http://localhost/cms/setup.php`** once. This creates the admin account.

5. Go to **`http://localhost/cms/`**.
   Log in with **`admin`** / **`admin123`**.

## Where each requirement is met

| Requirement | File | Notes |
|---|---|---|
| **2.1** Create page via HTML form | `admin/page-form.php` | Behind `require_login()` |
| 2.1 At least 10 pages, real data | `database.sql` | 10 real Maayi products |
| **2.2** Edit page | `admin/page-form.php?id=N` | Same file handles create and edit |
| **2.2** Delete page | `admin/page-delete.php` | Confirm prompt before deleting |
| **2.3** List pages, sortable | `admin/pages.php` | `ORDER BY` — MySQL does the sorting |
| 2.3 Sort by 3 columns | title, created_at, updated_at | Click a column heading |
| 2.3 Show the current sort | arrow (↑ / ↓) + a line of text above the table |
| **2.4** Separate categories table | `database.sql` | 1-to-many: `pages.category_id` → `categories` |
| 2.4 Create and update categories | `admin/categories.php` | HTML form |
| 2.4 Assign category to a page | `admin/page-form.php` | `<select>` dropdown |
| **2.7** Navigate pages | `index.php` | Every link generated from the database |
| 2.7 Links open the item | `page.php?id=N` | |
| **2.9** Comment form on each page | `page.php` | Plain text, deliberately not WYSIWYG |
| 2.9 Visitors submit a name | `author_name` field | No login needed to comment |
| 2.9 Comment shows once submitted | saved then redirected back to the page |
| 2.9 Reverse chronological order | `ORDER BY created_at DESC` | Newest first |
| **7.1** Only admins do CUD | `require_admin()` | Members are blocked, not just guests |
| **7.2** View / add / edit / delete users | `admin/users.php`, `admin/user-form.php` | Admin-only |
| **7.3** Hashed + salted passwords | `register.php`, `admin/user-form.php`, `setup.php` | `password_hash()` |
| 7.3 Verified on login | `login.php` | `password_verify()` |
| **7.4** Login via HTML form | `login.php` | Success and failure messages |
| 7.4 Session remembers the user | `$_SESSION` in `includes/functions.php` | |
| 7.4 Log out | `logout.php` | Destroys the session |
| **7.5** Register an account | `register.php` | Username, email, password twice |
| 7.5 Mismatched passwords rejected | `register.php` | Shown an error, asked to retry |

All four admin files call `require_login()` on the first few lines,
so only logged-in users can create, edit, delete, or sort.

## Notes for your demo

**How the sorting is safe (2.3).** The `sort` value from the URL is looked up in a
list of three allowed column names in `admin/pages.php`. Anything else — including
`price` or an attempted SQL injection — falls back to `title`. That is why it is safe
for the column name to go into the query string: it can only ever be one of three
fixed values. Everything else in the app uses prepared statements with bound
parameters.

**Ids from the URL.** `clean_id()` in `includes/functions.php` runs every id through
`filter_var(..., FILTER_VALIDATE_INT)` and rejects anything non-numeric or below 1.

**Escaping output.** The `e()` helper wraps `htmlspecialchars()` and is used on every
piece of database or user text that gets printed, which prevents HTML injection.

## File list

```
cms/
├── index.php               public product list          (2.7)
├── page.php                public product + comments    (2.7, 2.9)
├── login.php  logout.php   admin login
├── setup.php               run once to create the admin
├── database.sql            schema + 10 real products
├── style.css
├── includes/
│   ├── db.php              PDO connection
│   ├── functions.php       login guard, url(), e(), clean_id()
│   ├── header.php  footer.php
└── admin/
    ├── pages.php           sortable list               (2.3)
    ├── page-form.php       create + edit               (2.1, 2.2, 2.4)
    ├── page-delete.php     delete                      (2.2)
    └── categories.php      category create + update    (2.4)
```

## User accounts (requirements 7.1 – 7.5)

### Two roles
- **admin** — can manage pages, categories and other users.
- **member** — can log in and comment, but cannot reach `/admin`.

The first admin is created by `setup.php`. Anyone who registers through
`register.php` becomes a **member**, so registering never grants admin rights.

### 7.1 — how admin-only access is enforced
`includes/functions.php` has two guards:

```php
require_login();   // is anyone logged in at all?
require_admin();   // is the logged-in user an ADMIN?
```

Every file in `/admin` calls **`require_admin()`**. This matters: with only
`require_login()`, a registered member could type the URL of the page editor and
get in. `require_admin()` also checks `$_SESSION['role'] === 'admin'` and
redirects anyone else back to the public site with a message.

The admin links in the navigation bar are also wrapped in `if (is_admin())`,
so members never even see them — but the guard on each file is what actually
enforces it, since hiding a link is not security.

### 7.2 — managing users
`admin/users.php` lists every account with its role. From there an admin can add,
edit or delete users. Two safety rules are built in:
- you cannot delete the account you are currently logged in with
- you cannot delete or demote the **last remaining administrator**
  (otherwise nobody could ever reach the admin area again)

When editing a user, leaving the password fields blank keeps their existing
password rather than blanking it.

### 7.3 — password storage
Passwords are never stored as text. `password_hash($password, PASSWORD_DEFAULT)`
generates a random salt and embeds it in the resulting hash, so the same password
produces a different hash every time. `password_verify()` reads that salt back out
of the stored hash to check a login. Look in the `users` table — the
`password_hash` column holds strings beginning `$2y$`.

### 7.5 — registration
The form asks for a username, an email address, and the password **twice**, both
using `<input type="password">`. If the two do not match, the user is shown
"The two passwords did not match. Please try again." and the form comes back with
their username and email still filled in, so only the passwords need retyping.

## Testing the roles

1. Log in as `admin` / `admin123` — you should see **Manage Pages**,
   **Categories** and **Users** in the navigation.
2. Log out, click **Register**, and create an account (try mismatched passwords
   first to see the error).
3. Log in with that new account — the admin links are gone.
4. Now type `http://localhost/cms/admin/pages.php` directly in the address bar.
   You should be redirected away with "You do not have permission to view that page."
   That is requirement 7.1 working.
