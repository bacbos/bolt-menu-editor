# Menu editor

Le missing visual menu editor for bolt CMS.
Please edit config options to suit your needs before using this extension.

## Permission

You can set the permission for the menu editor in the configuration. The value to set can be choosen from the permission levels available on `[your-site]/bolt/roles` or you can create your own at `[your-site]/bolt/file/edit/config/permissions.yml`

For creating your own permission schema it is preferable to prefix it like: `ext:menueditor`

## Support
If you run into issues or need a new feature, please open a ticket over at [https://github.com/bacbos/bolt-menu-editor/issues](https://github.com/bacbos/bolt-menu-editor/issues)
... or fix it yourself, pull-requests very welcome =)

When adding new features, please consider making it possible to toggle it on and off in the config (with a sensible default), as not all users - especially ordinary editors - might need to access it.