<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Crea las tablas del DER V4 sin ejecutar su CREATE DATABASE ni reemplazar usuarios existentes.
return new class extends Migration
{
    public function up(): void
    {
        // is_admin solo se asigna desde una acción de administración, nunca desde el registro público.
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
        $driver = DB::getDriverName();
        if (! in_array($driver, ['sqlite', 'mysql'], true)) {
            throw new RuntimeException('Esta migración admite SQLite y MySQL/MariaDB.');
        }
        // Se conservan claves, unicidad y comprobaciones; SQLite usa su sintaxis equivalente.
        $statements = $driver === 'sqlite' ? [
            <<<'SQL'
CREATE TABLE `technologies` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `active` INTEGER NOT NULL DEFAULT 1,

  CONSTRAINT `uq_02_01` UNIQUE (`slug`),
  CONSTRAINT `ck_02_01` CHECK (`active` IN (0, 1))
)
SQL,
            <<<'SQL'
CREATE TABLE `tariff_versions` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `currency_code` CHAR(3) NOT NULL,
  `effective_from` DATETIME NOT NULL,
  `active` INTEGER NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `ck_03_01` CHECK (`currency_code` GLOB '[A-Z][A-Z][A-Z]'),
  CONSTRAINT `ck_03_02` CHECK (`active` IN (0, 1))
)
SQL,
            <<<'SQL'
CREATE TABLE `service_categories` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `active` BOOLEAN NOT NULL DEFAULT 1,

  CONSTRAINT `uq_04_01` UNIQUE (`slug`),
  CONSTRAINT `ck_04_01` CHECK (`active` IN (0, 1))
)
SQL,
            <<<'SQL'
CREATE TABLE `services` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `service_category_id` INTEGER NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `active` INTEGER NOT NULL DEFAULT 1,
  `estimation_mode` VARCHAR(32) NOT NULL DEFAULT 'manual',

  CONSTRAINT `uq_05_01` UNIQUE (`slug`),

  CONSTRAINT `fk_05_01` FOREIGN KEY (`service_category_id`) REFERENCES `service_categories` (`id`),
  CONSTRAINT `ck_05_01` CHECK (`active` IN (0, 1)),
  CONSTRAINT `ck_05_02` CHECK (`estimation_mode` IN ('manual', 'automatic'))
)
SQL,
            <<<'SQL'
CREATE TABLE `professional_profiles` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `user_id` INTEGER NOT NULL,
  `brand_name` VARCHAR(255) NOT NULL,
  `headline` VARCHAR(255) NOT NULL,
  `bio` TEXT NOT NULL,
  `availability_status` VARCHAR(32) NOT NULL DEFAULT 'available',
  `logo_path` VARCHAR(1024) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `uq_06_01` UNIQUE (`user_id`),
  CONSTRAINT `fk_06_01` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `ck_06_01` CHECK (`availability_status` IN ('available', 'busy', 'unavailable'))
)
SQL,
            <<<'SQL'
CREATE TABLE `service_components` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `service_id` INTEGER NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `unit_code` VARCHAR(32) NOT NULL,
  `active` INTEGER NOT NULL DEFAULT 1,
  `is_default` BOOLEAN NOT NULL DEFAULT 0,
  `default_quantity` DECIMAL(12,3) NOT NULL DEFAULT 1,

  CONSTRAINT `uq_07_01` UNIQUE (`service_id`, `slug`),
  CONSTRAINT `fk_07_01` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `ck_07_01` CHECK (`active` IN (0, 1)),
  CONSTRAINT `ck_07_02` CHECK (`is_default` IN (0, 1)),
  CONSTRAINT `ck_07_03` CHECK (`default_quantity` > 0)
)
SQL,
            <<<'SQL'
CREATE TABLE `professional_technologies` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `professional_profile_id` INTEGER NOT NULL,
  `technology_id` INTEGER NOT NULL,
  `level` VARCHAR(32) NULL DEFAULT NULL,

  CONSTRAINT `uq_08_01` UNIQUE (`professional_profile_id`, `technology_id`),

  CONSTRAINT `fk_08_01` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profiles` (`id`),
  CONSTRAINT `fk_08_02` FOREIGN KEY (`technology_id`) REFERENCES `technologies` (`id`),
  CONSTRAINT `ck_08_01` CHECK (`level` IS NULL OR (`level` IN ('beginner', 'intermediate', 'advanced')))
)
SQL,
            <<<'SQL'
CREATE TABLE `professional_services` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `professional_profile_id` INTEGER NOT NULL,
  `service_id` INTEGER NOT NULL,
  `active` INTEGER NOT NULL DEFAULT 1,

  CONSTRAINT `uq_09_01` UNIQUE (`professional_profile_id`, `service_id`),

  CONSTRAINT `fk_09_01` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profiles` (`id`),
  CONSTRAINT `fk_09_02` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `ck_09_01` CHECK (`active` IN (0, 1))
)
SQL,
            <<<'SQL'
