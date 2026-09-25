# Database

PostgreSQL 16. All tables carry `created_at`, `updated_at`; user-facing content also carries
`deleted_at` (soft delete). Money is `numeric(12,2)` plus a currency code; never floats.
Every foreign key is indexed. Enumerations are Postgres enums.

## ERD (core)

```
Brand ─┬─< Product >─┬─< ProductVariant >─< MerchantProduct >─┬─< Price >─< PriceHistory
       │             │                                        └─< Offer
Category ─< Product  ├─< ProductImage
                     ├─< ProductIngredient >─ Ingredient
                     ├─< ProductComplianceRule >─ Country
                     ├─< Review >─┬─< ReviewVote
                     │            ├─< ReviewReport
                     │            └─< Comment
                     ├─< Favorite / Watchlist / PriceAlert >─ User
                     └─< SponsoredPlacement

Merchant ─┬─< MerchantUser >─ User
          ├─< MerchantVerification
          ├─< MerchantProduct
          ├─< Coupon
          ├─< ShippingOption >─ Country
          ├─< AffiliateProgram >─ AffiliateNetwork
          ├─< Subscription >─< Invoice
          └─< Review (type = merchant)

AffiliateClick ─< AffiliateConversion ─< Commission
User ─< Notification, ConsentLog, AuditLog(actor)
Article ─< ArticleTag >─ Tag
```

## Tables

### Identity and accounts

**user** — `id`, `email` (unique, citext), `password_hash` (Argon2id), `email_verified_at`,
`role` (`user|editor|moderator|affiliate_manager|compliance_manager|account_manager|admin|superadmin`),
`two_factor_secret`, `last_login_at`, `deleted_at`.
**user_profile** — `user_id` (unique), `nickname` (unique), `avatar_path`, `country_id`,
`language`, `currency`, `public_profile`, `newsletter_opt_in`, `marketing_opt_in`.
**consent_log** — `user_id`, `scope`, `granted`, `source`, `ip_hash`, `created_at`. Append-only.

### Geography and money

**country** — `iso2` (unique), `name`, `currency_code`, `default_language`, `vat_rate`,
`min_age`, `shipping_rules` (jsonb), `tax_config` (jsonb), `active`.
**currency** — `code` (unique), `symbol`, `decimals`, `rate_to_eur`, `rate_updated_at`.
**language** — `code`, `name`, `enabled`, `fallback_code`.

### Catalogue

**brand** — `slug` (unique), `name`, `country_id`, `description`, `logo_path`, `founded_year`.
**category** — `slug` (unique), `parent_id` (self FK), `name`, `position`, `seo` (jsonb).
**product** — `slug` (unique), `brand_id`, `category_id`, `name`, `short_description`,
`description`, `gtin` (unique, nullable), `internal_sku` (unique), `rrp`, `rrp_currency`,
`servings`, `pack_size`, `unit`, `status` (`draft|active|merged|archived`),
`merged_into_id` (self FK, for de-duplication).
**product_variant** — `product_id`, `name` (flavour), `pack_size`, `gtin`,
unique `(product_id, name, pack_size)`.
**product_image** — `product_id`, `path`, `position`, `alt`, `source`.
**ingredient** / **product_ingredient** — `product_id`, `ingredient_id`, `amount`, `unit`,
unique `(product_id, ingredient_id)`.

### Merchants

**merchant** — `slug` (unique), `name`, `website`, `country_id`, `status`
(`pending|verified|rejected|suspended`), `verified_at`, `tier` (`free|pro|premium`),
`free_shipping_threshold`, `return_days`, `support_email`, `description`,
`payment_methods` (text[]), `carriers` (text[]), `currencies` (text[]).
**merchant_user** — `merchant_id`, `user_id`, `role` (`owner|manager|analyst`),
unique `(merchant_id, user_id)`.
**merchant_verification** — `merchant_id`, `legal_name`, `registration_number`, `vat_number`,
`billing_address` (jsonb), `documents` (jsonb), `reviewed_by`, `reviewed_at`, `decision_note`.
**shipping_option** — `merchant_id`, `country_id`, `carrier`, `cost`, `currency`,
`days_min`, `days_max`, `free_over`, unique `(merchant_id, country_id, carrier)`.
**subscription** — `merchant_id`, `plan`, `price`, `status`, `started_at`, `renews_at`.
**invoice** — `merchant_id`, `number` (unique), `amount`, `currency`, `status`,
`issued_at`, `due_at`, `lines` (jsonb), `pdf_path`.

### Offers and pricing

