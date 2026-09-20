# Maayi Industries — Distributor & Content Platform

A PHP/MySQL web application built for my dad's business, Maayi Industries — a real B2B distributor platform where wholesale customers can browse products, request pricing, and place orders, while staff manage everything from an admin dashboard.

This started as a school project (built to meet a set of instructor-defined requirements) and grew into something my dad's business actually uses.

## What it does

- **Public site** — visitors can browse product pages, categories, and content pages, and search across the catalog
- **Distributor accounts** — registered distributors can log in, view pricing, add products to a cart, and place orders
- **Order management** — staff can view and process incoming orders, and enter offline orders manually
- **Payments & statements** — tracks distributor payment history and generates printable monthly statements
- **Admin dashboard** — manage pages, categories, products (with image uploads), user accounts and roles, distributor approvals, and comments
- **Role-based access** — different permissions for admins vs. distributors vs. regular users
- **Showcase section** — a gallery/portfolio-style page for featuring products or projects

## Tech stack

- **Backend:** PHP (PDO for database access)
- **Database:** MySQL
- **Frontend:** Vanilla JavaScript, CSS
- **Editor:** TinyMCE (WYSIWYG content editing in admin)

## Setup

1. Clone the repo
2. Copy `includes/db.example.php` to `includes/db.php` and fill in your local database credentials
3. Import the database schema
4. Point your local server (e.g. XAMPP) at the project folder and visit `index.php`

## Status

Actively being developed — core storefront, cart, checkout, distributor management, and admin tools are working. Still adding features over time.

**Not live yet.** This is currently a working local build, not yet deployed for real customer use. A few things are still in progress:
- Only 3–4 products currently have 3D models; the rest use placeholder/flat images for now
- Some products are still missing product photos — these will be added as time allows
- Full product catalog and content are being filled in gradually