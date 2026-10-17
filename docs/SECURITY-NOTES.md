# SQL injection and page input review

The homepage category, seller, and latest listing queries use fixed SQL with no request values interpolated into SQL. Category IDs rendered in browse links are cast to integers, and database text displayed in HTML is escaped with `e()`.

The browse page builds optional `WHERE` conditions only from fixed SQL fragments. Search text, category IDs, location, region, and price limits are passed to PDO as bound parameters through `prepare()` and `execute()`. User input does not select column names or SQL operators. Text filters now reject non-string query values and have length limits; category IDs must be positive integers, and prices must be non-negative numeric values.

Continue these rules for future changes:

- Never concatenate request values into SQL. Bind every value with PDO placeholders.
- For dynamic table names, column names, or sort order, choose from a fixed allowlist; SQL placeholders cannot bind identifiers.
- Escape database content when rendering HTML with `e()` and encode URL components when building query strings.
- Keep PDO exceptions enabled and emulated prepares disabled in `config/db.php`.
- Keep production database credentials outside source control in Hostinger's private environment configuration.

These safeguards address SQL injection in the homepage and browse filters. They do not replace a full security audit of every route or production server configuration.
