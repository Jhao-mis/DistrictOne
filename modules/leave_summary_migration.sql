CREATE TABLE IF NOT EXISTS employee_leave_summaries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    emp_id VARCHAR(50) NOT NULL,
    leave_year SMALLINT UNSIGNED NOT NULL,
    leave_month TINYINT UNSIGNED NOT NULL,
    employee_name VARCHAR(255) NOT NULL,
    basic_pay DECIMAL(12, 2) NOT NULL,
    vacation_leave DECIMAL(10, 3) NOT NULL,
    sick_leave DECIMAL(10, 3) NOT NULL,
    total_leave DECIMAL(10, 3) NOT NULL,
    rate_per_day DECIMAL(12, 2) NOT NULL,
    leave_amount DECIMAL(14, 2) NOT NULL,
    imported_by INT NOT NULL,
    imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_employee_leave_period (emp_id, leave_year, leave_month),
    KEY idx_employee_leave_imported (imported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
