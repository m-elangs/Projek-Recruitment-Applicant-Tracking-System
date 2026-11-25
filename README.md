# TalentFlow ATS — Recruitment & Applicant Tracking System

TalentFlow is a full-stack, production-minded **Recruitment & Applicant Tracking System (ATS)** designed to manage the complete enterprise hiring lifecycle—from hiring manager job requisitions and public job listings to 9-stage candidate pipelines, multi-interviewer scorecards, job offers, real-time analytics, and immutable audit logs.

---

## Architecture & Tech Stack

- **Frontend (`frontend/`)**: React 18, TypeScript, Vite, Tailwind CSS, React Router v6, TanStack Query v5, React Hook Form + Zod, Recharts, Lucide React, Vitest + React Testing Library.
- **Backend (`backend/`)**: PHP 8.1+, Laravel 10, Laravel Sanctum (Stateful SPA Cookie + Bearer Token), Service Layer architecture, Form Requests, Laravel Policies, API Resources, Notifications, Queues (`queue_jobs`), PHPUnit.
- **Database**: Normalized MySQL 8.0+ (24 tables in 3NF, foreign keys, `CHECK` constraints, virtual active-application lock column, stored generated evaluation averages, composite & full-text indexes).
- **Documentation (`docs/`)**: OpenAPI 3.1 spec (`docs/openapi.yaml`) and Postman Collection (`docs/postman_collection.json`).

---

## Demo Accounts (Seeded Automatically)

Password for all seeded demo accounts: **`Password123!`**

| Role | Email | Default Workspace |
| :--- | :--- | :--- |
| **System Administrator** | `admin@talentflow.local` | `/dashboard` (Full Governance, Users, Roles, Audit Logs) |
| **Recruiter** | `recruiter@talentflow.local` | `/dashboard` (Jobs, Candidates, Kanban Pipeline, Interviews, Offers) |
| **Hiring Manager** | `hm.engineering@talentflow.local` | `/dashboard` (Requisitions, Assigned Jobs, Evaluations, Offer Approval) |
| **Candidate** | `candidate@talentflow.local` | `/portal/dashboard` (Careers, Profile & CV, Applications, Offer Response) |

---

## Cara Menjalankan Aplikasi (Local Setup & Run Guide)

### 1. Persiapan Database MySQL
1. Pastikan MySQL 8.0 (misalnya melalui Laragon / XAMPP / MySQL Server) sedang berjalan di `127.0.0.1:3306`.
2. Buat database kosong bernama `talentflow_ats` (via HeidiSQL, phpMyAdmin, atau CLI):
   ```sql
   CREATE DATABASE talentflow_ats CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Buka file `backend/.env` dan sesuaikan kredensial MySQL Anda:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=talentflow_ats
   DB_USERNAME=root
   DB_PASSWORD=password_mysql_anda
   ```

### 2. Menjalankan Backend Laravel API
Buka terminal pertama di folder `backend/`:
```bash
cd backend
php artisan migrate:fresh --seed
php artisan serve --port=8000
```
API akan aktif di `http://localhost:8000/api/v1` (cek cepat: `http://localhost:8000/api/v1/health`).

### 3. Menjalankan Frontend React + Vite
Buka terminal kedua di folder `frontend/`:
```bash
cd frontend
npm run dev
```
Aplikasi web akan aktif di **`http://localhost:5173`**.
- Halaman Publik Careers: `http://localhost:5173/careers`
- Halaman Login (dengan tombol *Quick-Fill Demo Accounts*): `http://localhost:5173/login`

---

## Menjalankan Automated Tests

### Backend PHPUnit Suite (12 tests, 116 assertions)
```bash
cd backend
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/phpunit/phpunit/phpunit
```

### Frontend TypeScript Build & Vitest Suite
```bash
cd frontend
npm run build
npm test
```

---

## Key Security & Engineering Highlights
- **Strict Backend RBAC + Policy Scoping**: Every endpoint enforces permission slugs via middleware and record ownership/assignment via Laravel Policies (`JobPolicy`, `CandidatePolicy`, `CandidateDocumentPolicy`, `ApplicationPolicy`, `InterviewPolicy`, `JobOfferPolicy`).
- **Private CV Vault**: Uploaded resumes are stored outside `public/` on the `private` disk (`storage/app/private/candidates/{id}/`) and streamed exclusively via policy-authorized downloads.
- **Database-Enforced Pipeline Integrity**: Duplicate active applications are blocked both transactionally (`lockForUpdate`) and at the MySQL engine level via a generated virtual lock index. Every stage transition is validated against a 9-stage state machine and recorded in `application_histories` + `activity_logs`.
