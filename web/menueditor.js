function __(key, values) {
    values = values || [];
    if(typeof menuEditorTranslations != 'undefined' && menuEditorTranslations[key]){
        var translation = menuEditorTranslations[key];
        if (values.length > 0) {
            values.forEach(function (val, index) {
                translation = translation.replace('%' + (index + 1) + '%', val);
            });
        }
        return translation;
    }
    return key;
}

function capitalizeFirstLetter(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

$().ready(function(){
    var ns = $('.sortable').nestedSortable({
        attribute: 'id',
        forcePlaceholderSize: true,
        handle: '.itemTitle',
        helper: 'clone',
        items: 'li',
        opacity: .6,
        placeholder: 'placeholder',
        revert: 250,
        tabSize: 25,
        tolerance: 'pointer',
        toleranceElement: '> div',
        maxLevels: 4,
        isTree: true,
        expandOnHover: 700,
        startCollapsed: true
    });

    function registerEvents() {
        $('.expandEditor, .itemTitle, .disclose, .deleteMenu').off('click')

        $('.disclose').on('click', function() {
            $(this).closest('li').toggleClass('mjs-nestedSortable-collapsed').toggleClass('mjs-nestedSortable-expanded');
            $(this).find('i').toggleClass('fa-minus').toggleClass('fa-plus');
        });

        $('.expandEditor, .itemTitle').on('click', function(){
            $(this).parent().siblings('.editor').toggle();
            $(this).parent().find('.expandEditor i').toggleClass('fa-chevron-down').toggleClass('fa-chevron-up');
        });

        $('.deleteMenu').on('click', function(){
            $(this).parents('.mjs-nestedSortable-expanded').first().remove();
        });

        $('.editor [type=text], .editor textarea').on('input', function(){
            var key = $(this).attr('name');
            $(this).parents('.mjs-nestedSortable-expanded').first().attr('data-'+key, $(this).val());
            if(key == 'label'){
                $(this).parents('.mjs-nestedSortable-expanded').first().find('.itemTitle').first().text($(this).val());
            }
        });

        $('.editor [type=checkbox]').on('change', function(){
            var key = $(this).attr('name');
            $(this).parents('.mjs-nestedSortable-expanded').first().attr('data-'+key, $(this).prop('checked'));
        });

        $('.editor select').on('change', function(){
            var key = $(this).attr('name');
            $(this).parents('.mjs-nestedSortable-expanded').first().attr('data-'+key, $(this).val());
        });
    }
    registerEvents();

    $('.saveform').submit(function(e){
        var menus = {}
        $('.menus .tab-pane').each(function(elem){
            var menu = $(this).find('ol.sortable').nestedSortable('toHierarchy', {startDepthCount: 0});
            menu = clean(menu);
            menus[$(this).attr('id')] = menu;
        });
        $('.saveform [name="menus"]').attr('value', JSON.stringify(menus))
    });

    /*
     * Clean data so we avoid circular structures in JSON.stringify
     */
    function clean(arr) {
        arr.forEach(function(item){
            delete item['nestedSortable-item'];
            delete item.nestedSortableItem
            delete item.id;
            if (item.children){
                item.submenu = item.children;
                delete item.children;
                clean(item.submenu);
            }
        });
        return arr;
    }

    /*
     * Templates for select2 (formatRepo and formatRepoSelection)
     */
    function formatRepo(record) {
        if (record.disabled){
            return "\
            <div class='select2-result-repository clearfix'> \
                <div class='select2-result-repository__title'> \
                    "+__('menueditor.js.loading')+" \
                </div> \
            </div>";
        }
        if (!record.image) {
            record.image = 'no-image.jpg';
        }
        var markup = "\
        <div class='select2-result-repository clearfix'> \
            <div class='select2-result-repository__avatar'> \
                <img src='/thumbs/60x60c/" + record.image + "' /> \
            </div> \
            <div class='select2-result-repository__meta'> \
                <div class='select2-result-repository__title'>" + record.title + "</div>\
                ";
                if (record.body) {
                    markup += "<div class='select2-result-repository__description'>" + record.body + "</div>";
                }
                markup += "<div class='select2-result-repository__statistics'>"
                if (record.newOption) {
                    markup += "<div class='select2-result-repository__forks'><i class='fa fa-plus-circle'></i> "+__('menueditor.js.newlink')+" '" + record.link + "'</div>"
                } else {
                    markup += "<div class='select2-result-repository__forks'><i class='fa " + record.icon + "'></i> " + record.type + "</div>"
                }
                markup += "\
                </div>\
            </div>\
        </div>";

        return markup;
    }

    function formatRepoSelection(repo) {
      return repo.contenttype ? (repo.contenttype + '/' + repo.id) : repo.link;
    }

    /*
     * select2 with ajax seraching
     */
    $(".additem [name='link']").select2({
        ajax: {
            url: "menueditor/search",
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    q: params.term
                };
            },
            processResults: function(data, params) {
                return {
                    results: data
                };
            },
            cache: true
        },
        createTag: function(params) {
            return {
                link: params.term,
                id: params.term,
                title: params.term,
                newOption: true
            }
        },
        multiple: true,
        maximumSelectionLength: 1,
        tags: true,
        escapeMarkup: function(markup) { return markup; },
        minimumInputLength: 2,
        templateResult: formatRepo,
        templateSelection: formatRepoSelection
    }).on('select2:select', function(evt){
        $(".additem [name='link']").val(null).trigger("change");
        if(evt.params.data.contenttype){
            var path = evt.params.data.contenttype + '/' + evt.params.data.id;
            var label = $(".additem [name='label']").val() ? $(".additem [name='label']").val() : evt.params.data.title;
        }else if(evt.params.data.ctslug){
            var path = evt.params.data.ctslug;
            var label = $(".additem [name='label']").val() ? $(".additem [name='label']").val() : evt.params.data.title;
        }else{
            var link = evt.params.data.link;
            var label = $(".additem [name='label']").val() ? $(".additem [name='label']").val() : (evt.params.data.title ? evt.params.data.title : evt.params.data.link);
        }
        addToActiveMenu(label, link, path);
    });

    function addToActiveMenu(label, link, path){
        var markup = '\
        <li class="mjs-nestedSortable-expanded" id="menuitem-' + link + '" data-label="'+label+'" data-' + (link ? 'link' : 'path') + '="' + (link ? link : path) + '"> \
            <div> \
                <div class="flex-row"> \
                <span title="'+__("menueditor.action.showhidechildren")+'" class="no-grow disclose"><i class="fa fa-minus" aria-hidden="true"></i></span> \
                <span title="'+__("menueditor.action.showhideeditor")+'" class="no-grow expandEditor"><i class="fa fa-chevron-down" aria-hidden="true"></i></span> \
                    <span class="itemTitle">' + label + '</span> \
                    <span title="'+__("menueditor.action.delete")+'" class="no-grow deleteMenu"><i class="fa fa-trash-o" aria-hidden="true"></i></span> \
                </div> \
                <div class="form-horizontal editor"> \
                    <div class="form-group"> \
                        <label class="col-sm-2 control-label">'+__("menueditor.fields.label")+'</label> \
                        <div class="col-sm-10"> \
                            <input type="text" class="form-control" placeholder="label" name="label" value="' + label + '"> \
                        </div> \
                    </div> \
                    <div class="form-group"> \
                        <label class="col-sm-2 control-label">'+ (link ? __("menueditor.fields.link") : __("menueditor.fields.path")) +'</label> \
                        <div class="col-sm-10"> \
                            <input type="text" class="form-control" placeholder="'+ (link ? 'link' : 'path') +'" name="'+ (link ? 'link' : 'path') +'" value="'+ (link ? link : path) +'"> \
                        </div> \
                    </div> \
                </div> \
            </div> \
        </li>';
        $('.active ol.sortable').append(markup);
        registerEvents();
    }

    /**
     * Show a flash message in top of the page
     * @param {string} message The message to display
     * @param {string} type Type of message (success(default), info, warning, danger)
     * @param {boolean} close Show the close button (default: true)
     */
    function showFlash(message, type, close) {
        type = type || 'success';
        close = close === undefined ? true : false;

        var flash = '<div class="alert alert-%type%" role="alert">%message%%button%</div>',
            button = close ? '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>' : '';

        flash = flash
            .replace('%type%', type)
            .replace('%message%', message)
            .replace('%button%', button);

        $('.col-md-8').first().prepend(flash);
    }

    // Some modal stuuf
    $('#new-field').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget),
            fieldId = button.data('id'),
            modal = $(this);

        modal.find('#field-id').val(fieldId);
        $('#field-label').focus();
    });

    $('#add-new-field').on('click', function () {
        var label = $('#field-label').val(),
            value = $('#field-value').val(),
            id = $('#field-id').val(),
            lastField = $('#menuitem-' + id + ' .form-group').last(),
            template = lastField.clone();

        if (label !== '' && value !== '') {
            template.find('label').html(capitalizeFirstLetter(label));
            template.find('input[type=text]')
                .attr('name', label.toLowerCase())
                .val(value);

            $('#menuitem-' + id + ' .fields').append(template[0]);
            $('#menuitem-' + id).attr('data-' + label, value);

            //Close and reset the modal
            $('#field-label').val('');
            $('#field-value').val('');
            showFlash(__('menueditor.flash.addedfield', [label]));
        }

        $('#new-field').modal('hide');
        registerEvents();
    });

    $('.tab-content').on('click', '.remove-field', function (evt) {
        evt.preventDefault();
        var fieldId = $(this).data('id'),
            fieldKey = $(this).data('key');

        if (confirm(__('menueditor.confirm.removefield', [fieldKey]))) {
            $('#menuitem-' + fieldId)
                .removeAttr('data-' + fieldKey);
            $('#group-' + fieldId + '-' + fieldKey).remove();
            showFlash(__('menueditor.flash.removefield', [fieldKey]));
        }
    })
});
