# 🛒 VENDO E-Commerce API

RESTful backend API for the **VENDO E-Commerce Platform**, built with Laravel and PostgreSQL.

This API provides the backend services for customer applications and the VENDO admin panel, including authentication, product management, orders, payments, returns, refunds, and exchanges.

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