**merchant_product** — `merchant_id`, `product_id` (nullable until matched), `variant_id`,
`merchant_sku`, `gtin`, `raw_title`, `raw_payload` (jsonb), `product_url`, `affiliate_url`,
`match_type` (`auto|suggested|manual|created|unmatched`), `match_confidence`,
`matched_by`, `matched_at`, unique `(merchant_id, merchant_sku)`.
**price** — current state, one row per merchant_product: `price`, `old_price`, `currency`,
`availability` (`in_stock|low_stock|preorder|out_of_stock`), `stock`, `warehouse_country_id`,
`updated_at`.
**price_history** — `merchant_product_id`, `captured_on` (date), `price`, `currency`,
`availability`, unique `(merchant_product_id, captured_on)`. Partitioned monthly. Aggregates
(`product_price_daily`: min, avg, offer_count per product/country/day) are materialised for charts.
**offer** — a published, compliance-cleared merchant_product: `merchant_product_id`,
`starts_at`, `ends_at`, `is_sponsored`, `position_boost`, `active`.
**coupon** — `merchant_id`, `code`, `title`, `description`, `discount_type`
(`percent|fixed|free_shipping|bundle`), `discount_value`, `min_order`, `countries` (text[]),
`product_ids` (int[]), `category_ids` (int[]), `starts_at`, `ends_at`, `is_exclusive`,
`uses`, `verified_at`, unique `(merchant_id, code)`.
**sponsored_placement** — `merchant_id`, `type`, `slot`, `starts_at`, `ends_at`, `budget`,
`spent`, `cpc`, `status`.

### Reviews and community

**review** — `type` (`product|merchant`), `target_id`, `user_id`, `merchant_id` (where bought),
`rating` (1–5), `title`, `body`, `pros` (text[]), `cons` (text[]), `recommends`,
`sub_ratings` (jsonb: shipping, communication, price, support, order),
`verified_purchase`, `proof_hash` (order confirmation digest — never the document),
`status` (`pending|approved|rejected|flagged|hidden`), `moderated_by`, `moderated_at`,
`helpful_count`, `not_helpful_count`, `ip_hash`, `device_hash`,
unique `(type, target_id, user_id)` where `deleted_at is null` — one review per target per user.
**review_reply** — `review_id` (unique), `merchant_id`, `body`, `author_user_id`, `published_at`.
**review_vote** — `review_id`, `user_id`, `value` (+1/−1), unique `(review_id, user_id)`.
**review_report** — `review_id`, `user_id`, `reason`, `note`, `status`, `resolved_by`.
**comment** — `review_id`, `user_id`, `body`, `status`, `parent_id`.

### User signals

**favorite** — `user_id`, `product_id`, unique pair.
**watchlist** — `user_id`, `entity_type` (`product|brand|merchant|category`), `entity_id`, unique triple.
**price_alert** — `user_id`, `product_id`, `target_total`, `currency`, `country_id`,
`channels` (text[]), `status` (`active|triggered|paused`), `triggered_at`, `last_checked_at`.
**notification** — `user_id`, `kind`, `payload` (jsonb), `read_at`, `sent_at`, `channel`.

### Compliance

**product_compliance_rule** — `product_id`, `country_id`,
`status` (`allowed|restricted|prescription_only|not_allowed|unknown`), `reason`, `source`,
`reviewed_by`, `reviewed_at`, `expires_at`, unique `(product_id, country_id)`.
Absence of a row is treated as `unknown` for newly imported products and `allowed` only for
categories explicitly whitelisted per market. See [COMPLIANCE.md](COMPLIANCE.md).

### Affiliate

**affiliate_network** — `name`, `api_base`, `credentials_encrypted`, `postback_secret`.
**affiliate_program** — `merchant_id`, `network_id` (nullable = direct), `commission_rate`,
`commission_type` (`percent|fixed|cpc`), `cookie_days`, `tracking_template`, `sub_id_prefix`,
`status`.
**affiliate_click** — `id` (uuid), `merchant_id`, `product_id`, `merchant_product_id`,
`campaign`, `placement`, `country_id`, `currency`, `session_hash`, `user_id` (nullable),
`sub_id`, `network_id`, `user_agent_class`, `created_at`. Append-only, partitioned monthly,
no raw IP stored.
**affiliate_conversion** — `click_id`, `network_order_id` (unique per network),
`order_value`, `currency`, `status` (`pending|approved|rejected`), `converted_at`,
`reported_at`.
**commission** — `conversion_id` (unique), `amount`, `currency`, `rate`, `status`,
`payout_batch_id`.

### Community

