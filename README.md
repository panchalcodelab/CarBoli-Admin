# CarBoli — Auction Platform Admin

CarBoli is the admin and sub-admin control panel for a car-auction marketplace. It is a
Laravel application with a custom Blade + Livewire admin panel served under `/admin`,
covering inventory, customer verification, auctions, payments, inspections, staff, and
role-based access control.

The customer-facing web and mobile apps are out of scope for this repository.

## Tech Stack

- **PHP** ^8.3
- **Laravel** ^13.17
- **Livewire** 4.x (single-file components for all admin screens)
- **Spatie Laravel Permission** ^8.3 (roles and permissions — the source of truth for access)
- **Tailwind CSS** 3.x + Alpine.js 3.x, bundled with **Vite** 8
- **MySQL** (primary database)
- **PHPUnit** 12 for tests, **Laravel Pint** for formatting

## Requirements

- PHP 8.3+ with the usual Laravel extensions
- Composer 2
- Node.js 20+ and npm
- MySQL 8+ (the local setup assumes XAMPP with database `car_boli`, user `root`, empty password)

## Getting Started

```bash
# 1. Install PHP dependencies
composer install

# 2. Create your environment file and app key
cp .env.example .env
php artisan key:generate

# 3. Configure the database in .env (see below), then migrate and seed
php artisan migrate --seed

# 4. Install and build front-end assets
npm install
npm run build
```

### Database configuration

The committed `.env.example` ships with SQLite as a Laravel default, but this project runs
on **MySQL**. Set the following in your `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=car_boli
DB_USERNAME=root
DB_PASSWORD=
```

Create the `car_boli` database before running migrations.

### Seeded Super Admin

`php artisan db:seed` (run automatically by `migrate --seed`) provisions the roles and
permissions and creates a Super Admin account:

- **Email:** `mehulpanchal@gmail.com`
- **Password:** `password`

Change this password immediately outside of local development.

## Running the App

```bash
# Serve the application
php artisan serve           # http://127.0.0.1:8000

# In a separate terminal, run Vite for hot-reloading assets during development
npm run dev
```

Visit `http://127.0.0.1:8000`. There is no public front page — the root redirects to the
login screen, and authenticated admins land on the dashboard at `/admin`.

### Running with Docker

Docker Compose runs PHP-FPM, Nginx, MySQL, and the Vite production asset build:

```bash
# Make sure .env exists and contains APP_KEY
docker compose up --build
```

Open `http://localhost:8000`. The application container runs database migrations
automatically after MySQL becomes healthy. The default Docker database credentials are
`carboli` / `carboli` for the `car_boli` database; these values are supplied to the
application container independently of the host database settings.

Stop the containers with:

```bash
docker compose down
```

Use `docker compose down -v` only when you intentionally want to delete the MySQL and
Laravel storage volumes.

## Admin Panel

All admin screens live under `/admin` and are gated by authentication plus an
`admin.access` middleware (active status + a role that grants panel access). Navigation
items and action buttons are shown only when the signed-in user holds the relevant
permission; the same checks are enforced server-side on every route, Livewire action, and
service method — hiding a control is never the only line of defence.

Modules:

- **Dashboard** — greeting, key stats, recently added vehicles, approval queue, quick actions
- **User Management** — customer accounts, account status (block / unblock)
- **KYC Verification** — review, approve, and reject customer KYC
- **Car Management** — vehicle inventory with a six-step add-car wizard, approval workflow, and delete
- **Live Auctions** — create, schedule, publish, close, cancel, and finalize auctions
- **Participants / Bids / Results** — auction participation and outcomes
- **Payments & Settlements** — read-only record of entry-fee and winning-bid payments
- **Inspections** — schedule and track vehicle inspections
- **Locations** — yard and pickup locations
- **Reports & Analytics** — operational and financial summaries with export
- **Notification Log** — dispatched notification history
- **Audit Log** — append-only record of security-sensitive actions
- **Staff Users** — admin/sub-admin accounts and role assignment
- **Roles & Permissions** — role CRUD with a grouped permission selector

### Roles & Access

Access is driven entirely by Spatie roles and permissions. The seeded roles are:

- **Super Admin** — protected system role with every permission
- **Auction Manager**
- **Car / Inventory Manager**
- **Inspection Staff**
- **Finance Manager**
- **Customer Support**

Super Admin is a protected role: it cannot be assigned through ordinary staff management
(only another Super Admin may grant it), cannot be deleted or renamed through the Roles UI,
and a Super Admin cannot delete their own account.

## Architecture Notes

- **Business logic lives in services** (`app/Services`). Livewire components and controllers
  delegate to them; state transitions and validation are centralized there.
- **Authorization** is enforced through policies and Gates (`app/Policies`) and Spatie
  permissions. Mutations re-check authorization in the service layer.
- **Audit logging** (`AuditLogService`) records actor, action, module, and before/after
  values for sensitive changes; audit logs are append-only.
- **Private storage** — KYC, car, and inspection files are stored on the private `local`
  disk and served only through authenticated download routes, never as public URLs.
- **Admin UI** uses shared Blade components under `resources/views/components/panel`
  (page headers, cards, badges, stat cards, icons, and a reusable confirmation modal).

### Key directories

```
app/
  Filament/            (removed — the panel is now custom Blade + Livewire)
  Models/              Eloquent models
  Policies/            Authorization policies
  Services/            Business logic and workflow services
resources/views/
  components/panel/    Livewire single-file components and shared UI components
  panel/layouts/       Admin shell (sidebar, topbar, navigation)
routes/
  web.php              Root, profile, and authenticated file-download routes
  panel.php            Admin panel routes (prefixed /admin, named panel.*)
  auth.php             Authentication routes (login, password reset — no public registration)
```

## Testing

The project uses **PHPUnit** (not Pest). Most tests are feature tests covering database,
authorization, and workflow behavior.

```bash
# Run the full suite
php artisan test --compact

# Run a single file or filter
php artisan test --compact tests/Feature/RoleManagementTest.php
php artisan test --compact --filter=test_authorized_admin_can_create_a_role_with_permissions
```

## Code Style

Format PHP with Laravel Pint before committing:

```bash
vendor/bin/pint
```

When front-end assets change, rebuild them with `npm run build` (or run `npm run dev`
during development).

## License

Proprietary. All rights reserved.
