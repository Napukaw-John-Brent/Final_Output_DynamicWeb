# 💰 SmartBudget — Personal Budget Tracker

A web-based personal budget tracking application built with **PHP**, **MySQL**, and **Docker**.  
Developed as a final project for Dynamic Web (DWEB) at Holy Angel University.

---

## ✨ Features

- 🔐 User registration, login, and security PIN
- 📊 Monthly budget setup with category allocation
- 💸 Expense logging and tracking
- 📈 Dashboard with spending stats and charts
- 🧾 QR code saver for deals
- 👤 User profile with avatar upload
- 🌙 Dark/light theme support

---

## 🚀 Quick Setup (Docker)

### Prerequisites
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) installed and running

### Steps

```bash
# 1. Clone the repository
git clone https://github.com/YOUR_USERNAME/YOUR_REPO_NAME.git

# 2. Navigate into the project folder
cd YOUR_REPO_NAME

# 3. Build and start the containers
docker-compose up -d
```

> ⏳ First run may take 1–2 minutes while Docker pulls images and MySQL initializes.

### 4. Open the app in your browser

```
http://localhost:8080
```

That's it! 🎉

---

## 🔑 Test Accounts

You can register your own account, or use these pre-seeded credentials:

| Email | Password |
|---|---|
| user1@gmail.com | user12345 |
| user2@gmail.com | test12345 |

---

## 🛑 Stopping the App

```bash
docker-compose down
```

To also delete the database volume (full reset):

```bash
docker-compose down -v
```

---

## 🗂️ Project Structure

```
├── auth/              # Login, register, password reset, security PIN
├── config/            # Database connection and app config
├── css/               # Stylesheets
├── dashboard/         # Main dashboard page
├── deals/             # QR saver and stats
├── images/            # Static image assets
├── includes/          # Shared header/footer
├── js/                # JavaScript files
├── uploads/           # User-uploaded avatars
├── budget_app.sql     # Database schema
├── docker-compose.yml # Multi-container Docker setup
├── Dockerfile         # PHP + Apache image definition
└── index.php          # Landing / entry point
```

---

## 🐳 Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.1 |
| Database | MySQL 8.0 |
| Web Server | Apache (via Docker) |
| Frontend | HTML, CSS, Vanilla JS |
| Containerization | Docker + Docker Compose |

---

## 👨‍💻 Developed by

**[Your Name Here]** — Holy Angel University, BSIT  
Final Project — Dynamic Web (DWEB)
