import { sqliteTable, text, integer } from 'drizzle-orm/sqlite-core';
export const orders=sqliteTable('orders',{
 id:text('id').primaryKey(),
 idempotencyKey:text('idempotency_key').notNull().unique(),
 requestHash:text('request_hash').notNull(),
 customerName:text('customer_name').notNull(),
 phone:text('phone').notNull(),
 address:text('address').notNull(),
 note:text('note').notNull(),
 payment:text('payment').notNull(),
 items:text('items').notNull(),
 subtotal:integer('subtotal').notNull(),
 delivery:integer('delivery').notNull(),
 total:integer('total').notNull(),
 status:text('status').notNull().default('new'),
 notificationStatus:text('notification_status').notNull().default('pending'),
 createdAt:text('created_at').notNull(),
 channel:text('channel').notNull().default('retail'),
 resellerId:text('reseller_id').references(()=>accounts.id),
 paymentStatus:text('payment_status').notNull().default('unverified'),
 paidAt:text('paid_at')
});

export const accounts=sqliteTable('accounts',{
 id:text('id').primaryKey(), email:text('email').notNull().unique(), userId:text('user_id').unique(), name:text('name').notNull(), phone:text('phone').notNull().default(''), role:text('role').notNull().default('reseller'), username:text('username').unique(), passwordHash:text('password_hash'), loginVersion:integer('login_version').notNull().default(1), active:integer('active').notNull().default(1), createdAt:text('created_at').notNull(), updatedAt:text('updated_at').notNull()
});
export const products=sqliteTable('products',{
 sku:text('sku').primaryKey(), name:text('name').notNull(), en:text('en').notNull().default(''), step:text('step').notNull().default(''), role:text('role').notNull().default(''), description:text('description').notNull().default(''), tags:text('tags').notNull().default('[]'), image:text('image').notNull(), retailPrice:integer('retail_price').notNull(), resellerPrice:integer('reseller_price'), active:integer('active').notNull().default(1), bundle:integer('bundle').notNull().default(0), sortOrder:integer('sort_order').notNull().default(0), revision:integer('revision').notNull().default(1), updatedAt:text('updated_at').notNull()
});
export const heroBanners=sqliteTable('hero_banners',{
 id:text('id').primaryKey(), desktopImage:text('desktop_image').notNull(), mobileImage:text('mobile_image').notNull(), alt:text('alt').notNull(), kicker:text('kicker').notNull(), title:text('title').notNull(), body:text('body').notNull(), cta:text('cta').notNull(), href:text('href').notNull(), active:integer('active').notNull().default(1), sortOrder:integer('sort_order').notNull().default(0), revision:integer('revision').notNull().default(1), updatedAt:text('updated_at').notNull()
});
export const settings=sqliteTable('settings',{key:text('key').primaryKey(),value:text('value').notNull()});

// Payment attempts are separate from fulfillment status and use order price snapshots.
export const paymentAttempts=sqliteTable('payment_attempts',{
 id:text('id').primaryKey(), orderId:text('order_id').notNull().references(()=>orders.id), provider:text('provider').notNull().default('qpay'), environment:text('environment').notNull().default('sandbox'), amount:integer('amount').notNull(), currency:text('currency').notNull().default('MNT'), status:text('status').notNull().default('prepared'), providerInvoiceId:text('provider_invoice_id').unique(), providerPaymentId:text('provider_payment_id').unique(), createdAt:text('created_at').notNull(), updatedAt:text('updated_at').notNull(), paidAt:text('paid_at')
});

export const loginSessions=sqliteTable('login_sessions',{
 tokenHash:text('token_hash').primaryKey(), accountId:text('account_id').references(()=>accounts.id), role:text('role').notNull(), credentialVersion:text('credential_version').notNull(), createdAt:integer('created_at').notNull(), expiresAt:integer('expires_at').notNull()
});
export const loginLimits=sqliteTable('login_limits',{
 key:text('key').primaryKey(), windowStart:integer('window_start').notNull(), attempts:integer('attempts').notNull().default(0)
});