**thread** — `slug` (unique), `category_id`, `user_id`, `kind` (`discussion|question`), `title`,
`body`, `tags` (text[]), `country_id`, `product_id`, `merchant_id`, `views`, `votes`,
`reply_count`, `accepted_reply_id` (FK reply, nullable), `pinned`, `locked`,
`status` (`approved|pending|hidden|rejected`), `last_activity_at`.
**forum_category** — `slug` (unique), `name`, `description`, `position`, `icon`, `locked`.
**reply** — `thread_id`, `user_id`, `body`, `quote_of_id` (self FK), `votes`, `status`.
**guide** — `slug` (unique), `user_id`, `title`, `category`, `tags` (text[]), `excerpt`,
`body`, `related_product_ids` (int[]), `related_merchant_ids` (int[]), `views`, `votes`,
`status`, `reviewed_by`, `published_at`.
**community_deal** — `user_id`, `merchant_id`, `product_id`, `title`, `description`, `price`,
`old_price`, `currency`, `coupon_code`, `country_id`, `link`, `expires_at`,
`status` (`pending|approved|rejected|expired`), `votes_good`, `votes_expired`, `votes_wrong`.
**vote** — `user_id`, `entity_type` (`thread|reply|guide|deal|qa|review`), `entity_id`,
`value`, unique `(user_id, entity_type, entity_id, kind)`.
**follow** — `user_id`, `entity_type` (`user|product|brand|merchant|thread|category`),
`entity_id`, unique triple.
**list** — `user_id`, `name`, `slug`, `visibility` (`private|unlisted|public`);
**list_item** — `list_id`, `entity_type`, `entity_id`, `position`.
**shop_question** — `merchant_id`, `asked_by`, `question`, `answer`, `answered_by`,
`is_official`, `votes`, `status`.
**merchant_announcement** — `merchant_id`, `kind`, `title`, `body`, `published_at`, `status`.
**activity_item** — `user_id`, `kind`, `entity_type`, `entity_id`, `created_at`
(append-only, partitioned monthly; the feed reads only this table).
**mute** / **block** — `user_id`, `target_user_id`, unique pair.

### Reputation

**reputation_event** — `user_id`, `action`, `points`, `entity_type`, `entity_id`,
`created_at`; the user's reputation is `sum(points)` and is cached on `user_profile.reputation`.
Reversals are new negative rows, never deletes.
**badge** — `key` (unique), `label`, `description`, `colour`, `auto_rule` (jsonb, nullable).
**user_badge** — `user_id`, `badge_key`, `granted_by`, `granted_at`, unique pair.
**feature_flag** — `key` (unique), `enabled`, `updated_by`, `updated_at`.

### Content and platform

**article** — `slug` (unique), `type` (`article|guide|review|comparison|news|brand|merchant`),
`title`, `excerpt`, `body` (markdown), `hero_path`, `author_id`, `status`, `published_at`,
`seo` (jsonb: title, description, canonical, og), `locale`, `translation_of_id`.
**tag** / **article_tag** — many-to-many, unique pair.
**audit_log** — `actor_id`, `actor_email`, `action` (dotted, e.g. `merchant.verified`),
`entity_type`, `entity_id`, `before` (jsonb), `after` (jsonb), `ip_hash`, `created_at`.
Append-only; no deletes, no updates.
**setting** — `key` (unique), `value` (jsonb), `updated_by`.

## Indexes worth stating

```sql
create index on product (category_id, status);
create index on product using gin (to_tsvector('simple', name));
create unique index on merchant_product (merchant_id, merchant_sku);
create index on merchant_product (product_id) where product_id is not null;
create index on price (merchant_product_id, availability);
create index on price_history (merchant_product_id, captured_on desc);
create index on review (type, target_id, status, created_at desc);
create index on affiliate_click (merchant_id, created_at desc);
create index on affiliate_click (session_hash, created_at desc);
create unique index on product_compliance_rule (product_id, country_id);
create index on coupon (merchant_id, ends_at) where ends_at > now();
```

## Invariants

1. A `merchant_product` may reference at most one `product`; re-matching writes an audit entry.
2. `price_history` is immutable; corrections are new rows, never updates.
3. A review is publicly readable only in status `approved`.
4. An offer is publicly readable only when its product's compliance status for the requested
   country is `allowed` or `restricted` (restricted renders a warning and no recommendation).
5. Aggregate ratings are always computed from approved reviews — never stored by hand, never seeded.
6. A thread may have at most one accepted reply, and only a reply belonging to that thread.
7. A community deal is publicly listed only in status `approved`; a guide only when `approved`.
8. Reputation is derived from `reputation_event`; the cached value on `user_profile` is a
   denormalisation that can always be rebuilt from the event log.
