-- Himal Tech CRM — schema
-- Import this via phpMyAdmin into the database you create in cPanel.

CREATE TABLE clients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_name VARCHAR(150) NOT NULL,
  website VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  address VARCHAR(255) NULL,
  industry VARCHAR(100) NULL,
  status ENUM('lead','active','inactive') NOT NULL DEFAULT 'lead',
  source VARCHAR(100) NULL,
  notes TEXT NULL,
  assigned_to INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','staff','client') NOT NULL DEFAULT 'staff',
  client_id INT NULL,               -- set only when role = 'client'; links to their company
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE clients
  ADD CONSTRAINT fk_clients_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  job_title VARCHAR(100) NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_contacts_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE leads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NULL,
  stage ENUM('new','contacted','qualified','quoted','won','lost') NOT NULL DEFAULT 'new',
  value_estimate DECIMAL(10,2) NULL,
  assigned_to INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_leads_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_leads_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE quotes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quote_number VARCHAR(30) NOT NULL UNIQUE,
  client_id INT NOT NULL,
  lead_id INT NULL,
  status ENUM('draft','sent','accepted','declined','expired') NOT NULL DEFAULT 'draft',
  issue_date DATE NOT NULL,
  valid_until DATE NULL,
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax_rate DECIMAL(5,2) NOT NULL DEFAULT 20.00,
  tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_quotes_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_quotes_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
  CONSTRAINT fk_quotes_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE quote_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quote_id INT NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_items_quote FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NULL,
  lead_id INT NULL,
  user_id INT NULL,
  note TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_activity_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Support tickets, raised by clients or logged by staff on a client's behalf.
CREATE TABLE tickets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_number VARCHAR(30) NOT NULL UNIQUE,
  client_id INT NOT NULL,
  subject VARCHAR(190) NOT NULL,
  description TEXT NOT NULL,
  status ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
  priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  created_by INT NULL,          -- user who raised it (client or staff)
  assigned_to INT NULL,         -- staff member handling it
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tickets_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_tickets_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_tickets_assignee FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Conversation thread on a ticket (staff and client can both reply).
CREATE TABLE ticket_replies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  user_id INT NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_replies_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_replies_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Files attached either directly to a ticket, or to a specific reply in the thread.
CREATE TABLE ticket_attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT NOT NULL,
  ticket_reply_id INT NULL,
  uploaded_by INT NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_filename VARCHAR(255) NOT NULL,   -- randomised name on disk; never trust the original name for storage
  filesize INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tattach_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_tattach_reply FOREIGN KEY (ticket_reply_id) REFERENCES ticket_replies(id) ON DELETE CASCADE,
  CONSTRAINT fk_tattach_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- General files attached to a client record (contracts, brand assets, reports, etc).
CREATE TABLE client_attachments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  uploaded_by INT NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_filename VARCHAR(255) NOT NULL,
  filesize INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cattach_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_cattach_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One-time tokens for "set your password" (new staff invites) and "forgot
-- password" (self-service reset). Same mechanism powers both: a random
-- token, a short expiry, and a used_at marker so a token can't be replayed.
CREATE TABLE password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  purpose ENUM('invite','reset') NOT NULL DEFAULT 'reset',
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_resets_token ON password_resets(token);