CREATE TABLE `portfolio_projects` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `professional_profile_id` INTEGER NOT NULL,
  `service_id` INTEGER NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `live_url` VARCHAR(2048) NULL DEFAULT NULL,
  `is_public` INTEGER NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,


  CONSTRAINT `fk_10_01` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profiles` (`id`),
  CONSTRAINT `fk_10_02` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `ck_10_01` CHECK (`is_public` IN (0, 1))
)
SQL,
            <<<'SQL'
CREATE TABLE `service_prices` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `tariff_version_id` INTEGER NOT NULL,
  `service_id` INTEGER NOT NULL,
  `price_min` DECIMAL(15,2) NOT NULL,
  `price_max` DECIMAL(15,2) NOT NULL,

  CONSTRAINT `uq_11_01` UNIQUE (`tariff_version_id`, `service_id`),

  CONSTRAINT `fk_11_01` FOREIGN KEY (`tariff_version_id`) REFERENCES `tariff_versions` (`id`),
  CONSTRAINT `fk_11_02` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `ck_11_01` CHECK (`price_min` >= 0 AND `price_max` >= `price_min`)
)
SQL,
            <<<'SQL'
CREATE TABLE `estimates` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `user_id` INTEGER NOT NULL,
  `service_id` INTEGER NOT NULL,
  `tariff_version_id` INTEGER NULL,
  `summary` TEXT NOT NULL,
  `base_min_snapshot` DECIMAL(15,2) NULL,
  `base_max_snapshot` DECIMAL(15,2) NULL,
  `price_min` DECIMAL(15,2) NULL,
  `price_max` DECIMAL(15,2) NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'calculated',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,



  CONSTRAINT `fk_12_01` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_12_02` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `fk_12_03` FOREIGN KEY (`tariff_version_id`) REFERENCES `tariff_versions` (`id`),
  CONSTRAINT `ck_12_01` CHECK (`status` IN ('calculated', 'archived', 'manual')),
  CONSTRAINT `ck_12_02` CHECK (`price_min` >= 0 AND `price_max` >= `price_min`),
  CONSTRAINT `ck_12_03` CHECK (`base_min_snapshot` >= 0 AND `base_max_snapshot` >= `base_min_snapshot`)
)
SQL,
            <<<'SQL'
CREATE TABLE `professional_capabilities` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `professional_profile_id` INTEGER NOT NULL,
  `service_component_id` INTEGER NOT NULL,

  CONSTRAINT `uq_13_01` UNIQUE (`professional_profile_id`, `service_component_id`),

  CONSTRAINT `fk_13_01` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profiles` (`id`),
  CONSTRAINT `fk_13_02` FOREIGN KEY (`service_component_id`) REFERENCES `service_components` (`id`)
)
SQL,
            <<<'SQL'
CREATE TABLE `portfolio_project_technologies` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `portfolio_project_id` INTEGER NOT NULL,
  `technology_id` INTEGER NOT NULL,

  CONSTRAINT `uq_14_01` UNIQUE (`portfolio_project_id`, `technology_id`),

  CONSTRAINT `fk_14_01` FOREIGN KEY (`portfolio_project_id`) REFERENCES `portfolio_projects` (`id`),
  CONSTRAINT `fk_14_02` FOREIGN KEY (`technology_id`) REFERENCES `technologies` (`id`)
)
SQL,
            <<<'SQL'
CREATE TABLE `portfolio_project_images` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `portfolio_project_id` INTEGER NOT NULL,
  `image_path` VARCHAR(1024) NOT NULL,
  `alt_text` VARCHAR(255) NOT NULL,
  `sort_order` INTEGER NOT NULL DEFAULT 0,

  CONSTRAINT `uq_15_01` UNIQUE (`portfolio_project_id`, `sort_order`),
  CONSTRAINT `fk_15_01` FOREIGN KEY (`portfolio_project_id`) REFERENCES `portfolio_projects` (`id`)
)
SQL,
            <<<'SQL'
CREATE TABLE `estimator_questions` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `service_id` INTEGER NULL DEFAULT NULL,
  `quantity_component_id` INTEGER NULL DEFAULT NULL,
  `question` VARCHAR(1000) NOT NULL,
  `input_type` VARCHAR(32) NOT NULL,
  `sort_order` INTEGER NOT NULL DEFAULT 0,
  `active` INTEGER NOT NULL DEFAULT 1,


  CONSTRAINT `fk_16_01` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `fk_16_02` FOREIGN KEY (`quantity_component_id`) REFERENCES `service_components` (`id`),
  CONSTRAINT `ck_16_01` CHECK (`input_type` IN ('single_choice', 'multiple_choice', 'number', 'text')),
  CONSTRAINT `ck_16_02` CHECK (`active` IN (0, 1)),
  CONSTRAINT `ck_16_03` CHECK (`quantity_component_id` IS NULL OR (`input_type` = 'number' AND `service_id` IS NOT NULL))
)
SQL,
            <<<'SQL'
CREATE TABLE `component_prices` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `tariff_version_id` INTEGER NOT NULL,
  `service_component_id` INTEGER NOT NULL,
  `price_min` DECIMAL(15,2) NOT NULL,
  `price_max` DECIMAL(15,2) NOT NULL,

  CONSTRAINT `uq_17_01` UNIQUE (`tariff_version_id`, `service_component_id`),

  CONSTRAINT `fk_17_01` FOREIGN KEY (`tariff_version_id`) REFERENCES `tariff_versions` (`id`),
  CONSTRAINT `fk_17_02` FOREIGN KEY (`service_component_id`) REFERENCES `service_components` (`id`),
  CONSTRAINT `ck_17_01` CHECK (`price_min` >= 0 AND `price_max` >= `price_min`)
)
SQL,
            <<<'SQL'
CREATE TABLE `estimate_items` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `estimate_id` INTEGER NOT NULL,
  `service_component_id` INTEGER NOT NULL,
  `component_name_snapshot` VARCHAR(255) NOT NULL,
  `unit_snapshot` VARCHAR(32) NOT NULL,
  `price_min_snapshot` DECIMAL(15,2) NOT NULL,
  `price_max_snapshot` DECIMAL(15,2) NOT NULL,
  `quantity` DECIMAL(12,3) NOT NULL DEFAULT 1,

  CONSTRAINT `uq_18_01` UNIQUE (`estimate_id`, `service_component_id`),

  CONSTRAINT `fk_18_01` FOREIGN KEY (`estimate_id`) REFERENCES `estimates` (`id`),
  CONSTRAINT `fk_18_02` FOREIGN KEY (`service_component_id`) REFERENCES `service_components` (`id`),
  CONSTRAINT `ck_18_01` CHECK (`quantity` > 0),
  CONSTRAINT `ck_18_02` CHECK (`price_min_snapshot` >= 0 AND `price_max_snapshot` >= `price_min_snapshot`)
)
SQL,
            <<<'SQL'
CREATE TABLE `project_requests` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `user_id` INTEGER NOT NULL,
  `estimate_id` INTEGER NULL DEFAULT NULL,
  `service_id` INTEGER NULL DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `visibility` VARCHAR(32) NOT NULL DEFAULT 'private',
  `status` VARCHAR(32) NOT NULL DEFAULT 'draft',
  `published_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,




  CONSTRAINT `fk_19_01` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_19_02` FOREIGN KEY (`estimate_id`) REFERENCES `estimates` (`id`),
  CONSTRAINT `fk_19_03` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `ck_19_01` CHECK (`visibility` IN ('private', 'public')),
  CONSTRAINT `ck_19_02` CHECK (`status` IN ('draft', 'open', 'closed', 'cancelled')),
  CONSTRAINT `ck_19_03` CHECK (`status` IN ('draft', 'cancelled') OR `service_id` IS NOT NULL),
  CONSTRAINT `ck_19_04` CHECK (`visibility` <> 'public' OR `status` <> 'open' OR `published_at` IS NOT NULL)
)
SQL,
            <<<'SQL'
CREATE TABLE `estimator_options` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `question_id` INTEGER NOT NULL,
  `label` VARCHAR(255) NOT NULL,
  `sort_order` INTEGER NOT NULL DEFAULT 0,
  `active` INTEGER NOT NULL DEFAULT 1,

  CONSTRAINT `uq_20_01` UNIQUE (`id`, `question_id`),

  CONSTRAINT `fk_20_01` FOREIGN KEY (`question_id`) REFERENCES `estimator_questions` (`id`),
  CONSTRAINT `ck_20_01` CHECK (`active` IN (0, 1))
)
SQL,
            <<<'SQL'
CREATE TABLE `project_request_components` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `project_request_id` INTEGER NOT NULL,
  `service_component_id` INTEGER NOT NULL,
  `quantity` DECIMAL(12,3) NOT NULL DEFAULT 1,

  CONSTRAINT `uq_21_01` UNIQUE (`project_request_id`, `service_component_id`),

  CONSTRAINT `fk_21_01` FOREIGN KEY (`project_request_id`) REFERENCES `project_requests` (`id`),
  CONSTRAINT `fk_21_02` FOREIGN KEY (`service_component_id`) REFERENCES `service_components` (`id`),
  CONSTRAINT `ck_21_01` CHECK (`quantity` > 0)
)
SQL,
            <<<'SQL'
CREATE TABLE `project_request_invitations` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `project_request_id` INTEGER NOT NULL,
  `professional_profile_id` INTEGER NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `uq_22_01` UNIQUE (`project_request_id`, `professional_profile_id`),

  CONSTRAINT `fk_22_01` FOREIGN KEY (`project_request_id`) REFERENCES `project_requests` (`id`),
  CONSTRAINT `fk_22_02` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profiles` (`id`),
  CONSTRAINT `ck_22_01` CHECK (`status` IN ('pending', 'accepted', 'declined'))
)
SQL,
            <<<'SQL'
