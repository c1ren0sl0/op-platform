# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# Operational Platform (op-platform) - Claude Code Instructions

**Plugin:** op-platform
**WordPress Path (Local):** `/Users/ericolson/Local Sites/6pointco-dev/app/public/`
**Plugin Path:** `wp-content/plugins/op-platform/`
**Library Path (Local):** `/Users/ericolson/decision-substrate-library/`

**Repository:** https://github.com/c1ren0sl0/op-platform

**Production (WP Engine):**
- Site: `sixpointcodev.wpenginepowered.com`
- Library Path: `/nas/content/live/sixpointcodev/_wpeprivate/library`

---

## Purpose

The Operational Platform is a **schema-ignorant** content platform that:

1. **Builds page tree** from `/platform/` filesystem
2. **Derives navigation** from page tree
3. **Registers routes** and handles requests
4. **Renders generic** directory/detail pages using provider-supplied configuration
5. **Provides admin UI** for platform status and configuration

It does NOT know about specific content schemas (like `source.schema.yaml`). Instead, it asks content providers: "what types do you have?" and "give me items of type X with these filters."

---

## Architecture

### Core Principle

The operational platform is **schema-ignorant**. Content providers handle:
- Schema definitions
- Artifact indexing
- Access control logic
- Content item details

The platform provides:
- Page tree building from filesystem
- Navigation derivation
- Route registration
- Generic rendering using provider-supplied type configs

### Content Provider Interface

```php
interface OP_Content_Provider {
    public function get_id(): string;
    public function get_label(): string;
    public function get_types(): array;
    public function get_type_config( string $type ): ?OP_Type_Config;
    public function query( string $type, array $args = [] ): array;
    public function get_item( string $type, string $slug ): ?OP_Content_Item;
    public function check_access( string $type, string $slug, ?int $user_id = null ): array;
    public function get_detail_template( string $type ): ?string;
    public function get_card_template( string $type ): ?string;
}
```

### Provider Registration

Providers register themselves via the `op_platform_register_providers` action:

```php
add_action( 'op_platform_register_providers', function() {
    OP_Provider_Registry::register( new My_Content_Provider() );
});
```

---

## File Structure

```
op-platform/
├── op-platform.php           # Main plugin file
├── includes/
│   ├── interfaces/
│   │   ├── interface-content-provider.php
│   │   ├── interface-type-config.php
│   │   └── interface-content-item.php
│   ├── class-plugin.php               # Orchestrator
│   ├── class-config.php               # Library path config
│   ├── class-provider-registry.php    # Provider management
│   ├── class-platform.php             # /platform/ scanner
│   ├── class-page-tree.php            # Page hierarchy
│   ├── class-navigation.php           # Nav derivation
│   ├── class-router.php               # URL routing (delegates to providers)
│   ├── class-markdown-parser.php      # Utility
│   ├── class-diagnostics.php          # Platform health
│   ├── entities/
│   │   └── class-page.php             # Page entity
│   └── admin/
│       ├── class-admin.php
│       ├── class-admin-dashboard.php
│       ├── class-admin-platform.php
│       └── class-admin-navigation.php
├── templates/
│   ├── frontend/
│   │   ├── operational.php            # Generic grid (uses type_config)
│   │   ├── artifact-detail.php        # Artifact detail page
│   │   └── gated.php                  # Access denied
│   └── admin/
│       └── *.php
└── assets/
    ├── css/
    │   └── frontend.css
    └── js/
        └── frontend.js
```

---

## Coding Standards

### File Operations
- **Replace whole files** - no cumulative patching
- **Full-schema returns** - complete, self-contained, supersedes previous
- **Explicit versioning** - version, layer, reason documented

### Naming Conventions
- Function prefix: `op_`
- Class prefix: `OP_`
- Hook prefix: `op_platform_`
- Option prefix: `op_platform_`

### PHP Standards
- WordPress Coding Standards
- PHP 8.2+ compatibility
- Strict typing where possible

---

## Hooks Provided

```php
// Provider registration
do_action( 'op_platform_register_providers' );

// Rendering
do_action( 'op_platform_before_page_render', $page );
do_action( 'op_platform_after_page_render', $page );

// Query modification
apply_filters( 'op_platform_artifact_query_args', $args, $type, $page );
apply_filters( 'op_platform_artifact_items', $items, $type, $page );

// Templates
apply_filters( 'op_platform_template_path', $path, $template, $type );

// Card data
apply_filters( 'op_platform_card_data', $data, $item, $type );
```

---

## Constants

```php
define( 'OP_VERSION', '1.0.0' );
define( 'OP_PLUGIN_FILE', __FILE__ );
define( 'OP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
```

---

## REST API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/op-platform/v1/status` | GET | Platform status |
| `/op-platform/v1/platform/status` | GET | Platform health details |
| `/op-platform/v1/platform/tree` | GET | Page tree structure |
| `/op-platform/v1/platform/navigation` | GET | Navigation structure |
| `/op-platform/v1/platform/rebuild` | POST | Rebuild platform |
| `/op-platform/v1/providers` | GET | List registered providers |

---

## Deployment

The plugin auto-deploys to WP Engine on push to `main`.

```bash
# Deploy plugin changes
cd /Users/ericolson/Local\ Sites/6pointco-dev/app/public/wp-content/plugins/op-platform
git add -A && git commit -m "message" && git push origin main

# Check deployment status
gh run list --limit 3

# Add WP Engine secret to repo
gh secret set WPE_SSHG_KEY_PRIVATE --repo c1ren0sl0/op-platform < ~/.ssh/wpengine_deploy
```

---

## Related Plugins

| Plugin | Repository | Purpose |
|--------|------------|---------|
| decision-substrate | c1ren0sl0/decision-substrate | Content provider for library artifacts |
| sixpoint-theme | c1ren0sl0/sixpoint-theme | Theme with op-platform integration |

---

## Meta-Principles

> Clarity beats speed.
> Determinism beats cleverness.
> Replaceability beats convenience.
> Observability beats explanation.
