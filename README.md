# Invoice Maker (Laravel + Vue)

Invoice Maker is a full-stack web application designed to generate,
preview, and export professional invoices in PDF and Excel (XLS/XLSX)
formats.

The system consists of a Laravel 12 backend API and a Vue 3 frontend
interface.

------------------------------------------------------------------------

# 📦 Tech Stack

## Backend

-   PHP 8.2+
-   Laravel 12
-   MySQL / MariaDB
-   barryvdh/laravel-dompdf (PDF generation)
-   phpoffice/phpspreadsheet (Excel XLS/XLSX export)

## Frontend

-   Vue 3
-   Vite
-   Vue Router
-   Axios

------------------------------------------------------------------------

# 📚 Composer Dependencies (Backend)

The following main Composer packages are used:

### Production Dependencies

-   **laravel/framework (\^12.0)**\
    Core Laravel framework providing routing, controllers, ORM,
    middleware, and API architecture.

-   **barryvdh/laravel-dompdf (\^3.1)**\
    Generates PDF invoices from Blade templates.

-   **phpoffice/phpspreadsheet (\^5.4)**\
    Generates Excel (XLS/XLSX) invoice exports programmatically.

-   **laravel/tinker (\^2.10.1)**\
    Interactive REPL for testing application logic.

------------------------------------------------------------------------

# 🏗 Project Structure

invoice-maker/ │ 
               ├── backend/ \# Laravel API 
               ├── frontend/ \# Vue 3 +Vite 
               └── .gitignore

------------------------------------------------------------------------

# 🚀 Installation Guide

## 1️⃣ Backend Setup

cd backend\
composer install\
cp .env.example .env\
php artisan key:generate

Configure database in `.env`:

DB_DATABASE=your_database\
DB_USERNAME=root\
DB_PASSWORD=

Run migrations:

php artisan migrate

Start backend server:

php artisan serve

Backend URL: http://127.0.0.1:8000

------------------------------------------------------------------------

## 2️⃣ Frontend Setup

cd frontend\
npm install\
npm run dev

Frontend URL: http://localhost:5173

------------------------------------------------------------------------

# 🔌 API Base URL

http://127.0.0.1:8000/api

Example endpoints:

-   GET /api/clients
-   GET /api/invoice/preview
-   GET /api/invoice/pdf
-   GET /api/invoice/export-xlsx

------------------------------------------------------------------------

# 📄 PDF & Excel Export

### Install PDF Package

composer require barryvdh/laravel-dompdf

### Install Excel Package

composer require phpoffice/phpspreadsheet

PDF template location: backend/resources/views/invoices/pdf.blade.php

#### For managing routing feature in VUE

npm install vue-router@4

------------------------------------------------------------------------

# 🗄 Database

## Invoices Table (new table for keeping records about loads)

SQL Example:

CREATE TABLE bill_invoiceloads (
    id_bill_invoice INT(11) NOT NULL,
    id_load INT(11) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id_bill_invoice, id_load)
);


------------------------------------------------------------------------

# ⚙️ Production Build

Backend optimization: php artisan config:cache\
php artisan route:cache

Frontend build: npm run build

Production assets output: frontend/dist

------------------------------------------------------------------------

# 📌 Requirements

-   PHP 8.2+
-   Node.js 18+
-   Composer
-   MySQL 8+ / MariaDB

------------------------------------------------------------------------

# 👤 Author

Norayr Kroyan