CREATE TABLE `proposals` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `project_request_id` INTEGER NOT NULL,
  `professional_profile_id` INTEGER NOT NULL,
  `message` TEXT NOT NULL,
  `estimated_delivery_days` INTEGER NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'submitted',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `uq_23_01` UNIQUE (`project_request_id`, `professional_profile_id`),

  CONSTRAINT `fk_23_01` FOREIGN KEY (`project_request_id`) REFERENCES `project_requests` (`id`),
  CONSTRAINT `fk_23_02` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profiles` (`id`),
  CONSTRAINT `ck_23_01` CHECK (`estimated_delivery_days` > 0),
  CONSTRAINT `ck_23_02` CHECK (`status` IN ('submitted', 'accepted', 'rejected', 'withdrawn'))
)
SQL,
            <<<'SQL'
CREATE TABLE `option_components` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `estimator_option_id` INTEGER NOT NULL,
  `service_component_id` INTEGER NOT NULL,

  CONSTRAINT `uq_24_01` UNIQUE (`estimator_option_id`, `service_component_id`),

  CONSTRAINT `fk_24_01` FOREIGN KEY (`estimator_option_id`) REFERENCES `estimator_options` (`id`),
  CONSTRAINT `fk_24_02` FOREIGN KEY (`service_component_id`) REFERENCES `service_components` (`id`)
)
SQL,
            <<<'SQL'
CREATE TABLE `option_services` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `estimator_option_id` INTEGER NOT NULL,
  `service_id` INTEGER NOT NULL,

  CONSTRAINT `uq_25_01` UNIQUE (`estimator_option_id`, `service_id`),

  CONSTRAINT `fk_25_01` FOREIGN KEY (`estimator_option_id`) REFERENCES `estimator_options` (`id`),
  CONSTRAINT `fk_25_02` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
)
SQL,
            <<<'SQL'
CREATE TABLE `estimate_answers` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `estimate_id` INTEGER NOT NULL,
  `question_id` INTEGER NOT NULL,
  `option_id` INTEGER NULL DEFAULT NULL,
  `value_text` TEXT NULL DEFAULT NULL,
  `value_number` DECIMAL(15,4) NULL DEFAULT NULL,

  CONSTRAINT `uq_26_01` UNIQUE (`estimate_id`, `question_id`, `option_id`),


  CONSTRAINT `fk_26_01` FOREIGN KEY (`estimate_id`) REFERENCES `estimates` (`id`),
  CONSTRAINT `fk_26_02` FOREIGN KEY (`question_id`) REFERENCES `estimator_questions` (`id`),
  CONSTRAINT `fk_26_03` FOREIGN KEY (`option_id`, `question_id`) REFERENCES `estimator_options` (`id`, `question_id`),
  CONSTRAINT `ck_26_01` CHECK (((`option_id` IS NOT NULL) + (`value_text` IS NOT NULL) + (`value_number` IS NOT NULL)) = 1),
  CONSTRAINT `ck_26_02` CHECK (`value_text` IS NULL OR LENGTH(TRIM(`value_text`)) > 0)
)
SQL,
            <<<'SQL'
CREATE TABLE `budgets` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `proposal_id` INTEGER NULL DEFAULT NULL,
  `project_request_id` INTEGER NULL DEFAULT NULL,
  `professional_profile_id` INTEGER NULL DEFAULT NULL,
  `client_user_id` INTEGER NULL DEFAULT NULL,
  `direct_service_id` INTEGER NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,





  CONSTRAINT `fk_27_01` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`),
  CONSTRAINT `fk_27_02` FOREIGN KEY (`project_request_id`) REFERENCES `project_requests` (`id`),
  CONSTRAINT `fk_27_03` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profiles` (`id`),
  CONSTRAINT `fk_27_04` FOREIGN KEY (`client_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_27_05` FOREIGN KEY (`direct_service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `ck_27_01` CHECK ((
      (`proposal_id` IS NOT NULL AND `project_request_id` IS NULL AND `professional_profile_id` IS NULL AND `client_user_id` IS NULL AND `direct_service_id` IS NULL)
      OR (`proposal_id` IS NULL AND `project_request_id` IS NOT NULL AND `professional_profile_id` IS NOT NULL AND `client_user_id` IS NULL AND `direct_service_id` IS NULL)
      OR (`proposal_id` IS NULL AND `project_request_id` IS NULL AND `professional_profile_id` IS NOT NULL AND `client_user_id` IS NOT NULL AND `direct_service_id` IS NOT NULL)
    ))
)
SQL,
            <<<'SQL'
