CREATE TABLE `login_limits` (
	`key` text PRIMARY KEY NOT NULL,
	`window_start` integer NOT NULL,
	`attempts` integer DEFAULT 0 NOT NULL
);
--> statement-breakpoint
CREATE TABLE `login_sessions` (
	`token_hash` text PRIMARY KEY NOT NULL,
	`account_id` text,
	`role` text NOT NULL,
	`credential_version` text NOT NULL,
	`created_at` integer NOT NULL,
	`expires_at` integer NOT NULL,
	FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON UPDATE no action ON DELETE no action
);
--> statement-breakpoint
ALTER TABLE `accounts` ADD `username` text;--> statement-breakpoint
ALTER TABLE `accounts` ADD `password_hash` text;--> statement-breakpoint
ALTER TABLE `accounts` ADD `login_version` integer DEFAULT 1 NOT NULL;--> statement-breakpoint
CREATE UNIQUE INDEX `accounts_username_unique` ON `accounts` (`username`);