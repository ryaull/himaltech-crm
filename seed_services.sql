-- Himal Tech CRM — service catalog starter data
-- Optional: import this via phpMyAdmin AFTER schema.sql (or after
-- add_catalog_tables.sql on an existing database), to start the Catalog
-- with real data instead of an empty list.
--
-- Every price below was directly confirmed either in knowledge.md or by
-- fetching the live himaltech.co.uk pages themselves. Nothing here is a
-- guess. Services with price_type='custom' ("Custom quote" instead of a
-- number) is the confirmed real fact about that service — the site
-- genuinely doesn't publish a fixed price for it, that's not a gap.

INSERT INTO service_categories (name, sort_order) VALUES
  ('Website', 1),
  ('SEO', 2),
  ('Digital Marketing', 3),
  ('Design', 4),
  ('Content', 5),
  ('Hosting & Domain', 6),
  ('Training', 7);

-- ===== Website =====
INSERT INTO services (category_id, name, description, price, price_type, unit_label, keywords, is_active, sort_order) VALUES
  ((SELECT id FROM service_categories WHERE name='Website'), 'Website Design Plan', 'Up to 5 pages (Home, About, Services, Contact, Map), WordPress, mobile responsive, basic SEO setup, 2hrs training, 30 days support.', 295.00, 'flat', NULL, 'website, new website, design, wordpress', 1, 1),
  ((SELECT id FROM service_categories WHERE name='Website'), 'WordPress Theme Installation Only', 'Just the theme installed, no page-building.', 100.00, 'flat', NULL, 'wordpress theme, template install', 1, 2),
  ((SELECT id FROM service_categories WHERE name='Website'), 'Website + Pages (starting from)', 'Theme installed and pages built out.', 500.00, 'flat', NULL, 'website, pages, wordpress build', 1, 3),
  ((SELECT id FROM service_categories WHERE name='Website'), 'Website + Full Customization (starting from)', 'Theme installed with full custom styling/build.', 1000.00, 'flat', NULL, 'custom website, bespoke design', 1, 4),
  ((SELECT id FROM service_categories WHERE name='Website'), 'Conversion-Focused Business Site', 'Typical range for a business site built to convert visitors.', 500.00, 'custom', NULL, 'business website, conversion focused', 1, 5),
  ((SELECT id FROM service_categories WHERE name='Website'), 'E-commerce Site', 'Online store — typical range £2,500–£10,000 depending on scope.', 2500.00, 'custom', NULL, 'ecommerce, online shop, sell products, store, online payment', 1, 6),
  ((SELECT id FROM service_categories WHERE name='Website'), 'Custom Feature-Rich Site', 'Bespoke build with custom functionality — from £10,000.', 10000.00, 'custom', NULL, 'custom features, complex website, bespoke build', 1, 7);

-- ===== SEO =====
INSERT INTO services (category_id, name, description, price, price_type, unit_label, keywords, is_active, sort_order) VALUES
  ((SELECT id FROM service_categories WHERE name='SEO'), 'SEO Audit', 'Full technical + on-page SEO review with PDF findings report.', 100.00, 'flat', NULL, 'seo audit, seo review, seo check', 1, 1),
  ((SELECT id FROM service_categories WHERE name='SEO'), 'SEO Consultation (paid)', 'Paid consultation beyond the free 5-minute chat.', 100.00, 'flat', NULL, 'seo consultation, seo advice', 1, 2),
  ((SELECT id FROM service_categories WHERE name='SEO'), 'One-time On-Page SEO', 'One-off on-page optimisation (titles, meta, structure, images).', 150.00, 'flat', NULL, 'one time seo, on-page seo, seo setup', 1, 3),
  ((SELECT id FROM service_categories WHERE name='SEO'), 'Keyword Ranking (per keyword)', 'Ongoing ranking work for one target keyword.', 200.00, 'per_unit', 'keyword', 'keyword ranking, rank on google, keyword targeting', 1, 4),
  ((SELECT id FROM service_categories WHERE name='SEO'), 'Monthly SEO — Starter', 'Entry-level ongoing SEO package.', 200.00, 'monthly', NULL, 'monthly seo, ongoing seo, seo package', 1, 5),
  ((SELECT id FROM service_categories WHERE name='SEO'), 'Monthly SEO — Silver', 'Mid-tier ongoing SEO package.', 300.00, 'monthly', NULL, 'monthly seo, ongoing seo, seo package', 1, 6),
  ((SELECT id FROM service_categories WHERE name='SEO'), 'Monthly SEO — Gold', 'Higher-tier ongoing SEO package.', 500.00, 'monthly', NULL, 'monthly seo, ongoing seo, seo package', 1, 7),
  ((SELECT id FROM service_categories WHERE name='SEO'), 'Monthly SEO — Diamond', 'Top-tier ongoing SEO package.', 700.00, 'monthly', NULL, 'monthly seo, ongoing seo, seo package', 1, 8),
  ((SELECT id FROM service_categories WHERE name='SEO'), 'Monthly SEO Setup Fee', 'One-time setup fee charged alongside the first month of any monthly SEO package.', 1000.00, 'flat', NULL, 'seo setup fee', 1, 9),
  ((SELECT id FROM service_categories WHERE name='SEO'), 'Website Health Check', 'Technical inspection: plugins, theme, hosting, security, performance.', 100.00, 'flat', NULL, 'health check, website audit, technical review', 1, 10);