CREATE TABLE `project_request_answers` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `project_request_id` INTEGER NOT NULL,
  `question_id` INTEGER NOT NULL,
  `option_id` INTEGER NULL DEFAULT NULL,
  `value_text` TEXT NULL DEFAULT NULL,
  `value_number` DECIMAL(15,4) NULL DEFAULT NULL,

  CONSTRAINT `uq_28_01` UNIQUE (`project_request_id`, `question_id`, `option_id`),


  CONSTRAINT `fk_28_01` FOREIGN KEY (`project_request_id`) REFERENCES `project_requests` (`id`),
  CONSTRAINT `fk_28_02` FOREIGN KEY (`question_id`) REFERENCES `estimator_questions` (`id`),
  CONSTRAINT `fk_28_03` FOREIGN KEY (`option_id`, `question_id`) REFERENCES `estimator_options` (`id`, `question_id`),
  CONSTRAINT `ck_28_01` CHECK (((`option_id` IS NOT NULL) + (`value_text` IS NOT NULL) + (`value_number` IS NOT NULL)) = 1),
  CONSTRAINT `ck_28_02` CHECK (`value_text` IS NULL OR LENGTH(TRIM(`value_text`)) > 0)
)
SQL,
            <<<'SQL'
CREATE TABLE `budget_versions` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `budget_id` INTEGER NOT NULL,
  `version_number` INTEGER NOT NULL,
  `currency_code` CHAR(3) NOT NULL,
  `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `valid_until` DATE NOT NULL,
  `terms` TEXT NOT NULL,
  `estimated_delivery_days` INTEGER NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'draft',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `uq_29_01` UNIQUE (`budget_id`, `version_number`),

  CONSTRAINT `fk_29_01` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`),
  CONSTRAINT `ck_29_01` CHECK (`version_number` > 0),
  CONSTRAINT `ck_29_02` CHECK (`currency_code` GLOB '[A-Z][A-Z][A-Z]'),
  CONSTRAINT `ck_29_03` CHECK (`tax_rate` BETWEEN 0 AND 100),
  CONSTRAINT `ck_29_04` CHECK (`estimated_delivery_days` > 0),
  CONSTRAINT `ck_29_05` CHECK (`status` IN ('draft', 'sent', 'accepted', 'rejected', 'expired'))
)
SQL,
            <<<'SQL'
CREATE TABLE `budget_items` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `budget_version_id` INTEGER NOT NULL,
  `service_component_id` INTEGER NULL DEFAULT NULL,
  `description` TEXT NOT NULL,
  `unit_label` VARCHAR(50) NOT NULL,
  `quantity` DECIMAL(12,3) NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(15,2) NOT NULL,


  CONSTRAINT `fk_30_01` FOREIGN KEY (`budget_version_id`) REFERENCES `budget_versions` (`id`),
  CONSTRAINT `fk_30_02` FOREIGN KEY (`service_component_id`) REFERENCES `service_components` (`id`),
  CONSTRAINT `ck_30_01` CHECK (`quantity` > 0),
  CONSTRAINT `ck_30_02` CHECK (`unit_price` >= 0)
)
SQL,
            <<<'SQL'
