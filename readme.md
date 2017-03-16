# Menu editor

Le missing visual menu editor for bolt CMS.
Please edit config options to suit your needs before using this extension.

## Fields

You can define fields in addition to the ones that already exist on menuitems.
These are key-value pairs of name and default value and will be added (with that default value) to all existing items that do not have that field yet.
You can not use the terms `id`, `nestedSortableItem` and `nestedSortable-item`.

*Example*

    fields:
        additional_field1: 'test'
        additional_field2: 'test'

## Adding fields

Within the editor it is by default possible to add custom fields. Sometimes you do not want the editor to add extra fields, because they will do nothinh in the frontend. To prevent the option for adding fields you can turn this of:

    addcustomfields: false 

## Fieldtypes

For easier editing and preventing making mistakes, it is possible to style fields. The fields can be styled per menu to make it more felxible. Currently there are 4 fieldtypes you can choose from:

* **text**: Adding a regular input text field. This is also the default. 
* **textarea**: Adding a textarea field. This can be used for e.g. tooltips
* **checkbox**: Adding a checkbox. This can be used for easily toggling features in the menu
* **select**: Adding a select field. Let the editor select from just a few options to prevent typos

The select needs an extra attribute: `values`. The value is a key: value paired object like: `values: {image: Image, something: Just something else}`

Default all fields except the label are deletable. This is not always a wanted feature. Thereby it can be turned of per field by setting a delete attribute to false.

*Example*

    menufields:
        main:
            type:
                type: select
                values: {image: Image, something: Just something else}
            image:
                type: text
                delete: false
            path:
                delete: false
            class:
                type: checkbox
            tooltip: 
                type: textarea


## Permission

You can set the permission for the menu editor in the configuration. The value to set can be choosen from the permission levels available on `[your-site]/bolt/roles` or you can create your own at `[your-site]/bolt/file/edit/config/permissions.yml`

For creating your own permission schema it is preferable to prefix it like: `ext:menueditor`

## Support
If you run into issues or need a new feature, please open a ticket over at [https://github.com/bacbos/bolt-menu-editor/issues](https://github.com/bacbos/bolt-menu-editor/issues)
... or fix it yourself, pull-requests very welcome =)

When adding new features, keep the next things in mind:
* Make it possible to toggle it on and off in the config (with a sensible default), as not all users - especially ordinary editors - might need to access it.
* All strings shown to users need to be translatable, with the english version also set as default.