-- ===== Digital Marketing (confirmed: genuinely "Custom quote", not a gap) =====
INSERT INTO services (category_id, name, description, price, price_type, unit_label, keywords, is_active, sort_order) VALUES
  ((SELECT id FROM service_categories WHERE name='Digital Marketing'), 'Google Ads Management', 'Ad spend budget is separate from this management fee — varies per client.', 0.00, 'custom', NULL, 'google ads, ppc, search ads', 1, 1),
  ((SELECT id FROM service_categories WHERE name='Digital Marketing'), 'Facebook / Instagram Ads Management', 'Ad spend budget is separate from this management fee.', 0.00, 'custom', NULL, 'facebook ads, instagram ads, meta ads', 1, 2);

-- ===== Design =====
INSERT INTO services (category_id, name, description, price, price_type, unit_label, keywords, is_active, sort_order) VALUES
  ((SELECT id FROM service_categories WHERE name='Design'), 'Logo Design', 'Starting price — scope affects final cost.', 50.00, 'flat', NULL, 'logo, branding, brand identity', 1, 1),
  ((SELECT id FROM service_categories WHERE name='Design'), 'Business Cards', 'Starting price.', 30.00, 'flat', NULL, 'business cards, print design', 1, 2),
  ((SELECT id FROM service_categories WHERE name='Design'), 'Flyers', 'Starting price.', 40.00, 'flat', NULL, 'flyer, leaflet, print design', 1, 3),
  ((SELECT id FROM service_categories WHERE name='Design'), 'Brochures', 'Starting price.', 80.00, 'flat', NULL, 'brochure, print design', 1, 4),
  ((SELECT id FROM service_categories WHERE name='Design'), 'Posters', 'Starting price.', 50.00, 'flat', NULL, 'poster, print design', 1, 5);

-- ===== Content =====
INSERT INTO services (category_id, name, description, price, price_type, unit_label, keywords, is_active, sort_order) VALUES
  ((SELECT id FROM service_categories WHERE name='Content'), 'Content Writing', 'Website/blog copywriting.', 50.00, 'per_unit', '1000 words', 'copywriting, content writing, website text, blog', 1, 1),
  ((SELECT id FROM service_categories WHERE name='Content'), 'Stock Photography', 'Licensed stock images.', 5.00, 'per_unit', 'photo', 'stock photos, images, photography, gallery', 1, 2);

-- ===== Hosting & Domain =====
INSERT INTO services (category_id, name, description, price, price_type, unit_label, keywords, is_active, sort_order) VALUES
  ((SELECT id FROM service_categories WHERE name='Hosting & Domain'), 'Premium Hosting (with gallery CMS)', 'Per year.', 300.00, 'flat', NULL, 'hosting, web hosting, premium hosting', 1, 1),
  ((SELECT id FROM service_categories WHERE name='Hosting & Domain'), 'Domain Registration', 'Price depends on the specific domain/extension.', 0.00, 'custom', NULL, 'domain, domain name, .co.uk, .com', 1, 2);

-- ===== Training =====
INSERT INTO services (category_id, name, description, price, price_type, unit_label, keywords, is_active, sort_order) VALUES
  ((SELECT id FROM service_categories WHERE name='Training'), '1-on-1 Training', 'SEO, WordPress, or digital marketing training.', 30.00, 'per_unit', 'hour', 'training, learn, teach me, workshop', 1, 1);