CREATE TABLE `projects` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `accepted_budget_version_id` INTEGER NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `start_date` DATE NULL DEFAULT NULL,
  `end_date` DATE NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  CONSTRAINT `uq_31_01` UNIQUE (`accepted_budget_version_id`),
  CONSTRAINT `fk_31_01` FOREIGN KEY (`accepted_budget_version_id`) REFERENCES `budget_versions` (`id`),
  CONSTRAINT `ck_31_01` CHECK (`status` IN ('pending', 'in_progress', 'completed', 'cancelled')),
  CONSTRAINT `ck_31_02` CHECK (`start_date` IS NULL OR `end_date` IS NULL OR `end_date` >= `start_date`)
)
SQL
        ] : [
            <<<'SQL'
CREATE TABLE `technologies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_02_01` (`slug`),
  CONSTRAINT `ck_02_01` CHECK (`active` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `tariff_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `currency_code` CHAR(3) NOT NULL,
  `effective_from` DATETIME(0) NOT NULL,
  `active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `ck_03_01` CHECK (CHAR_LENGTH(`currency_code`) = 3 AND ASCII(SUBSTRING(`currency_code`, 1, 1)) BETWEEN 65 AND 90 AND ASCII(SUBSTRING(`currency_code`, 2, 1)) BETWEEN 65 AND 90 AND ASCII(SUBSTRING(`currency_code`, 3, 1)) BETWEEN 65 AND 90),
  CONSTRAINT `ck_03_02` CHECK (`active` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `service_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `active` BOOLEAN NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_04_01` (`slug`),
  CONSTRAINT `ck_04_01` CHECK (`active` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `services` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_category_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `estimation_mode` VARCHAR(32) NOT NULL DEFAULT 'manual',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_05_01` (`slug`),
  KEY `ix_05_02` (`service_category_id`),
  CONSTRAINT `fk_05_01` FOREIGN KEY (`service_category_id`) REFERENCES `service_categories` (`id`),
  CONSTRAINT `ck_05_01` CHECK (`active` IN (0, 1)),
  CONSTRAINT `ck_05_02` CHECK (`estimation_mode` IN ('manual', 'automatic'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `professional_profiles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `brand_name` VARCHAR(255) NOT NULL,
  `headline` VARCHAR(255) NOT NULL,
  `bio` TEXT NOT NULL,
  `availability_status` VARCHAR(32) NOT NULL DEFAULT 'available',
  `logo_path` VARCHAR(1024) NULL DEFAULT NULL,
  `created_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_06_01` (`user_id`),
  CONSTRAINT `fk_06_01` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `ck_06_01` CHECK (`availability_status` IN ('available', 'busy', 'unavailable'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `service_components` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `description` TEXT NOT NULL,
  `unit_code` VARCHAR(32) NOT NULL,
  `active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `is_default` BOOLEAN NOT NULL DEFAULT 0,
  `default_quantity` DECIMAL(12,3) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_07_01` (`service_id`, `slug`),
  CONSTRAINT `fk_07_01` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `ck_07_01` CHECK (`active` IN (0, 1)),
  CONSTRAINT `ck_07_02` CHECK (`is_default` IN (0, 1)),
  CONSTRAINT `ck_07_03` CHECK (`default_quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `professional_technologies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `professional_profile_id` BIGINT UNSIGNED NOT NULL,
  `technology_id` BIGINT UNSIGNED NOT NULL,
  `level` VARCHAR(32) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_08_01` (`professional_profile_id`, `technology_id`),
  KEY `ix_08_02` (`technology_id`),
  CONSTRAINT `fk_08_01` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profiles` (`id`),
  CONSTRAINT `fk_08_02` FOREIGN KEY (`technology_id`) REFERENCES `technologies` (`id`),
  CONSTRAINT `ck_08_01` CHECK (`level` IS NULL OR (`level` IN ('beginner', 'intermediate', 'advanced')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `professional_services` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `professional_profile_id` BIGINT UNSIGNED NOT NULL,
  `service_id` BIGINT UNSIGNED NOT NULL,
  `active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_09_01` (`professional_profile_id`, `service_id`),
  KEY `ix_09_02` (`service_id`),
  CONSTRAINT `fk_09_01` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profiles` (`id`),
  CONSTRAINT `fk_09_02` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `ck_09_01` CHECK (`active` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `portfolio_projects` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `professional_profile_id` BIGINT UNSIGNED NOT NULL,
  `service_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `live_url` VARCHAR(2048) NULL DEFAULT NULL,
  `is_public` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_10_01` (`professional_profile_id`),
  KEY `ix_10_02` (`service_id`),
  CONSTRAINT `fk_10_01` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profiles` (`id`),
  CONSTRAINT `fk_10_02` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `ck_10_01` CHECK (`is_public` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `service_prices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tariff_version_id` BIGINT UNSIGNED NOT NULL,
  `service_id` BIGINT UNSIGNED NOT NULL,
  `price_min` DECIMAL(15,2) NOT NULL,
  `price_max` DECIMAL(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_11_01` (`tariff_version_id`, `service_id`),
  KEY `ix_11_02` (`service_id`),
  CONSTRAINT `fk_11_01` FOREIGN KEY (`tariff_version_id`) REFERENCES `tariff_versions` (`id`),
  CONSTRAINT `fk_11_02` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `ck_11_01` CHECK (`price_min` >= 0 AND `price_max` >= `price_min`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `estimates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `service_id` BIGINT UNSIGNED NOT NULL,
  `tariff_version_id` BIGINT UNSIGNED NULL,
  `summary` TEXT NOT NULL,
  `base_min_snapshot` DECIMAL(15,2) NULL,
  `base_max_snapshot` DECIMAL(15,2) NULL,
  `price_min` DECIMAL(15,2) NULL,
  `price_max` DECIMAL(15,2) NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'calculated',
  `created_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_12_01` (`user_id`),
  KEY `ix_12_02` (`service_id`),
  KEY `ix_12_03` (`tariff_version_id`),
  CONSTRAINT `fk_12_01` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_12_02` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `fk_12_03` FOREIGN KEY (`tariff_version_id`) REFERENCES `tariff_versions` (`id`),
  CONSTRAINT `ck_12_01` CHECK (`status` IN ('calculated', 'archived', 'manual')),
  CONSTRAINT `ck_12_02` CHECK (`price_min` >= 0 AND `price_max` >= `price_min`),
  CONSTRAINT `ck_12_03` CHECK (`base_min_snapshot` >= 0 AND `base_max_snapshot` >= `base_min_snapshot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `professional_capabilities` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `professional_profile_id` BIGINT UNSIGNED NOT NULL,
  `service_component_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_13_01` (`professional_profile_id`, `service_component_id`),
  KEY `ix_13_02` (`service_component_id`),
  CONSTRAINT `fk_13_01` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profiles` (`id`),
  CONSTRAINT `fk_13_02` FOREIGN KEY (`service_component_id`) REFERENCES `service_components` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `portfolio_project_technologies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `portfolio_project_id` BIGINT UNSIGNED NOT NULL,
  `technology_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_14_01` (`portfolio_project_id`, `technology_id`),
  KEY `ix_14_02` (`technology_id`),
  CONSTRAINT `fk_14_01` FOREIGN KEY (`portfolio_project_id`) REFERENCES `portfolio_projects` (`id`),
  CONSTRAINT `fk_14_02` FOREIGN KEY (`technology_id`) REFERENCES `technologies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `portfolio_project_images` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `portfolio_project_id` BIGINT UNSIGNED NOT NULL,
  `image_path` VARCHAR(1024) NOT NULL,
  `alt_text` VARCHAR(255) NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_15_01` (`portfolio_project_id`, `sort_order`),
  CONSTRAINT `fk_15_01` FOREIGN KEY (`portfolio_project_id`) REFERENCES `portfolio_projects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `estimator_questions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `service_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `quantity_component_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `question` VARCHAR(1000) NOT NULL,
  `input_type` VARCHAR(32) NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `ix_16_01` (`service_id`, `active`, `sort_order`),
  KEY `ix_16_02` (`quantity_component_id`),
  CONSTRAINT `fk_16_01` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `fk_16_02` FOREIGN KEY (`quantity_component_id`) REFERENCES `service_components` (`id`),
  CONSTRAINT `ck_16_01` CHECK (`input_type` IN ('single_choice', 'multiple_choice', 'number', 'text')),
  CONSTRAINT `ck_16_02` CHECK (`active` IN (0, 1)),
  CONSTRAINT `ck_16_03` CHECK (`quantity_component_id` IS NULL OR (`input_type` = 'number' AND `service_id` IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `component_prices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tariff_version_id` BIGINT UNSIGNED NOT NULL,
  `service_component_id` BIGINT UNSIGNED NOT NULL,
  `price_min` DECIMAL(15,2) NOT NULL,
  `price_max` DECIMAL(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_17_01` (`tariff_version_id`, `service_component_id`),
  KEY `ix_17_02` (`service_component_id`),
  CONSTRAINT `fk_17_01` FOREIGN KEY (`tariff_version_id`) REFERENCES `tariff_versions` (`id`),
  CONSTRAINT `fk_17_02` FOREIGN KEY (`service_component_id`) REFERENCES `service_components` (`id`),
  CONSTRAINT `ck_17_01` CHECK (`price_min` >= 0 AND `price_max` >= `price_min`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `estimate_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estimate_id` BIGINT UNSIGNED NOT NULL,
  `service_component_id` BIGINT UNSIGNED NOT NULL,
  `component_name_snapshot` VARCHAR(255) NOT NULL,
  `unit_snapshot` VARCHAR(32) NOT NULL,
  `price_min_snapshot` DECIMAL(15,2) NOT NULL,
  `price_max_snapshot` DECIMAL(15,2) NOT NULL,
  `quantity` DECIMAL(12,3) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_18_01` (`estimate_id`, `service_component_id`),
  KEY `ix_18_02` (`service_component_id`),
  CONSTRAINT `fk_18_01` FOREIGN KEY (`estimate_id`) REFERENCES `estimates` (`id`),
  CONSTRAINT `fk_18_02` FOREIGN KEY (`service_component_id`) REFERENCES `service_components` (`id`),
  CONSTRAINT `ck_18_01` CHECK (`quantity` > 0),
  CONSTRAINT `ck_18_02` CHECK (`price_min_snapshot` >= 0 AND `price_max_snapshot` >= `price_min_snapshot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `project_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `estimate_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `service_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `visibility` VARCHAR(32) NOT NULL DEFAULT 'private',
  `status` VARCHAR(32) NOT NULL DEFAULT 'draft',
  `published_at` DATETIME(0) NULL DEFAULT NULL,
  `created_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_19_01` (`visibility`, `status`, `published_at`),
  KEY `ix_19_02` (`user_id`),
  KEY `ix_19_03` (`estimate_id`),
  KEY `ix_19_04` (`service_id`),
  CONSTRAINT `fk_19_01` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_19_02` FOREIGN KEY (`estimate_id`) REFERENCES `estimates` (`id`),
  CONSTRAINT `fk_19_03` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `ck_19_01` CHECK (`visibility` IN ('private', 'public')),
  CONSTRAINT `ck_19_02` CHECK (`status` IN ('draft', 'open', 'closed', 'cancelled')),
  CONSTRAINT `ck_19_03` CHECK (`status` IN ('draft', 'cancelled') OR `service_id` IS NOT NULL),
  CONSTRAINT `ck_19_04` CHECK (`visibility` <> 'public' OR `status` <> 'open' OR `published_at` IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `estimator_options` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `question_id` BIGINT UNSIGNED NOT NULL,
  `label` VARCHAR(255) NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_20_01` (`id`, `question_id`),
  KEY `ix_20_02` (`question_id`),
  CONSTRAINT `fk_20_01` FOREIGN KEY (`question_id`) REFERENCES `estimator_questions` (`id`),
  CONSTRAINT `ck_20_01` CHECK (`active` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `project_request_components` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_request_id` BIGINT UNSIGNED NOT NULL,
  `service_component_id` BIGINT UNSIGNED NOT NULL,
  `quantity` DECIMAL(12,3) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_21_01` (`project_request_id`, `service_component_id`),
  KEY `ix_21_02` (`service_component_id`),
  CONSTRAINT `fk_21_01` FOREIGN KEY (`project_request_id`) REFERENCES `project_requests` (`id`),
  CONSTRAINT `fk_21_02` FOREIGN KEY (`service_component_id`) REFERENCES `service_components` (`id`),
  CONSTRAINT `ck_21_01` CHECK (`quantity` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `project_request_invitations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_request_id` BIGINT UNSIGNED NOT NULL,
  `professional_profile_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `created_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_22_01` (`project_request_id`, `professional_profile_id`),
  KEY `ix_22_02` (`professional_profile_id`),
  CONSTRAINT `fk_22_01` FOREIGN KEY (`project_request_id`) REFERENCES `project_requests` (`id`),
  CONSTRAINT `fk_22_02` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profiles` (`id`),
  CONSTRAINT `ck_22_01` CHECK (`status` IN ('pending', 'accepted', 'declined'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `proposals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_request_id` BIGINT UNSIGNED NOT NULL,
  `professional_profile_id` BIGINT UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `estimated_delivery_days` INT UNSIGNED NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'submitted',
  `created_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_23_01` (`project_request_id`, `professional_profile_id`),
  KEY `ix_23_02` (`professional_profile_id`),
  CONSTRAINT `fk_23_01` FOREIGN KEY (`project_request_id`) REFERENCES `project_requests` (`id`),
  CONSTRAINT `fk_23_02` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profiles` (`id`),
  CONSTRAINT `ck_23_01` CHECK (`estimated_delivery_days` > 0),
  CONSTRAINT `ck_23_02` CHECK (`status` IN ('submitted', 'accepted', 'rejected', 'withdrawn'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `option_components` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estimator_option_id` BIGINT UNSIGNED NOT NULL,
  `service_component_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_24_01` (`estimator_option_id`, `service_component_id`),
  KEY `ix_24_02` (`service_component_id`),
  CONSTRAINT `fk_24_01` FOREIGN KEY (`estimator_option_id`) REFERENCES `estimator_options` (`id`),
  CONSTRAINT `fk_24_02` FOREIGN KEY (`service_component_id`) REFERENCES `service_components` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `option_services` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estimator_option_id` BIGINT UNSIGNED NOT NULL,
  `service_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_25_01` (`estimator_option_id`, `service_id`),
  KEY `ix_25_02` (`service_id`),
  CONSTRAINT `fk_25_01` FOREIGN KEY (`estimator_option_id`) REFERENCES `estimator_options` (`id`),
  CONSTRAINT `fk_25_02` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `estimate_answers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estimate_id` BIGINT UNSIGNED NOT NULL,
  `question_id` BIGINT UNSIGNED NOT NULL,
  `option_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `value_text` TEXT NULL DEFAULT NULL,
  `value_number` DECIMAL(15,4) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_26_01` (`estimate_id`, `question_id`, `option_id`),
  KEY `ix_26_02` (`question_id`),
  KEY `ix_26_03` (`option_id`, `question_id`),
  CONSTRAINT `fk_26_01` FOREIGN KEY (`estimate_id`) REFERENCES `estimates` (`id`),
  CONSTRAINT `fk_26_02` FOREIGN KEY (`question_id`) REFERENCES `estimator_questions` (`id`),
  CONSTRAINT `fk_26_03` FOREIGN KEY (`option_id`, `question_id`) REFERENCES `estimator_options` (`id`, `question_id`),
  CONSTRAINT `ck_26_01` CHECK (((`option_id` IS NOT NULL) + (`value_text` IS NOT NULL) + (`value_number` IS NOT NULL)) = 1),
  CONSTRAINT `ck_26_02` CHECK (`value_text` IS NULL OR CHAR_LENGTH(TRIM(`value_text`)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `budgets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposal_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `project_request_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `professional_profile_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `client_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `direct_service_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_27_01` (`proposal_id`),
  KEY `ix_27_02` (`project_request_id`),
  KEY `ix_27_03` (`professional_profile_id`),
  KEY `ix_27_04` (`client_user_id`),
  KEY `ix_27_05` (`direct_service_id`),
  CONSTRAINT `fk_27_01` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`),
  CONSTRAINT `fk_27_02` FOREIGN KEY (`project_request_id`) REFERENCES `project_requests` (`id`),
  CONSTRAINT `fk_27_03` FOREIGN KEY (`professional_profile_id`) REFERENCES `professional_profiles` (`id`),
  CONSTRAINT `fk_27_04` FOREIGN KEY (`client_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_27_05` FOREIGN KEY (`direct_service_id`) REFERENCES `services` (`id`),
  CONSTRAINT `ck_27_01` CHECK ((
      (`proposal_id` IS NOT NULL AND `project_request_id` IS NULL AND `professional_profile_id` IS NULL AND `client_user_id` IS NULL AND `direct_service_id` IS NULL)
      OR (`proposal_id` IS NULL AND `project_request_id` IS NOT NULL AND `professional_profile_id` IS NOT NULL AND `client_user_id` IS NULL AND `direct_service_id` IS NULL)
      OR (`proposal_id` IS NULL AND `project_request_id` IS NULL AND `professional_profile_id` IS NOT NULL AND `client_user_id` IS NOT NULL AND `direct_service_id` IS NOT NULL)
    ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `project_request_answers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_request_id` BIGINT UNSIGNED NOT NULL,
  `question_id` BIGINT UNSIGNED NOT NULL,
  `option_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `value_text` TEXT NULL DEFAULT NULL,
  `value_number` DECIMAL(15,4) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_28_01` (`project_request_id`, `question_id`, `option_id`),
  KEY `ix_28_02` (`question_id`),
  KEY `ix_28_03` (`option_id`, `question_id`),
  CONSTRAINT `fk_28_01` FOREIGN KEY (`project_request_id`) REFERENCES `project_requests` (`id`),
  CONSTRAINT `fk_28_02` FOREIGN KEY (`question_id`) REFERENCES `estimator_questions` (`id`),
  CONSTRAINT `fk_28_03` FOREIGN KEY (`option_id`, `question_id`) REFERENCES `estimator_options` (`id`, `question_id`),
  CONSTRAINT `ck_28_01` CHECK (((`option_id` IS NOT NULL) + (`value_text` IS NOT NULL) + (`value_number` IS NOT NULL)) = 1),
  CONSTRAINT `ck_28_02` CHECK (`value_text` IS NULL OR CHAR_LENGTH(TRIM(`value_text`)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `budget_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `budget_id` BIGINT UNSIGNED NOT NULL,
  `version_number` INT UNSIGNED NOT NULL,
  `currency_code` CHAR(3) NOT NULL,
  `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `valid_until` DATE NOT NULL,
  `terms` TEXT NOT NULL,
  `estimated_delivery_days` INT UNSIGNED NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'draft',
  `created_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_29_01` (`budget_id`, `version_number`),
  KEY `ix_29_02` (`budget_id`, `status`),
  CONSTRAINT `fk_29_01` FOREIGN KEY (`budget_id`) REFERENCES `budgets` (`id`),
  CONSTRAINT `ck_29_01` CHECK (`version_number` > 0),
  CONSTRAINT `ck_29_02` CHECK (CHAR_LENGTH(`currency_code`) = 3 AND ASCII(SUBSTRING(`currency_code`, 1, 1)) BETWEEN 65 AND 90 AND ASCII(SUBSTRING(`currency_code`, 2, 1)) BETWEEN 65 AND 90 AND ASCII(SUBSTRING(`currency_code`, 3, 1)) BETWEEN 65 AND 90),
  CONSTRAINT `ck_29_03` CHECK (`tax_rate` BETWEEN 0 AND 100),
  CONSTRAINT `ck_29_04` CHECK (`estimated_delivery_days` > 0),
  CONSTRAINT `ck_29_05` CHECK (`status` IN ('draft', 'sent', 'accepted', 'rejected', 'expired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `budget_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `budget_version_id` BIGINT UNSIGNED NOT NULL,
  `service_component_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `description` TEXT NOT NULL,
  `unit_label` VARCHAR(50) NOT NULL,
  `quantity` DECIMAL(12,3) NOT NULL DEFAULT 1,
  `unit_price` DECIMAL(15,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_30_01` (`budget_version_id`),
  KEY `ix_30_02` (`service_component_id`),
  CONSTRAINT `fk_30_01` FOREIGN KEY (`budget_version_id`) REFERENCES `budget_versions` (`id`),
  CONSTRAINT `fk_30_02` FOREIGN KEY (`service_component_id`) REFERENCES `service_components` (`id`),
  CONSTRAINT `ck_30_01` CHECK (`quantity` > 0),
  CONSTRAINT `ck_30_02` CHECK (`unit_price` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            <<<'SQL'
CREATE TABLE `projects` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `accepted_budget_version_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `start_date` DATE NULL DEFAULT NULL,
  `end_date` DATE NULL DEFAULT NULL,
  `created_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME(0) NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_31_01` (`accepted_budget_version_id`),
  CONSTRAINT `fk_31_01` FOREIGN KEY (`accepted_budget_version_id`) REFERENCES `budget_versions` (`id`),
  CONSTRAINT `ck_31_01` CHECK (`status` IN ('pending', 'in_progress', 'completed', 'cancelled')),
  CONSTRAINT `ck_31_02` CHECK (`start_date` IS NULL OR `end_date` IS NULL OR `end_date` >= `start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ];
        foreach ($statements as $statement) {
            DB::statement($statement);
        }
    }

    public function down(): void
    {
        // Solo se utiliza al solicitar expresamente una reversión de esta migración.
        foreach (['projects', 'budget_items', 'budget_versions', 'project_request_answers', 'budgets', 'estimate_answers', 'option_services', 'option_components', 'proposals', 'project_request_invitations', 'project_request_components', 'estimator_options', 'project_requests', 'estimate_items', 'component_prices', 'estimator_questions', 'portfolio_project_images', 'portfolio_project_technologies', 'professional_capabilities', 'estimates', 'service_prices', 'portfolio_projects', 'professional_services', 'professional_technologies', 'service_components', 'professional_profiles', 'services', 'service_categories', 'tariff_versions', 'technologies'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
