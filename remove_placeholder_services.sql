-- Run this via phpMyAdmin on your live database to remove the guessed
-- placeholder services that were never confirmed on the real site.
-- Safe to run once. Only deletes by exact name match, nothing else touched.

DELETE FROM services WHERE name IN (
  'Booking System',
  'Online Payment Integration',
  'Blog Setup',
  'Custom CMS / Admin Dashboard',
  'User Accounts / Login System',
  'Live Chat Integration',
  'Multi-language Support',
  'Appointment System',
  'Newsletter Signup',
  'Photo Gallery',
  'Social Media Marketing',
  'Email Marketing',
  'Business Email',
  'SSL Certificate',
  'Extra Page'
);
