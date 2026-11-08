# 🛒 VENDO E-Commerce API

A RESTful backend API for the **VENDO E-Commerce Platform**, built with **Laravel** and **PostgreSQL**.

This API provides backend services for customer applications and the VENDO admin panel, including authentication, user management, product management, orders, payments, returns, refunds, and product exchanges.

---

## 🚀 Features

- 🔐 User Authentication & Authorization
- 👤 User Management
- 🛍️ Product Management
- 📂 Category Management
- 🏷️ Brand Management
- 🛒 Shopping Cart
- 📦 Order Management
- 💳 Payment Management
- 🔄 Return Management
- 💰 Refund Management
- 🔁 Product Exchange
- 📍 Address Management
- 🎟️ Discount Management
- 📊 Order & Revenue Statistics
- 🔒 API Validation & Security

---

## 🛠️ Tech Stack

- **Framework:** Laravel
- **Language:** PHP
- **Database:** PostgreSQL
- **API Architecture:** RESTful API

---

## 🏗️ Project Architecture

```text
laravel-api/
│
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Resources/
│   │
│   ├── Models/
│   └── Services/
│
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
│
├── routes/
│   ├── api.php
│   └── web.php
│
├── config/
├── storage/
├── tests/
├── .env.example
├── artisan
└── composer.json
```

---

## ⚙️ Installation & Setup

### Prerequisites

Make sure you have installed:

- PHP
- Composer
- PostgreSQL
- Git

### 1. Clone the repository

```bash
git clone https://github.com/YOUR-USERNAME/YOUR-REPOSITORY.git
cd laravel-api
```

### 2. Install dependencies

```bash
composer install
```

### 3. Configure environment

```bash
cp .env.example .env
```

Update your `.env` file with your PostgreSQL database credentials.

```env
APP_NAME=VENDO
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 4. Generate application key

```bash
php artisan key:generate
```

### 5. Run migrations

```bash
php artisan migrate
```

### 6. Start the development server

```bash
php artisan serve
```

The API will be available at:

```text
http://localhost:8000
```

---

## 🔑 Environment Variables

| Variable | Description |
|---|---|
| `APP_NAME` | Application name |
| `APP_ENV` | Application environment |
| `APP_KEY` | Laravel application key |
| `APP_DEBUG` | Debug mode |
| `APP_URL` | Application URL |
| `DB_CONNECTION` | Database driver |
| `DB_HOST` | Database host |
| `DB_PORT` | Database port |
| `DB_DATABASE` | Database name |
| `DB_USERNAME` | Database username |
| `DB_PASSWORD` | Database password |

---

## 🔒 Security

- Request validation
- Authentication and authorization
- Protected API routes
- Environment-based configuration
- Secure handling of sensitive credentials

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).

You are free to use, copy, modify, distribute, and use this software for commercial purposes, subject to the terms of the MIT License.
