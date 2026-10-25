CREATE TABLE `accounts` (
	`id` text PRIMARY KEY NOT NULL,
	`email` text NOT NULL,
	`user_id` text,
	`name` text NOT NULL,
	`phone` text DEFAULT '' NOT NULL,
	`role` text DEFAULT 'reseller' NOT NULL,
	`active` integer DEFAULT 1 NOT NULL,
	`created_at` text NOT NULL,
	`updated_at` text NOT NULL
);
--> statement-breakpoint
CREATE UNIQUE INDEX `accounts_email_unique` ON `accounts` (`email`);--> statement-breakpoint
CREATE UNIQUE INDEX `accounts_user_id_unique` ON `accounts` (`user_id`);--> statement-breakpoint
CREATE TABLE `hero_banners` (
	`id` text PRIMARY KEY NOT NULL,
	`desktop_image` text NOT NULL,
	`mobile_image` text NOT NULL,
	`alt` text NOT NULL,
	`kicker` text NOT NULL,
	`title` text NOT NULL,
	`body` text NOT NULL,
	`cta` text NOT NULL,
	`href` text NOT NULL,
	`active` integer DEFAULT 1 NOT NULL,
	`sort_order` integer DEFAULT 0 NOT NULL,
	`revision` integer DEFAULT 1 NOT NULL,
	`updated_at` text NOT NULL
);
--> statement-breakpoint
CREATE TABLE `products` (
	`sku` text PRIMARY KEY NOT NULL,
	`name` text NOT NULL,
	`en` text DEFAULT '' NOT NULL,
	`step` text DEFAULT '' NOT NULL,
	`role` text DEFAULT '' NOT NULL,
	`description` text DEFAULT '' NOT NULL,
	`tags` text DEFAULT '[]' NOT NULL,
	`image` text NOT NULL,
	`retail_price` integer NOT NULL,
	`reseller_price` integer,
	`active` integer DEFAULT 1 NOT NULL,
	`bundle` integer DEFAULT 0 NOT NULL,
	`sort_order` integer DEFAULT 0 NOT NULL,
	`revision` integer DEFAULT 1 NOT NULL,
	`updated_at` text NOT NULL
);
--> statement-breakpoint
CREATE TABLE `settings` (
	`key` text PRIMARY KEY NOT NULL,
	`value` text NOT NULL
);
--> statement-breakpoint
ALTER TABLE `orders` ADD `channel` text DEFAULT 'retail' NOT NULL;--> statement-breakpoint
ALTER TABLE `orders` ADD `reseller_id` text REFERENCES accounts(id);