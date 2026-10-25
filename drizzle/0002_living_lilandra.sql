CREATE TABLE `payment_attempts` (
	`id` text PRIMARY KEY NOT NULL,
	`order_id` text NOT NULL,
	`provider` text DEFAULT 'qpay' NOT NULL,
	`environment` text DEFAULT 'sandbox' NOT NULL,
	`amount` integer NOT NULL,
	`currency` text DEFAULT 'MNT' NOT NULL,
	`status` text DEFAULT 'prepared' NOT NULL,
	`provider_invoice_id` text,
	`provider_payment_id` text,
	`created_at` text NOT NULL,
	`updated_at` text NOT NULL,
	`paid_at` text,
	FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON UPDATE no action ON DELETE no action
);
--> statement-breakpoint
CREATE UNIQUE INDEX `payment_attempts_provider_invoice_id_unique` ON `payment_attempts` (`provider_invoice_id`);--> statement-breakpoint
CREATE UNIQUE INDEX `payment_attempts_provider_payment_id_unique` ON `payment_attempts` (`provider_payment_id`);--> statement-breakpoint
ALTER TABLE `orders` ADD `payment_status` text DEFAULT 'unverified' NOT NULL;--> statement-breakpoint
ALTER TABLE `orders` ADD `paid_at` text;