-- Multiple staff can be assigned to the same lead. leads.assigned_to is kept
-- as the "primary" assignee for backward compatibility with existing
-- reports/badges; this table is the full list (primary assignee included).
CREATE TABLE lead_assignees (
  lead_id INT NOT NULL,
  user_id INT NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (lead_id, user_id),
  CONSTRAINT fk_leadassign_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_leadassign_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Same pattern for tickets.
CREATE TABLE ticket_assignees (
  ticket_id INT NOT NULL,
  user_id INT NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ticket_id, user_id),
  CONSTRAINT fk_ticketassign_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_ticketassign_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Service catalog: the shared price list used by both the staff
-- quote-builder and (later) the public quote-request form. Managed
-- entirely through the admin UI — no code changes needed to add a
-- service or change a price.
-- ============================================================
CREATE TABLE service_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  -- flat: one fixed price. per_unit: multiplied by a quantity (e.g. £50
  -- per extra page). monthly: recurring. custom: no fixed price shown,
  -- "Custom quote" displayed instead — for things too variable to price
  -- automatically (e.g. ad spend management).
  price_type ENUM('flat','per_unit','monthly','custom') NOT NULL DEFAULT 'flat',
  unit_label VARCHAR(50) NULL,   -- e.g. "page", "1000 words" — only used when price_type = per_unit
  keywords VARCHAR(255) NULL,    -- comma-separated; powers the "not sure what I need" matcher later
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_services_category FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE INDEX idx_services_category ON services(category_id);

-- ============================================================
-- Public quote-request form submissions — a detailed intake
-- questionnaire, NOT a price calculator. The client never sees any
-- price here; they self-select a budget range, same as describing
-- what they want and roughly what they're willing to spend as two
-- separate, independent pieces of information. Staff reviews
-- everything and fills in the real numbers privately afterward
-- (the internal_* fields below), then builds the actual quote using
-- the normal Quotes tool — nothing here is ever sent to the client
-- automatically.
-- ============================================================
CREATE TABLE quote_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  client_id INT NOT NULL,
  lead_id INT NOT NULL,
  quote_id INT NULL,               -- set once staff converts this into a real quote

  -- Client Details
  contact_name VARCHAR(120) NOT NULL,
  contact_email VARCHAR(190) NOT NULL,
  contact_phone VARCHAR(40) NULL,
  company_name VARCHAR(150) NOT NULL,
  website_url VARCHAR(190) NULL,
  business_address VARCHAR(255) NULL,

  -- Project Type (checkboxes; stored comma-separated)
  project_type VARCHAR(255) NULL,

  -- Business Information
  business_description TEXT NULL,
  target_customers TEXT NULL,
  competitors TEXT NULL,

  -- Website Requirements
  page_count_bracket VARCHAR(20) NULL,
  required_pages VARCHAR(255) NULL,     -- comma-separated
  website_features VARCHAR(500) NULL,   -- comma-separated

  -- Branding
  has_logo VARCHAR(10) NULL,
  brand_colours VARCHAR(255) NULL,
  brand_guidelines VARCHAR(255) NULL,
  marketing_materials VARCHAR(255) NULL,

  -- Content
  has_content VARCHAR(10) NULL,
  needs_copywriting VARCHAR(10) NULL,
  needs_photography VARCHAR(10) NULL,
  needs_stock_images VARCHAR(10) NULL,
  needs_video VARCHAR(10) NULL,

  -- SEO Requirements (comma-separated)
  seo_requirements VARCHAR(500) NULL,

  -- Domain & Hosting
  has_domain VARCHAR(10) NULL,
  hosting_provider VARCHAR(150) NULL,
  needs_hosting VARCHAR(10) NULL,
  needs_business_email VARCHAR(10) NULL,

  -- Design Preferences
  design_references TEXT NULL,          -- up to 3 reference site URLs, one per line
  preferred_style VARCHAR(50) NULL,

  -- Budget & Timeline
  budget_range VARCHAR(30) NULL,
  preferred_launch_date DATE NULL,
  is_urgent VARCHAR(10) NULL,

  -- Additional Information
  special_requirements TEXT NULL,
  additional_notes TEXT NULL,

  -- Optional Add-ons (comma-separated)
  addon_services VARCHAR(500) NULL,

  -- Referral
  referral_source VARCHAR(50) NULL,

  -- Internal Use — staff-filled, NEVER rendered anywhere a client can see
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

-- No default admin user is inserted here on purpose — password hashes generated
-- outside your live PHP environment can silently fail to verify.
-- Run setup.php once after upload to create your admin account, then delete it.
