CREATE TABLE IF NOT EXISTS scope_order_partner_financials (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id bigint(20) unsigned NOT NULL,
  cost_center_code varchar(50) DEFAULT NULL,
  partner_code varchar(60) DEFAULT NULL,
  financial_status varchar(50),
  
  local_currency char(3),
  local_booked_income decimal(18,2),
  local_booked_cost decimal(18,2),
  local_total_income decimal(18,2),
  local_total_cost decimal(18,2),
  local_profit decimal(18,2),
  local_gross_margin decimal(10,3),
  
  org_currency char(3),
  org_booked_income decimal(18,2),
  org_booked_cost decimal(18,2),
  org_total_income decimal(18,2),
  org_total_cost decimal(18,2),
  org_profit decimal(18,2),
  org_gross_margin decimal(10,3),
  
  updated_at timestamp default current_timestamp on update current_timestamp,
  
  UNIQUE KEY unique_order_cost_center (order_id, cost_center_code),
  FOREIGN KEY (order_id) REFERENCES scope_orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
