# Maayi Industries CMS

A PHP + MySQL (PDO) content management system.
Covers requirements **2.1, 2.2, 2.3, 2.4, 2.7, 2.9, 3.1, 3.2, 3.3, 7.1, 7.2, 7.3, 7.4 and 7.5** — 59 marks.

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
| **3.1** Keyword search, LIKE + wildcards | `search.php` | Form is in `includes/header.php`, on every page |
| 3.1 Results are links to pages | `search.php` | |
| **3.2** Restrict search to a category | `search.php` | Dropdown; "All categories" = plain 3.1 |
| **3.3** Paginated results | `search.php` | `LIMIT`/`OFFSET`, `RESULTS_PER_PAGE` constant |

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

## Content search (requirements 3.1 – 3.3)

The search form lives in `includes/header.php`, so it appears at the top of
every page in the site — this satisfies 3.1's requirement that the form be
available everywhere, not just on a dedicated search page.

### 3.1 — how the LIKE query works
`search.php` matches the keyword against both the page title and body with:
```sql
WHERE title LIKE '%keyword%' OR body LIKE '%keyword%'
```
Any `%` or `_` the visitor actually types is escaped with `ESCAPE '!'` first, so
those characters are matched literally instead of acting as SQL wildcards.
Every value is bound as a parameter — the keyword is never concatenated into
the query string.

### 3.2 — the category dropdown
The dropdown is built from the `categories` table plus an "All categories"
option (value `0`). When a real category is chosen, `AND category_id = :cat`
is added to the same query; choosing "All categories" leaves the query exactly
as it is in 3.1. This is still a *page* search — the category only narrows
which pages are considered, it does not search category names.

### 3.3 — pagination
Change the one constant at the top of `search.php` to test with a smaller or
larger page size:
```php
const RESULTS_PER_PAGE = 3;
```
It defaults to 3 so pagination is easy to demonstrate with only 10 seeded
products. The count query runs first to work out how many pages exist, then
`LIMIT`/`OFFSET` fetches just that page's rows. Numbered links plus
Previous/Next appear **only** when there is more than one page — searching
something with 1–3 results shows no pagination bar at all, which is correct.

I verified this against the seed data: searching a broad keyword like `"a"`
returns all 10 products split across 4 pages of 3; searching `"whisky"` returns
exactly 2 results on 1 page (no pagination shown); and searching `"water"`
restricted to the Kombucha category correctly returns 0 results.

## Distributor applications (added feature)

Members can apply to become distributors, and admins review the requests.

New files:
- `apply.php` — the application form (members only; one application per user).
- `admin/requests.php` — admin page to approve/reject pending applications.

Modified files:
- `includes/functions.php` — added `distributor_status()`.
- `includes/header.php` — added the "Become a distributor" nav link, the
  "Applications" admin link, and the status banner shown once a user applies.
- `style.css` — added `.status-banner` styles.
- `database.sql` — added the distributors / pricing / orders / order_items /
  payments tables.

Setup:
- Fresh install: import `database.sql` (it drops and recreates everything).
- Existing database with data you want to keep: import `add_commerce_tables.sql`
  instead — it only adds the new tables and touches nothing else.

Status labels: the database stores `pending` / `approved` / `rejected`; the
site displays these as Pending / Active / Deactivated.

## Staff / distributor separation (update)

- **Users admin is now "Staff"** (`admin/users.php`) — shows company staff
  accounts only. Roles come from the new `roles` table.
- **Roles** (`admin/roles.php`, admin only) — create and name staff roles
  such as Accountant. Built-in roles cannot be deleted.
- **Distributors** (`admin/distributors.php`, staff: admin + accountant) —
  a list of all distributors; click one to open `admin/distributor-view.php`
  which shows full details, approve/reject for pending applications, and the
  per-product pricing table (regular price shown for reference).
- The old `admin/requests.php` and `admin/pricing.php` now redirect into the
  Distributors area.

Run `add_roles_table.sql` on an existing database (or re-import `database.sql`
for a fresh install) so the `roles` table exists.

## Ordering flow (added)

Approved distributors:
- Add products to a **session cart** from any product page; the cart persists
  as they browse and is reachable from the nav (`cart.php`).
- **Checkout** (`checkout.php`) places a credit order: status `pending`,
  line prices snapshotted from their distributor pricing.
- **My Orders** (`my-orders.php`) shows their orders and balance owed, with a
  printable **receipt/invoice** (`receipt.php`) available any time.

Staff (admin + accountant):
- **Orders** (`admin/orders.php`) lists all orders. Pending orders can be
  **Confirmed** or **Cancelled**; a note is REQUIRED to cancel.
- Confirming an order is what adds its total to the distributor's balance.

Balance owed = SUM(confirmed orders) − SUM(payments recorded). Derived on read.

New column: `orders.cancel_note`. Run `add_order_cancel_note.sql` on an
existing database (or re-import `database.sql` for a fresh install).

## Payments (accountant)

- **Payments** (`admin/payments.php`, staff: admin + accountant) lists approved
  distributors with the amount each owes. Choose one to see the balance
  breakdown (confirmed orders − payments), record a payment (amount, date,
  optional note), and view payment history.
- Recording a payment reduces the distributor's owed balance immediately.
  Overpayment shows as a negative balance (credit).
- The distributor's detail page (`admin/distributor-view.php`) now shows the
  balance and a shortcut to record a payment.

## Contextual search (update)

The single header search box now adapts to the current page:
- Distributors page -> searches distributors (business, contact, login, phone)
- Orders page -> searches orders (distributor name, status, order number)
- Staff page -> searches staff (username, email, role)
- Payments page -> searches distributors
- Everywhere else -> searches products

Roles page now lists staff roles only (member/distributor are account types
kept in the roles table but not shown as manageable roles).

Note: 'member' and 'distributor' are built-in account types, not clutter —
registration assigns 'member', approval assigns 'distributor'. They cannot be
deleted. Forgot-password is planned for a later step.


## Product images & the role-aware home page

**Images.** Admins upload a product image when creating a product, and can
replace or remove it any time from the edit form (upload a new file to replace;
tick "Remove this image" to delete). Uploads are validated with `getimagesize()`
— a script renamed to .jpg is rejected before it touches disk or database —
resized by PHP GD to max 800px on the longest side, stored in `/uploads` with
generated names, and recorded in the `images` table.

**If you already have data:** run `add_images_table.sql` in phpMyAdmin once
instead of re-importing `database.sql` (which would wipe your data).
Make sure the `uploads/` folder exists and is writable.

**Home page.** One `index.php`, three experiences:
- **Guests / plain members** — a product gallery with images and description
  teasers, NO prices (proposal rule 1), plus a register/apply nudge.
- **Approved distributors** — an Amazon-style storefront: their own prices on
  every tile, quantity + Add-to-cart buttons, a category filter bar, and a
  cart summary panel at the top of the page with a checkout shortcut.
- Both audiences get the category filter chips.
