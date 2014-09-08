define([
    'jquery',
    'bootbox',
    'nestable'
], function($, bootbox) {
    "use strict";

    /**
     * just some basics to get things running (event listeners and stuff)
     * @private
     */
    var _basics = function()
    {
        // removes messages after 5s
        setTimeout(function(){
            $('.alert').fadeOut();
        },5000);

        // menu navigation (tabs)
        $( "#menu-editor-extension" ).on('click', '.filter', function(e) {
            e.preventDefault();

            var allf = $('.tabgrouping');
            var customType = $( this ).data('filter');
            allf
                .hide()
                .filter(function () {
                    return $(this).data('tab') === customType;
                })
                .show();
            $( '#filtertabs li' ).removeClass( "active" );
            $( this ).parent().attr('class', 'active')
        });

        // restore specific backup
        $(".me-restoremenus").on("click", function(e) {
            e.preventDefault();
            _restoreBackup($(this).data('filetime'));
        });

        // discard / revert changes
        $('.me-revert-changes').click(function(e) {
            e.preventDefault();
            bootbox.confirm(_me.trans_revertChanges, function(result) {
                if (true === result) {
                    location.reload(true);
                }
            });
        })

        // toggle item panels
        $( "#menu-editor-extension" ).on( "click", ".dd-edit", function() {
            var el = $(this).parent().children('div.dd-editpanel');
            var show = false;
            if ($(el).hasClass('hidden')) {
                show = true;
            }

            $('div.dd-editpanel').addClass('hidden');

            if (show) {
                $(el).removeClass('hidden');
            } else {
                $(el).addClass('hidden');
            }
        });

        // saves the menu(s)
        $("#savemenus").click(function() {
            if (_me.me_writeLock == 0) {
                return false;
            }

            _saveMenus();
        });

        // updates menu-item data
        $( "#menu-editor-extension" ).on("click", "button.me-updateitem", function(e) {
            e.preventDefault();
            _updateItemData(this);
        });

        // removes item from menu
        $( "#menu-editor-extension" ).on("click", "button.me-deleteitem", function(e) {
            e.preventDefault();
            var item = $(this).parent().parent();

            if ($(item).find("ol:first").length >= 1) {

                bootbox.confirm(_me.trans_deleteWithSubmenus, function(result) {
                    if (true === result) {
                        _removeMenuItem(item)
                    }
                });
            } else {
                _removeMenuItem(item);
            }
        })

        // adds new menu
        $('button#me-addmenu').click(function() {
            _addNewMenu();
        });

        // prefill stuff when selecting contenttype
        $(".me-addct").on('change', function() {
            var slug = $($(this).select2("data"))[0].slug;
            $("#me-addct-label").val($(this).select2("data").text);
            $("#me-addct-path").val($($(this).select2("data"))[0].contenttype + "/" + slug)
        });

        // prefill stuff when selecting contenttype
        $(".me-addct-filter").on('change', function() {
            $(this).parent().find('.select2-choice').addClass('select2-default');
            $(this).parent().find('.select2-chosen').html(_me.trans_searchitem);
            $('#me-addct-label').val("");
            $('#me-addct-path').val("");
            $('#me-addct-class').val("");
            $('#me-addct-title').val("");
        });

        // prefill stuff when selecting special item
        $(".me-addsp").on('change', function() {
            $('#me-addsp-label').val($(this).select2("data").text);
            $('#me-addsp-path').val($(this).select2("val"));
        });


    }

    /**
     * sets up drag-and-droppable menu-lists
     * @private
     */
    var _nestable = function()
    {
        $('.me-menu').nestable({
            'maxDepth': 100,
            'threshold': 15
        });
    }

    /**
     *
     * @private
     */
    var _select2 = function()
    {
        // select2ify contenttype selector
        $("div.me-addct").select2({
            placeholder: _me.trans_searchitem,
            minimumInputLength: 3,
            ajax: {
                type: 'post',
                url: '',
                dataType: 'json',
                quietMillis: 100,
                data: function(term, page) {
                    return {
                        action: 'search-contenttypes',
                        ct: $('select.me-addct-filter').val(),
                        meq: term,
                        page_limit: 100
                    };
                },
                results: function(data, page) {
                    var records = Array();
                    var record;
                    for (record in data.records[0]) {
                        records.push({id:data.records[0][record].values.id, text:data.records[0][record].values.title, slug:data.records[0][record].values.slug, contenttype:data.records[0][record].contenttype.slug});
                    }

                    $('.select2-container.me-addct').removeClass('select2error');

                    return {results: records}
                }
            }
        });

        // select2ify special selector
        $('select.me-addsp').select2({
            placeholder: _me.trans_additem
        });

        // add an item to the menu
        $(".me-additem").click(function() {
            _addItemToMenu(this);
        });
    }

    /**
     * save the menus
     * @private
     */
    var _saveMenus = function()
    {
        var menus = {};

        $('.me-menu').each(function() {
            var menu = [];
            $(this).children('ol').children('li').each(function(){
                menu.push(_extractMenuItem(this));
            });

            var menuName = $(this).data('menuname');
            menus[menuName] = menu;
        });

        $.ajax({
            url: '',
            type: 'POST',
            data: {'menus': menus, 'writeLock': _me.writeLock},
            dataType:"json",
            error: function() {
                bootbox.alert(_me.trans_connectionError, function() {});
            },
            success: function (data) {
                if(typeof data.writeLock != 'undefined') {
                    _me.writeLock = data.writeLock;
                }

                // all went well, refresh page
                if (data.status == 0) {
                    location.reload(true);
                }

                // menu.yml was edited in the meantime
                if (data.status == 1) {
                    bootbox.alert(_me.trans_writeLockError, function() {});
                }

                // xhr-error or parse-error
                if (data.status == 2 || data.status == 4) {
                    bootbox.alert(_me.trans_parseError, function() {});
                }

                // unable to write menu.yml
                if (data.status == 3) {
                    bootbox.alert(_me.trans_writeError, function() {});
                }

                // backup failed
                if (data.status == 5) {
                    bootbox.alert(_me.trans_backupFailError, function() {});
                }
            }
        });
    }

    /**
     * helper function for _saveMenus
     * @param item
     * @returns {{}}
     * @private
     */
    var _extractMenuItem = function(item)
    {
        var extractedItem = {};
        var match;

        // extract data attributes
        $.each(item.attributes, function() {
            if (match = this.name.match(/^data-([a-z]+)/)) {
                if (this.value != '' || match[1] == 'label') {
                    extractedItem[match[1]] = this.value;
                }
            }
        });

        // loop subs
        var subs = $("> ol > li", item);
        if (subs.length > 0) {
            var itemsSub = [];

            subs.each(function() {
                itemsSub.push(_extractMenuItem(this));
            })
            extractedItem['submenu'] = itemsSub;
        }

        return extractedItem;
    }

    /**
     * Applies changes to menu-items
     */
    var _updateItemData = function(item)
    {
        var the = item;
        var item = $(the).parent('div').parent();

        $(the).parent('div').find('input').each(function() {
            var tag = $(this).data('tag');
            if (tag != undefined) {
                // replace parent data
                $(item).attr('data-' + tag, $(this).val());

                if (tag == 'label') {
                    // update label
                    var label = $(item).children(".dd3-content").first();
                    if ($(this).val() == '') {
                        $(label).html('<em>' + _me.trans_nolabelset +'</em>');
                    } else {
                        $(label).html($(this).val());
                    }
                }
            }
        });

        // close all editpanels
        $('div.dd-editpanel').addClass('hidden');
    }

    /**
     * removes an item from a menu
     * @param item
     */
    var _removeMenuItem = function(item) {
        var parentOl = $(item).parent('ol');
        parentOl = parentOl[0];
        $(item).remove();

        if ($(parentOl).find("li").length == 0 && !$(parentOl).hasClass('me-menulist')) {
            // last element
            $(parentOl).parent().find("> button").each(function() {
                this.remove();
            });

            parentOl.remove();
        }
    }

    /**
     * adds a new menu
     * @returns {boolean}
     * @private
     */
    var _addNewMenu = function()
    {
        var menuname = $('input#me-addmenu-name').val().replace(/[^a-z0-9-_]/gi, "");

        if (menuname == '') {
            return false;
        }

        var existingMenus = [];
        $('.me-menu').each(function() {
            existingMenus.push($(this).data('menuname'));
        })

        if ($.inArray( menuname, existingMenus ) > -1) {
            bootbox.alert(_me.trans_menualreadyexists, function() {});
            return false;
        } else {
            $(".nav-tabs li:last").before('<li><a class="filter" data-filter="me-tab-'+ existingMenus.length +'">' + menuname +'</a></li>');
            $(".tabgrouping[data-tab='_add-new-menu']").before('<div class="tabgrouping" data-tab="me-tab-' + existingMenus.length + '"><div class="dd me-menu" id="me-menu-'+ existingMenus.length +'" data-menuname="'+ menuname +'"><ol class="dd-list me-menulist"></ol></div></div>');

            $('input#me-addmenu-name').val('')

            // activate new menu
            $('.filter[data-filter="me-tab-' + existingMenus.length +'"').trigger('click');

            _nestable();
        }

    }

    /**
     * restores a previously saved backup
     * @param filetime
     * @private
     */
    var _restoreBackup = function(filetime)
    {
        bootbox.confirm(_me.trans_restorebackup, function(result)
        {
            if (true === result) {
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: {'filetime': filetime},
                    dataType:"json",
                    error: function(data) {
                        bootbox.alert(_me.trans_connectionError, function() {});
                    },
                    success: function (data) {
                        if (data.status == 0) {
                            // all good, refresh page
                            location.reload(true);
                        } else {
                            // bs :(
                            bootbox.alert(_me.trans_backupRestoreFailError + ' ' + data.error, function() {});
                        }
                    }
                });
            }
        });
    }

    var _addItemToMenu = function(the)
    {

        var type = the.id;

        // fetch attributes
        var attributes = {
            label: $('#' + type + '-label').val(),
            link: '',
            title: $('#' + type + '-title').val(),
            class: $('#' + type + '-class').val(),
            path: ''
        }

        // type-specfics
        switch (type) {
            case 'me-addct':
                if ($('#me-addct-path').val() == '') {
                    $('.select2-container.me-addct').addClass('select2error');
                    $('select.me-addct').on('change', function() {
                        $('.select2-container.me-addct').removeClass('select2error');
                        $('select.me-addct').off('change');
                    });
                    return false;
                }

                attributes.path = $('#' + type + '-path').val();
                break;

            case 'me-addsp':
                if ($('#me-addsp-path').val() == '') {
                    $('.select2-container.me-addsp').addClass('select2error');
                    $('select.me-addsp').on('change', function() {
                        $('.select2-container.me-addsp').removeClass('select2error');
                        $('select.me-addsp').off('change');
                    });
                    return false;
                }

                attributes.path = $('#' + type + '-path').val();
                break;

            case 'me-addurl':
                attributes.link = $('#' + type + '-link').val();
                break;

        }

        // fetch rendered item
        $.ajax({
            url: '',
            type: 'POST',
            data: {'newitem': attributes},
            dataType:"json",
            error: function(data) {
                bootbox.alert(_me.trans_connectionError, function() {});
            },
            success: function (data) {
                if (data.status == 0) {
                    // all good, add item to page
                    // append to menu
                    var activeTab = $(".nav-tabs li.active").find('a').data('filter').replace("me-tab-", "");

                    $('#me-menu-' + activeTab +' ol:first-child').append(data.html);
                    $('#me-menu-' + activeTab +' ol:first-child li:last').fadeIn(700);

                    // clean up
                    switch (type) {
                        case 'me-addct':
                            $(the).parent().find('.select2-choice').addClass('select2-default');
                            $(the).parent().find('.select2-chosen').html(_me.trans_searchitem);
                            $('select.me-addct').select2("val", "");
                            $('.me-addct-filter').val("");
                            $('#me-addct-label').val("");
                            $('#me-addct-path').val("");
                            $('#me-addct-class').val("");
                            $('#me-addct-title').val("");
                            break;

                        case 'me-addsp':
                            $('select.me-addsp').select2("val", 0);
                            $('#me-addsp-label').val("");
                            $('#me-addsp-path').val("");
                            $('#me-addsp-class').val("");
                            $('#me-addsp-title').val("");
                            break;

                        case 'me-addurl':
                            $('#me-addurl-label').val("");
                            $('#me-addurl-link').val("");
                            $('#me-addurl-class').val("");
                            $('#me-addurl-title').val("");
                            break;
                    }

                } else {
                    // bs :(
                    bootbox.alert(data.error, function() {});
                }
            }
        });
    }

    /**
     * public methods
     */
    return {
        init: function()
        {
            // fire it up
            _basics();
            _nestable();
            _select2();
        }
    }
})