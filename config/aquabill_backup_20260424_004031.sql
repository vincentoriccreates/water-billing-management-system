-- AquaBill Database Backup
-- Generated: 2026-04-24 00:40:31
-- Server: localhost | Database: aquabill

SET FOREIGN_KEY_CHECKS=0;

-- Table: users
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Staff') NOT NULL DEFAULT 'Staff',
  `avatar` char(2) DEFAULT 'U',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `avatar`, `created_at`) VALUES
('1', 'Admin User', 'admin@barangay.gov', 'admin123', 'Admin', 'A', '2026-04-21 18:15:16'),
('2', 'Staff Member', 'staff@barangay.gov', 'staff123', 'Staff', 'S', '2026-04-21 18:15:16'),
('3', 'Vincent Oric', 'oricv71@gmail.com', '$2y$10$gr8It6E6D5N6eMqfn/HXHeCs3uUmrHKVB7b7PGe1Wx1gM/PVx/VH6', 'Staff', 'V', '2026-04-22 18:49:25');

-- Table: customers
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` varchar(10) NOT NULL,
  `name` varchar(150) NOT NULL,
  `address` text NOT NULL,
  `contact` varchar(20) NOT NULL,
  `meter_no` varchar(30) NOT NULL,
  `status` enum('Active','Disconnected') NOT NULL DEFAULT 'Active',
  `created_at` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `meter_no` (`meter_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `customers` (`id`, `name`, `address`, `contact`, `meter_no`, `status`, `created_at`) VALUES
('C001', 'Maria Santos', 'Blk 3 Lot 5, Sta. Ana St.', '09171234567', 'M-0011', 'Active', '2022-01-15'),
('C002', 'Jose Reyes', '45 Rizal Ave., Brgy. Uno', '09182345678', 'M-0022', 'Active', '2022-03-20'),
('C003', 'Ana Gonzales', '12 Mabini St., Brgy. Dos', '09193456789', 'M-0033', 'Active', '2021-11-10'),
('C004', 'Pedro Cruz', '67 Del Pilar St.', '09204567890', 'M-0044', 'Active', '2020-06-05'),
('C005', 'Lina Bautista', '89 Bonifacio Rd.', '09215678901', 'M-0055', 'Active', '2023-02-28'),
('C006', 'Ramon Dela Cruz', '22 Aguinaldo St.', '09226789012', 'M-0066', 'Active', '2022-08-14');

