({
    name: 'app',
    baseUrl: '.',
    mainConfigFile: 'app.js',
    out: 'app.min.js',
    paths: {
        bootbox:    'bower_components/bootbox/bootbox',
        nestable:   'bower_components/nestable/jquery.nestable',
        requireLib: 'bower_components/requirejs/require',
        menueditor: 'bolt-menu-editor/main'
    },
    include: ['requireLib', 'bootbox', 'nestable', 'menueditor']
})