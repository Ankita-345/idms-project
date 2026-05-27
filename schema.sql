-- SQL schema for Ice Distribution Management System (IDMS)
CREATE DATABASE IF NOT EXISTS idms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE idms;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('Admin', 'Manager', 'Delivery', 'Client') NOT NULL DEFAULT 'Client',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_name VARCHAR(150) NOT NULL,
  business_type ENUM('Cafe', 'Restaurant', 'Event') NOT NULL,
  category ENUM('Regular', 'Priority', 'Bulk') NOT NULL DEFAULT 'Regular',
  contact_person VARCHAR(100),
  email VARCHAR(100),
  phone VARCHAR(20) NOT NULL,
  credit_limit DECIMAL(10, 2) DEFAULT 0.00,
  payment_terms VARCHAR(100),
  status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS client_addresses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  address_type ENUM('Billing', 'Delivery', 'Both') NOT NULL DEFAULT 'Delivery',
  street_address VARCHAR(200) NOT NULL,
  city VARCHAR(100) NOT NULL,
  state VARCHAR(100) DEFAULT NULL,
  postal_code VARCHAR(20),
  is_default BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Orders table for managing client orders
CREATE TABLE IF NOT EXISTS orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  client_address_id INT UNSIGNED NULL,
  ice_type ENUM('Cube', 'Block', 'Crushed', 'Dry Ice') NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  bulk_order BOOLEAN DEFAULT FALSE,
  recurring BOOLEAN DEFAULT FALSE,
  delivery_date DATE NOT NULL,
  delivery_time_slot VARCHAR(50) NOT NULL,
  delivery_street VARCHAR(200) DEFAULT NULL,
  delivery_city VARCHAR(100) DEFAULT NULL,
  delivery_state VARCHAR(100) DEFAULT NULL,
  delivery_postal_code VARCHAR(20) DEFAULT NULL,
  special_instructions TEXT,
  status ENUM('Pending','Confirmed','Assigned','Out for Delivery','Delivered','Completed','Cancelled','Failed') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  FOREIGN KEY (client_address_id) REFERENCES client_addresses(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS delivery_teams (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  driver_name VARCHAR(100) NOT NULL,
  vehicle_type ENUM('Truck', 'Van', 'Mini') NOT NULL DEFAULT 'Truck',
  vehicle_capacity INT UNSIGNED NOT NULL DEFAULT 0,
  availability_status ENUM('Available', 'Busy', 'Offline') NOT NULL DEFAULT 'Available',
  shift_timing VARCHAR(100) NOT NULL,
  route_allocation VARCHAR(150) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration: link users to clients by adding user_id
-- Run this if `clients` table already exists; safe to run multiple times.
ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL AFTER id;

ALTER TABLE client_addresses
  ADD COLUMN IF NOT EXISTS state VARCHAR(100) DEFAULT NULL AFTER city;

-- Add unique constraint so one user -> one client
ALTER TABLE clients
  ADD UNIQUE KEY IF NOT EXISTS uq_clients_user_id (user_id);

-- Add foreign key to users table
ALTER TABLE clients
  ADD CONSTRAINT IF NOT EXISTS fk_clients_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Migration: allow orders to be assigned to delivery teams
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS assigned_team_id INT UNSIGNED NULL AFTER client_address_id,
  ADD COLUMN IF NOT EXISTS assigned_at DATETIME NULL AFTER assigned_team_id,
  ADD COLUMN IF NOT EXISTS delivery_state VARCHAR(100) DEFAULT NULL AFTER delivery_city;

ALTER TABLE orders
  MODIFY COLUMN status ENUM('Pending','Confirmed','Assigned','Picked','In Transit','Out for Delivery','Delivered','Completed','Cancelled','Failed') NOT NULL DEFAULT 'Pending';

ALTER TABLE orders
  ADD CONSTRAINT IF NOT EXISTS fk_orders_assigned_team FOREIGN KEY (assigned_team_id) REFERENCES delivery_teams(id) ON DELETE SET NULL;

-- Track whether inventory has been deducted/reserved for an order
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS inventory_deducted TINYINT(1) NOT NULL DEFAULT 0 AFTER assigned_at,
  ADD COLUMN IF NOT EXISTS delivery_proof_image VARCHAR(255) DEFAULT NULL AFTER inventory_deducted,
  ADD COLUMN IF NOT EXISTS delivery_otp VARCHAR(10) DEFAULT NULL AFTER delivery_proof_image,
  ADD COLUMN IF NOT EXISTS delivered_at DATETIME NULL AFTER delivery_otp;

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED DEFAULT NULL,
  role VARCHAR(50) DEFAULT NULL,
  title VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  link VARCHAR(255) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_user (user_id),
  INDEX idx_notifications_role (role),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: link delivery_teams to users so Delivery-role users can be mapped to a team
ALTER TABLE delivery_teams
  ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL AFTER id;

ALTER TABLE delivery_teams
  ADD CONSTRAINT IF NOT EXISTS fk_delivery_teams_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Inventory table for tracking ice stock by type
CREATE TABLE IF NOT EXISTS inventory (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ice_type ENUM('Cube','Block','Crushed','Dry Ice') NOT NULL UNIQUE,
  quantity INT UNSIGNED NOT NULL DEFAULT 0,
  low_threshold INT UNSIGNED NOT NULL DEFAULT 10,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
