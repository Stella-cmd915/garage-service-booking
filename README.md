# Garage Service Booking System

A web application for a car service garage: customers register vehicles and book appointments, mechanics see their assigned jobs, and the secretary manages schedules.

## Tech stack
- PHP 8 · MySQL/MariaDB
- Vanilla JavaScript (AJAX) · HTML/CSS

## Features
- Role-based access (customer / mechanic / secretary)
- Appointment booking and scheduling with availability checks
- Vehicle management per customer
- Dashboards for each role
- Secure sessions and hashed passwords

## Getting started
1. Create a MySQL database and import `database_setup.sql`.
2. Set the DB credentials in `config/database.php`.
3. Serve the folder with Apache/XAMPP and open `index.php`.

Demo accounts are created by the SQL seed (all use the demo password from the seed file; change it before any real use).

---
Part of the **SIGMA LAB** project portfolio.
