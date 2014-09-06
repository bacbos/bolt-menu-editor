define('jquery', [], function() {
    return jQuery;
});

requirejs.config({
    enforceDefine: false,
    paths:
    {
        bootbox:    'bower_components/bootbox/bootbox',
        nestable:   'bower_components/nestable/jquery.nestable',
        require:    'bower_components/requirejs/require',
        menueditor: 'bolt-menu-editor/main'
    },
    shim:
    {
        nestable: {}
    }
});

require(['menueditor'], function(me) {
    me.init();
});