-- Table: readings
DROP TABLE IF EXISTS `readings`;
CREATE TABLE `readings` (
  `id` varchar(10) NOT NULL,
  `customer_id` varchar(10) NOT NULL,
  `reading_date` date NOT NULL,
  `previous_reading` decimal(10,2) NOT NULL DEFAULT 0.00,
  `current_reading` decimal(10,2) NOT NULL,
  `consumption` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `readings_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `readings` (`id`, `customer_id`, `reading_date`, `previous_reading`, `current_reading`, `consumption`) VALUES
('R001', 'C001', '2024-12-05', '1200.00', '1225.00', '25.00'),
('R002', 'C002', '2024-12-06', '980.00', '998.00', '18.00'),
('R003', 'C003', '2024-12-07', '2340.00', '2368.00', '28.00'),
('R004', 'C005', '2024-12-08', '560.00', '582.00', '22.00'),
('R005', 'C006', '2024-12-09', '1750.00', '1773.00', '23.00'),
('R006', 'C001', '2025-01-05', '1225.00', '1252.00', '27.00'),
('R007', 'C002', '2025-01-06', '998.00', '1019.00', '21.00'),
('R008', 'C003', '2025-01-07', '2368.00', '2401.00', '33.00'),
('R009', 'C003', '2026-04-21', '2401.00', '2500.00', '99.00'),
('R010', 'C003', '2026-04-24', '2500.00', '2505.00', '5.00'),
('R011', 'C002', '2026-04-21', '1019.00', '1023.00', '4.00'),
('R012', 'C005', '2026-04-21', '582.00', '600.00', '18.00'),
('R013', 'C001', '2026-04-21', '1252.00', '1280.00', '28.00'),
('R014', 'C006', '2026-04-21', '1773.00', '1774.00', '1.00'),
('R015', 'C003', '2026-04-22', '2505.00', '2530.00', '25.00'),
('R016', 'C002', '2026-04-22', '1023.00', '1030.00', '7.00'),
('R017', 'C005', '2026-04-22', '600.00', '612.00', '12.00'),
('R018', 'C001', '2026-04-22', '1280.00', '1291.00', '11.00'),
('R019', 'C006', '2026-04-22', '1774.00', '1779.00', '5.00'),
('R020', 'C004', '2026-04-22', '0.00', '12.00', '12.00'),
('R021', 'C003', '2026-05-19', '2505.00', '2520.00', '15.00'),
('R022', 'C002', '2026-05-19', '1030.00', '1035.00', '5.00');

-- Table: bills
DROP TABLE IF EXISTS `bills`;
CREATE TABLE `bills` (
  `id` varchar(10) NOT NULL,
  `customer_id` varchar(10) NOT NULL,
  `reading_id` varchar(10) DEFAULT NULL,
  `billing_month` varchar(30) NOT NULL,
  `prev_reading` decimal(10,2) NOT NULL,
  `curr_reading` decimal(10,2) NOT NULL,
  `consumption` decimal(10,2) NOT NULL,
  `rate_per_cubic` decimal(8,2) NOT NULL DEFAULT 35.00,
  `base_charge` decimal(8,2) NOT NULL DEFAULT 120.00,
  `penalty` decimal(8,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `status` enum('Unpaid','Paid','Overdue') NOT NULL DEFAULT 'Unpaid',
  `due_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `bills_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `bills` (`id`, `customer_id`, `reading_id`, `billing_month`, `prev_reading`, `curr_reading`, `consumption`, `rate_per_cubic`, `base_charge`, `penalty`, `total`, `status`, `due_date`, `created_at`) VALUES
('B001', 'C001', 'R001', 'December 2024', '1200.00', '1225.00', '25.00', '35.00', '120.00', '0.00', '995.00', 'Paid', '2025-01-10', '2026-04-21 18:15:16'),
('B002', 'C002', 'R002', 'December 2024', '980.00', '998.00', '18.00', '35.00', '120.00', '0.00', '750.00', 'Paid', '2025-01-10', '2026-04-21 18:15:16'),
('B003', 'C003', 'R003', 'December 2024', '2340.00', '2368.00', '28.00', '35.00', '120.00', '0.00', '1100.00', 'Paid', '2025-01-10', '2026-04-21 18:15:16'),
('B004', 'C005', 'R004', 'December 2024', '560.00', '582.00', '22.00', '35.00', '120.00', '50.00', '940.00', 'Paid', '2025-01-10', '2026-04-21 18:15:16'),
('B005', 'C006', 'R005', 'December 2024', '1750.00', '1773.00', '23.00', '35.00', '120.00', '50.00', '975.00', 'Paid', '2025-01-10', '2026-04-21 18:15:16'),
('B006', 'C001', 'R006', 'January 2025', '1225.00', '1252.00', '27.00', '35.00', '120.00', '50.00', '1115.00', 'Paid', '2025-02-10', '2026-04-21 18:15:16'),
('B007', 'C002', 'R007', 'January 2025', '998.00', '1019.00', '21.00', '35.00', '120.00', '0.00', '855.00', 'Paid', '2025-02-10', '2026-04-21 18:15:16'),
('B008', 'C003', 'R008', 'January 2025', '2368.00', '2401.00', '33.00', '35.00', '120.00', '50.00', '1325.00', 'Paid', '2025-02-10', '2026-04-21 18:15:16'),
('B009', 'C003', 'R009', 'March 2025', '2401.00', '2500.00', '99.00', '35.00', '120.00', '0.00', '3585.00', 'Paid', '2026-05-06', '2026-04-21 18:22:37'),
('B010', 'C003', 'R015', 'April 2026', '2505.00', '2530.00', '25.00', '35.00', '120.00', '0.00', '995.00', 'Unpaid', '2026-05-07', '2026-04-22 19:25:09'),
('B011', 'C002', 'R016', 'April 2026', '1023.00', '1030.00', '7.00', '35.00', '120.00', '0.00', '365.00', 'Unpaid', '2026-05-07', '2026-04-22 19:25:09'),
('B012', 'C005', 'R017', 'April 2026', '600.00', '612.00', '12.00', '35.00', '120.00', '0.00', '540.00', 'Unpaid', '2026-05-07', '2026-04-22 19:25:09'),
('B013', 'C001', 'R018', 'April 2026', '1280.00', '1291.00', '11.00', '35.00', '120.00', '0.00', '505.00', 'Paid', '2026-05-07', '2026-04-22 19:25:09'),
('B014', 'C006', 'R019', 'April 2026', '1774.00', '1779.00', '5.00', '35.00', '120.00', '0.00', '295.00', 'Paid', '2026-05-07', '2026-04-22 19:25:09'),
('B015', 'C004', 'R020', 'April 2026', '0.00', '12.00', '12.00', '35.00', '120.00', '0.00', '540.00', 'Paid', '2026-05-07', '2026-04-22 19:39:07'),
('B016', 'C003', 'R021', 'May 2026', '2505.00', '2520.00', '15.00', '35.00', '120.00', '0.00', '645.00', 'Unpaid', '2026-06-03', '2026-04-24 06:34:30'),
('B017', 'C002', 'R022', 'May 2026', '1030.00', '1035.00', '5.00', '35.00', '120.00', '0.00', '295.00', 'Unpaid', '2026-06-03', '2026-04-24 06:38:04');

-- Table: payments
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` varchar(10) NOT NULL,
  `bill_id` varchar(10) NOT NULL,
  `customer_id` varchar(10) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `method` enum('Cash','GCash','PayMaya','Bank Transfer','Check') NOT NULL DEFAULT 'Cash',
  `receipt_no` varchar(20) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `payments` (`id`, `bill_id`, `customer_id`, `amount`, `payment_date`, `method`, `receipt_no`, `notes`, `created_at`) VALUES
('P001', 'B001', 'C001', '995.00', '2025-01-08', 'Cash', 'RCP-0001', 'Full payment', '2026-04-21 18:15:16'),
('P002', 'B002', 'C002', '750.00', '2025-01-09', 'GCash', 'RCP-0002', '', '2026-04-21 18:15:16'),
('P003', 'B007', 'C002', '855.00', '2025-02-07', 'Cash', 'RCP-0003', '', '2026-04-21 18:15:16'),
('P004', 'B009', 'C003', '3585.00', '2026-04-21', '', 'ADM-0004', 'Status manually set to Paid', '2026-04-21 19:02:30'),
('P005', 'B004', 'C005', '940.00', '2026-04-21', '', 'ADM-0005', 'Status manually set to Paid', '2026-04-21 19:02:36'),
('P006', 'B003', 'C003', '1100.00', '2026-04-21', '', 'ADM-0006', 'Status manually set to Paid', '2026-04-21 19:02:55'),
('P007', 'B008', 'C003', '1325.00', '2026-04-22', '', 'ADM-0007', 'Status manually set to Paid', '2026-04-22 19:25:33'),
('P008', 'B006', 'C001', '1115.00', '2026-04-22', '', 'ADM-0008', 'Status manually set to Paid', '2026-04-22 19:25:35'),
('P009', 'B005', 'C006', '975.00', '2026-04-22', '', 'ADM-0009', 'Status manually set to Paid', '2026-04-22 19:25:37'),
('P010', 'B014', 'C006', '295.00', '2026-04-22', '', 'ADM-0010', 'Status manually set to Paid', '2026-04-22 19:31:56'),
('P011', 'B013', 'C001', '505.00', '2026-04-22', '', 'ADM-0011', 'Status manually set to Paid', '2026-04-22 19:32:59'),
('P012', 'B015', 'C004', '540.00', '2026-04-24', 'GCash', 'GC-0012', 'GCash Ref: 3234534989 · From: 0909886785', '2026-04-24 06:30:47');

SET FOREIGN_KEY_CHECKS=1;
