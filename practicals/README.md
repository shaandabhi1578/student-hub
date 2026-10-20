# StudentHub WDF Practicals (9 to 14)

This folder contains the complete, tested implementations for Web Development Frameworks (WDF) Practicals 9 through 14.

---

### Structure of Practicals Folder

```
practicals/
├── practical-9/               # Secure User Registration
│   ├── register_user.php      # MySQLi insert, duplicate check, password_hash, CSRF
│   ├── register.html          # Registration form with HTML5 & JS validation
│   ├── register.js            # Real-time regex validation & password strength meter
│   └── register.css           # Modern accessible form styling
│
├── practical-10/              # Secure Login/Logout, Sessions & RBAC
│   ├── login.php              # password_verify, session fixation defense, remember-me token
│   ├── logout.php             # Session destruction & cookie revocation
│   ├── auth.php               # Session timeout (15 min) & RBAC middleware
│   ├── login.html             # Login UI with credentials helper
│   └── profile.php            # Dynamic student dashboard
│
├── practical-11/              # Student Management CRUD
│   ├── students.php           # Add, view, edit, delete, search, filter & soft delete
│   └── export_students_csv.php# CSV export endpoint
│
├── practical-12/              # Event Management CRUD & Poster Upload
│   ├── manage_events.php      # Event CRUD, category/status filter, MIME/size validation
│   └── uploads/posters/       # Upload directory protected by .htaccess
│
├── practical-13/              # PHP JSON REST API & Fetch Async CRUD
│   ├── api/students.php       # RESTful API with HTTP status codes (200, 201, 400, 404, 409)
│   ├── api/events.php         # Events JSON API endpoint
│   └── students_ajax.php      # Fetch API single-page CRUD with 300ms debounced search
│
├── practical-14/              # Admin Dashboard, RBAC & Audit Log
│   ├── admin_dashboard.php    # Dynamic SQL aggregations, category chart, status toggle
│   ├── audit_logs.php         # Dedicated security audit log viewer
│   └── toggle_user_status.php # Endpoint for toggling user active/inactive status
│
└── studenthub_db.sql          # Unified MySQL schema & 22+ seeded student records
```

---

### Database Setup

Import the SQL file into MySQL:
```bash
mysql -u root < practicals/studenthub_db.sql
```

### Test User Accounts

| Username | Password | Role | Redirect Target |
|:---|:---|:---|:---|
| `admin` | `Admin@123` | Administrator | `admin_dashboard.php` |
| `shaan` | `Shaan@123` | Student | `profile.php` |
| `student1` | `Student@123` | Student | `profile.php` |
