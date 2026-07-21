# Shared game assets

KaevCMS 0.26.0 introduces one persistent directory for reusable game images:

```text
public/uploads/game-assets/
```

This is preferable to an `image` directory in the project root because files are directly web-accessible, are kept with other persistent uploads and are not replaced by release patches.

## Directory layout

```text
public/uploads/game-assets/
├─ items/
│  ├─ common/
│  │  ├─ 57.webp
│  │  └─ 4037.png
│  └─ servers/
│     └─ 3/
│        └─ 57.png
└─ characters/
   ├─ common/
   │  └─ human-female-mage.webp
   └─ servers/
      └─ 3/
         └─ human-female-mage.png
```

Supported extensions are checked in this order:

```text
webp, png, jpg, jpeg
```

## Item icon resolution

Modules and core pages use `App\Services\GameAssets\GameAssetUrlResolver` instead of constructing paths themselves.

For item `57` on GameServer `3`, the resolver checks:

1. `items/servers/3/57.webp`, then the other supported extensions;
2. `items/common/57.webp`, then the other supported extensions;
3. no image if neither exists.

This permits a shared Lineage II icon set while allowing one server or custom build to override an icon with a different appearance.

```php
$url = $assets->itemIcon($gameServer, 57);
```

The web inventory and bundled promo-code module already use this resolver. Donation, voting and other future modules should use the same service.

## Character avatars

Character images use a safe alphanumeric key instead of a database item ID:

```php
$url = $assets->characterAvatar($gameServer, 'human-female-mage');
```

Server-specific files take priority over `characters/common/` in the same way as item icons.

## Operations

`setup.ps1` and `update.ps1` create the directory structure but never delete its contents. Include the complete `public/uploads` directory in backups. System diagnostics checks that `public/uploads/game-assets` is writable.

The first release provides filesystem-based lookup only. It does not yet include an administrator upload manager or an item-name catalogue. File names remain predictable and can be copied in bulk from a legally distributable icon pack.
