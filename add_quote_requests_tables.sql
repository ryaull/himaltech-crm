-- Run this via phpMyAdmin. Safe to run whether or not you previously
-- applied an earlier version of this table — it drops any old version
-- first, then creates the current structure fresh. This does NOT touch
-- clients, leads, quotes, or anything else in your database.

DROP TABLE IF EXISTS quote_request_items;
DROP TABLE IF EXISTS quote_requests;

CREATE TABLE quote_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  lead_id INT NOT NULL,
  quote_id INT NULL,

  contact_name VARCHAR(120) NOT NULL,
  contact_email VARCHAR(190) NOT NULL,
  contact_phone VARCHAR(40) NULL,
  company_name VARCHAR(150) NOT NULL,
  website_url VARCHAR(190) NULL,
  business_address VARCHAR(255) NULL,

  project_type VARCHAR(255) NULL,

  business_description TEXT NULL,
  target_customers TEXT NULL,
  competitors TEXT NULL,

  page_count_bracket VARCHAR(20) NULL,
  required_pages VARCHAR(255) NULL,
  website_features VARCHAR(500) NULL,

  has_logo VARCHAR(10) NULL,
  brand_colours VARCHAR(255) NULL,
  brand_guidelines VARCHAR(255) NULL,
  marketing_materials VARCHAR(255) NULL,

  has_content VARCHAR(10) NULL,
  needs_copywriting VARCHAR(10) NULL,
  needs_photography VARCHAR(10) NULL,
  needs_stock_images VARCHAR(10) NULL,
  needs_video VARCHAR(10) NULL,

  seo_requirements VARCHAR(500) NULL,

  has_domain VARCHAR(10) NULL,
  hosting_provider VARCHAR(150) NULL,
  needs_hosting VARCHAR(10) NULL,
  needs_business_email VARCHAR(10) NULL,

  design_references TEXT NULL,
  preferred_style VARCHAR(50) NULL,

  budget_range VARCHAR(30) NULL,
  preferred_launch_date DATE NULL,
  is_urgent VARCHAR(10) NULL,

  special_requirements TEXT NULL,
  additional_notes TEXT NULL,

  addon_services VARCHAR(500) NULL,
  referral_source VARCHAR(50) NULL,

  internal_estimated_hours DECIMAL(6,1) NULL,
  internal_estimated_cost DECIMAL(10,2) NULL,
  internal_hosting_cost DECIMAL(10,2) NULL,
  internal_seo_cost DECIMAL(10,2) NULL,
  internal_maintenance_cost DECIMAL(10,2) NULL,
  internal_priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  internal_notes TEXT NULL,

  status ENUM('new','contacted','converted','closed') NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_qr_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_qr_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_qr_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
