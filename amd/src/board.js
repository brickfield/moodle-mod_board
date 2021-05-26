define(['jquery', 'jqueryui', 'core/str', 'core/ajax', 'core/notification', 'core/templates',
        'mod_board/jquery.editable.amd'], function($, jqui, Str, Ajax, Notification) {

    var _serviceCall = function(method, args, callback, failcallback) {
        Ajax.call([{
            methodname: 'mod_board_' + method,
            args: args,
            done: function(data) {
                callback(data);
            }.bind(this),
            fail: function(error) {
                Notification.exception(error);
                if (failcallback) {
                    failcallback(error);
                }
            }
        }]);
    };

    var isAriaTriggerKey = function(key) {
        return key==13 || key==32;
    };

    var encodeText = function(rawText) {
        return $('<div />').text(rawText).html();
    };

    var decodeText = function(encodedText) {
        return $('<div />').html(encodedText).text();
    };

    var handleAction = function(elem, callback) {
        return elem.on('click keypress', function(e) {
            if (e.type=='keypress') {
                if (isAriaTriggerKey(e.keyCode)) {
                    e.preventDefault();
                } else {
                    return;
                }
            }

            callback();
        });
    };

    var handleEditableAction = function(elem, callback, callBeforeOnKeyEditing) {
        if (elem.is(':editable')) {
            throw new Error('handleEditableAction - must be called before setting the element as editable');
        }

        // can't use on(edit) here because we want to do actions (save cache) before the control goes into edit mode
        return elem.on('dblclick keypress', function(e) {
            if (e.type=='keypress') {
                if (isAriaTriggerKey(e.keyCode) && !elem.is(':editing')) {
                    e.preventDefault();
                    if (callBeforeOnKeyEditing) {
                        callback();
                    }
                    elem.editable('open');
                    if (callBeforeOnKeyEditing) {
                        return;
                    }
                } else {
                    return;
                }
            }

            callback();
        });
    };

    return function(board, options) {
        var strings = {
            default_column_heading: '',
            post_button_text: '',
            cancel_button_text: '',
            remove_note_text: '',
            remove_column_text: '',
            note_changed_text: '',
            note_deleted_text: '',
            rate_note_text: '',
            Ok: '',
            Cancel: '',
            warning: '',
            option_youtube: '',
            option_youtube_info: '',
            option_youtube_url: '',
            option_image: '',
            option_image_info: '',
            option_image_url: '',
            option_link: '',
            option_link_info: '',
            option_link_url: '',
            option_empty: '',

            aria_newcolumn: '',
            aria_newpost: '',
            aria_deletecolumn: '',
            aria_deletepost: '',
            aria_addmedia: '',
            aria_addmedianew: '',
            aria_deleteattachment: '',
            aria_postedit: '',
            aria_canceledit: '',
            aria_postnew: '',
            aria_cancelnew: '',
            aria_choosefilenew: '',
            aria_choosefileedit: '',
            aria_ratepost: '',

            choose_file: '',
            invalid_file_extension: '',
            invalid_file_size_min: '',
            invalid_file_size_max: '',
        };

        const MEDIA_SELECTION_BUTTONS = 1;
        const MEDIA_SELECTION_DROPDOWN = 2;
        const ATTACHMENT_VIDEO = 1;
        const ATTACHMENT_IMAGE = 2;
        const ATTACHMENT_LINK = 3;
        const SORTBY_DATE = 1;
        const SORTBY_RATING = 2;
        var reloadTimer = null;
        var lastHistoryId = null;
        var isEditor = options.isEditor || false;
        var userId = options.userId || -1;
        var mediaSelection = options.mediaselection || MEDIA_SELECTION_BUTTONS;
        var noteTextCache = null, noteHeadingCache = null, attachmentCache = null;
        var editingNote = 0;
        var isReadOnlyBoard = options.readonly || false;
        var accepted_file_extensions = options.file.extensions;
        var accepted_file_size_min = options.file.size_min;
        var accepted_file_size_max = options.file.size_max;
        var ratingenabled = options.ratingenabled;
        var sortby = options.sortby || SORTBY_DATE;

        var serviceCall = function(method, args, callback, failcallback) {
            if (method!=='board_history') {
                stopUpdating();
            }
            _serviceCall(method, args, function() {
                callback.apply(null, arguments);
                if (method!=='board_history' && method!='get_board') {
                    updateBoard(true);
                }
            }, failcallback);
        };

        var getNote = function(ident) {
            return $(".board_note[data-ident='"+ident+"']");
        };

        var getNoteText = function(ident) {
            return $(".board_note[data-ident='"+ident+"'] .note_text");
        };

        var getNoteTextForNote = function(note) {
            return $(note).find(".note_text");
        };

        var getNoteHeading = function(ident) {
            return $(".board_note[data-ident='"+ident+"'] .note_heading");
        };

        var getNoteHeadingForNote = function(note) {
            return $(note).find(".note_heading");
        };

        var getNoteButtonsForNote = function(note) {
            return $(note).find(".note_buttons");
        };

        var showNewNoteButtons = function() {
            $('.board_column .board_column_newcontent .board_button.newnote').show();
        };

        var hideNewNoteButtons = function() {
            $('.board_column .board_column_newcontent .board_button.newnote').hide();
        };

        var getNoteAttachmentsForNote = function(note) {
            return $(note).find(".note_attachment");
        };

        var textIdentifierForNote = function(note) {
            var noteText = getNoteTextForNote(note).html();
            var noteHeading = getNoteHeadingForNote(note).html();
            var noteAttachment = attachmentDataForNote(note);

            if (noteHeading.length>0) {
                return noteHeading;
            }
            if (noteText.length>0) {
                return noteText.replace(/<br\s*\/?>/gi," ").replace(/\n/g, " ").split(/\s+/).slice(0,5).join(" ");
            }
            if (noteAttachment.info && noteAttachment.info.length>0) {
                return noteAttachment.info;
            }
            return null;
        };

        var updateNoteAria = function(noteId) {
            var note = getNote(noteId);
            var columnIdentifier = note.closest('.board_column').find('.column_name').text();
            var post_button = "";
            var cancel_button = "";
            var add_youtube = "";
            var add_image = "";
            var add_link = "";
            var remove_attachment = "";
            var choose_file_button = "";

            if (!noteId) { // new post
                post_button = strings.aria_postnew.replace('{column}', columnIdentifier);
                cancel_button = strings.aria_cancelnew.replace('{column}', columnIdentifier);
                add_youtube = strings.aria_addmedianew.replace('{type}', strings.option_youtube).replace('{column}',
                              columnIdentifier);
                add_image = strings.aria_addmedianew.replace('{type}', strings.option_image).replace('{column}', columnIdentifier);
                add_link = strings.aria_addmedianew.replace('{type}', strings.option_link).replace('{column}', columnIdentifier);
                choose_file_button = strings.aria_choosefilenew.replace('{column}', strings.columnIdentifier);
            } else {
                var noteIdentifier = textIdentifierForNote(note);

                post_button = strings.aria_postedit.replace('{column}', columnIdentifier).replace('{post}', noteIdentifier);
                cancel_button = strings.aria_canceledit.replace('{column}', columnIdentifier).replace('{post}', noteIdentifier);
                add_youtube = strings.aria_addmedia.replace('{type}', strings.option_youtube).replace('{column}',
                              columnIdentifier).replace('{post}', noteIdentifier);
                add_image = strings.aria_addmedia.replace('{type}', strings.option_image).replace('{column}',
                              columnIdentifier).replace('{post}', noteIdentifier);
                add_link = strings.aria_addmedia.replace('{type}', strings.option_link).replace('{column}',
                              columnIdentifier).replace('{post}', noteIdentifier);
                remove_attachment = strings.aria_deleteattachment.replace('{column}',
                columnIdentifier).replace('{post}', noteIdentifier);
                choose_file_button = strings.aria_choosefileedit.replace('{column}',
                columnIdentifier).replace('{post}', noteIdentifier);

                note.find('.delete_note').attr('aria-label', strings.aria_deletepost.replace('{column}',
                    columnIdentifier).replace('{post}', noteIdentifier));
                note.find('.rating').attr('aria-label', strings.aria_ratepost.replace('{column}',
                    columnIdentifier).replace('{post}', noteIdentifier));
                note.find('.note_ariatext').html(noteIdentifier);
            }

            if (mediaSelection==MEDIA_SELECTION_BUTTONS) {
                if (noteId) {
                    var attRemove = note.find('.remove_attachment');
                    if (attRemove) {
                        attRemove.attr('aria-label', remove_attachment);
                    }
                }

                note.find('.attachment_button.youtube_button').attr('aria-label', add_youtube);
                note.find('.attachment_button.image_button').attr('aria-label', add_image);
                note.find('.attachment_button.link_button').attr('aria-label', add_link);
            }
            note.find('.post_button').attr('aria-label', post_button);
            note.find('.cancel_button').attr('aria-label', cancel_button);
            note.find('.choose_file_button').attr('aria-label', choose_file_button);
        };

        var updateColumnAria = function(columnId) {
            var column = $('.board_column[data-ident='+columnId+']');
            var columnIdentifier = column.find('.column_name').text();
            column.find('.newnote').attr('aria-label', strings.aria_newpost.replace('{column}', columnIdentifier));
            column.find('.delete_column').attr('aria-label', strings.aria_deletecolumn.replace('{column}', columnIdentifier));

            column.find(".board_note").each(function(index, note) {
                updateNoteAria($(note).data('ident'));
            });
        };

        var successNoteEdit = function() {
            noteTextCache = null;
            noteHeadingCache = null;
            attachmentCache = null;
            stopNoteEdit();
        };

        var stopNoteEdit = function() {
            if (!editingNote) {
                getNote(0).remove();
                showNewNoteButtons();
                return;
            }

            if (noteTextCache) {
                getNoteText(editingNote).html(noteTextCache);
                noteTextCache = null;
            }

            if (noteHeadingCache) {
                getNoteHeading(editingNote).html(noteHeadingCache);
                noteHeadingCache = null;
            }

            var note = getNote(editingNote);

            if (note) {
                if (attachmentCache) {
                    setAttachment(note, attachmentCache);
                    attachmentCache = null;
                } else {
                    previewAttachment(note);
                }

                getNoteButtonsForNote(note).hide();
                getNoteAttachmentsForNote(note).hide();
                showNewNoteButtons();
                var noteHeading = getNoteHeadingForNote(note);
                if (!noteHeading.html()) {
                    noteHeading.hide();
                }
            }

            editingNote = 0;
        };

        var startNoteEdit = function(ident) {
            if (editingNote) {
                if (editingNote==ident) {
                    return;
                }
                stopNoteEdit();
            }

            if (ident) {
                var pendingNote = getNote(0);
                if (pendingNote) {
                    pendingNote.remove();
                }
            }

            var note = getNote(ident);
            if (note) {
                getNoteButtonsForNote(note).show();
                getNoteAttachmentsForNote(note).show();
                hideNewNoteButtons();

                var noteHeading = getNoteHeadingForNote(note);
                if (ident) {
                    attachmentCache = attachmentDataForNote(note);
                    noteTextCache = getNoteTextForNote(note).html();
                    noteHeadingCache = noteHeading.html();
                    editingNote = ident;
                }
                noteHeading.show();
            }
        };

        var deleteNote = function(ident) {
            if (confirm(strings.remove_note_text)) {
                serviceCall('delete_note', {id: ident}, function(result) {
                    if (result.status) {
                        lastHistoryId = result.historyid;
                        getNote(ident).remove();
                    }
                });
            }
        };

        var rateNote = function(ident) {
            if (!ratingenabled) {
                return;
            }
            if (isReadOnlyBoard) {
                return;
            }

            var note = getNote(ident);
            var rating = note.find('.rating');
            if (rating.data('disabled')) {
                return;
            }
            rating.data('disabled', true);

            serviceCall('can_rate_note', {id: ident}, function(canrate) {
                if (canrate) {
                    if (confirm(strings.rate_note_text)) {
                        serviceCall('rate_note', {id: ident}, function(result) {
                            if (result.status) {
                                lastHistoryId = result.historyid;
                                rating.html(result.rating);
                                if (sortby == SORTBY_RATING) {
                                    sortNotes(note.closest('.board_column_content'));
                                }
                            }
                            rating.data('disabled', false);
                        });
                    }
                }
            });
        };

        var attachmentTypeChanged = function(note) {
            var noteAttachment = getNoteAttachmentsForNote(note);
            var type = noteAttachment.find('.type').val();

            var attachmentInfo = noteAttachment.find('.info');
            var attachmentUrl = noteAttachment.find('.url');
            var attachmentFile = noteAttachment.find('.file');

            if (mediaSelection==MEDIA_SELECTION_BUTTONS) {
                var attachmentIcon = noteAttachment.find('.type_icon');
                var removeAttachment = noteAttachment.find('.remove_attachment');
            } else {
                getNoteButtonsForNote(note).find('.attachment_button').hide();
            }

            if (type>"0") {
                if (mediaSelection==MEDIA_SELECTION_BUTTONS) {
                    removeAttachment.show();
                }
                attachmentInfo.prop('placeholder', strings['option_'+attachmentTypeToString(type)+'_info']);
                attachmentUrl.prop('placeholder', strings['option_'+attachmentTypeToString(type)+'_url']);

                attachmentInfo.show();
                if (type==ATTACHMENT_IMAGE && FileReader) {
                    attachmentFile.show();
                    attachmentUrl.hide();
                } else {
                    attachmentFile.hide();
                    attachmentUrl.show();
                }

                if (mediaSelection==MEDIA_SELECTION_BUTTONS) {
                    attachmentIcon.removeClass().addClass(['type_icon', 'fa', attachmentFAIcon(type)]);
                    attachmentIcon.show();
                    getNoteButtonsForNote(note).find('.attachment_button').hide();
                }
            } else {
                if (mediaSelection==MEDIA_SELECTION_BUTTONS) {
                    removeAttachment.hide();
                }
                attachmentInfo.hide();
                attachmentUrl.hide();
                attachmentFile.hide();
                if (mediaSelection==MEDIA_SELECTION_BUTTONS) {
                    attachmentIcon.hide();
                }
                attachmentInfo.val('');
                attachmentUrl.val('');
                if (mediaSelection==MEDIA_SELECTION_BUTTONS) {
                    getNoteButtonsForNote(note).find('.attachment_button').show();
                }
            }
        };

        var setAttachment = function(note, attachment) {
            var noteAttachment = getNoteAttachmentsForNote(note);
            if (noteAttachment) {
                if (!attachment) {
                    attachment = {type: "0"};
                } else {
                    attachment.type += "";//just in case
                }
                var attType = noteAttachment.find('.type');
                attType.val(attachment.type?attachment.type: "0");
                if (attType.val()>"0") {
                    noteAttachment.find('.info').val(decodeText(attachment.info));
                    noteAttachment.find('.url').val(decodeText(attachment.url));
                }
                attachmentTypeChanged(note, attachment);
            }
            previewAttachment(note, attachment);
        };

        var attachmentDataForNote = function(note) {
            var attachment = { type: 0, info: null, url: null, filename: null, filecontents: null };
            var noteAttachment = getNoteAttachmentsForNote(note);
            if (noteAttachment.length) {
                attachment.type = noteAttachment.find('.type').val();
                attachment.info = encodeText(noteAttachment.find('.info').val());
                attachment.url = encodeText(noteAttachment.find('.url').val());
                var fileElem = noteAttachment.find('.file>input');
                if (fileElem.data('filename')) {
                    attachment.filename = fileElem.data('filename');
                    attachment.filecontents = fileElem.data('filecontents');
                }
            }
            if ((!attachment.info || !attachment.info.length) && (!attachment.url || !attachment.url.length) &&
                (!attachment.filename)) {
                attachment.type = 0;
            }

            return attachment;
        };

        var attachmentTypeToString = function(type) {
            switch(type) {
                case "1": return 'youtube';
                case "2": return 'image';
                case "3": return 'link';
                default: return null;
            }
        };

        var attachmentFAIcons = ['fa-youtube', 'fa-picture-o', 'fa-link'];
        var attachmentFAIcon = function(type) {
            return attachmentFAIcons[type-1] || null;
        };

        var preloadFile = function(note, callback) {
            var noteAttachment = getNoteAttachmentsForNote(note);
            if (noteAttachment.length) {
                var fileElem = noteAttachment.find('.file>input');
                if (FileReader && fileElem.prop('files').length) {
                    var file = fileElem.prop('files')[0];
                    if (accepted_file_extensions.indexOf(file.name.split('.').pop().toLowerCase())==-1) { // wrong exception
                        Notification.alert(strings.warning, strings.invalid_file_extension);
                    } else if (file.size < accepted_file_size_min) {
                        Notification.alert(strings.warning, strings.invalid_file_size_min);
                    } else if (file.size > accepted_file_size_max) {
                        Notification.alert(strings.warning, strings.invalid_file_size_max);
                    } else {
                        fileElem.data('filename', file.name);
                        var fr = new FileReader();
                        fr.onload = function() {
                            fileElem.data('filecontents', fr.result);
                            callback();
                            fileElem.val('');
                        };
                        fr.readAsDataURL(file);
                    }
                } else {
                    callback();
                }
            } else {
                callback();
            }
        };

        var previewAttachment = function(note, attachment) {
            var elem = note.find('.preview');
            if (!attachment) {
                attachment = attachmentDataForNote(note);
            }
            var fixEmbedUrlIfNeeded = function(url) {
                return url.replace(/watch\?v=/gi, '/embed/').replace(/youtu\.be/, 'youtube.com/embed');
            };

            if (!getNoteTextForNote(note).html().length) {
                elem.addClass('notext');
            } else {
                elem.removeClass('notext');
            }

            elem.removeClass('wrapper_youtube');
            elem.removeClass('wrapper_image');
            elem.removeClass('wrapper_url');
            if (attachment.filename && parseInt(attachment.type)==ATTACHMENT_IMAGE) { //before uploading
                elem.html('<img src="'+ attachment.filecontents +'" class="preview_element" alt="'+ attachment.info +'"/>');
                elem.addClass('wrapper_image');
                elem.show();
            }
            else if (attachment.url) {
                switch(parseInt(attachment.type)) {
                    case ATTACHMENT_VIDEO: //youtube
                        elem.html('<iframe src="'+ fixEmbedUrlIfNeeded(attachment.url) +
                        '" class="preview_element" frameborder="0" allow="accelerometer; autoplay; clipboard-write;' +
                        'encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>');
                        elem.addClass('wrapper_youtube');
                        elem.show();
                    break;
                    case ATTACHMENT_IMAGE: // image
                        elem.html('<img src="'+ attachment.url +'" class="preview_element" alt="'+ attachment.info +'"/>');
                        elem.addClass('wrapper_image');
                        elem.show();
                    break;
                    case ATTACHMENT_LINK: // url
                        elem.html('<a href="'+ attachment.url +'" class="preview_element" target="_blank">' +
                                 (attachment.info || attachment.url) + '</a>');
                        elem.addClass('wrapper_url');
                        elem.show();
                    break;
                    default:
                        elem.html('');
                        elem.hide();
                }
            } else {
                elem.html('');
                elem.hide();
            }
        };

        var addNote = function(columnid, ident, heading, content, attachment, owner, sortorder, rating) {
            var ismynote = owner.id==userId || !ident;
            var iseditable = isEditor || (ismynote && !isReadOnlyBoard);

            if (!ident) {
                var pendingNote = getNote(0);
                if (pendingNote) {
                    pendingNote.remove();
                }
            }

            var note = $('<div class="board_note" data-ident="'+ident+'" data-sortorder="'+sortorder+'"></div>');
            if (ismynote) {
                note.addClass('mynote');
            }
            if (iseditable) {
                note.addClass('editablenote');
            }

            var notecontent = $('<div class="note_content"></div>');
            var noteHeading = $('<div class="note_heading" tabindex="0">'+(heading?heading:'')+'</div>');
            var noteText = $('<div class="note_text" tabindex="0">'+(content?content:'')+'</div>');
            var noteAriaText = $('<div class="note_ariatext hidden" role="heading" aria-level="4" tabindex="0"></div>');
            var attachmentPreview = $('<div class="preview"></div>');
            if (iseditable) {
                var noteAttachment = $('<div class="note_attachment form-group row" tabindex="0">' +
                                    '<select class="type form-control form-control-sm '+
                                    (mediaSelection==MEDIA_SELECTION_BUTTONS?'hidden':'')+'">' +
                                        '<option value="0">'+strings.option_empty+'</option>' +
                                        '<option value="'+ATTACHMENT_VIDEO+'">'+strings.option_youtube+'</option>' +
                                        '<option value="'+ATTACHMENT_IMAGE+'">'+strings.option_image+'</option>' +
                                        '<option value="'+ATTACHMENT_LINK+'">'+strings.option_link+'</option>' +
                                    '</select>' +
                                    '<span class="type_icon fa '+(mediaSelection==MEDIA_SELECTION_DROPDOWN?'hidden':'')+'"></span>'+
                                    '<input type="text" class="info form-control form-control-sm col-sm-12 '+
                                    (mediaSelection==MEDIA_SELECTION_BUTTONS?'with_type_icon':'')+'" placeholder="">' +
                                    '<input type="text" class="url form-control form-control-sm col-sm-12" placeholder="">' +
                                    '<div class="file form-control form-control-sm"><label for="file'+ident+
                                    '" class="choose_file_button action_button p-0 w-100" tabindex="0">'+
                                    strings.choose_file+'</label><input id="file'+ident+'" type="file" class="d-none"></div>' +
                                '</div>'
                            );

                noteAttachment.hide();
            }

            notecontent.append(noteHeading);
            notecontent.append(noteText);
            notecontent.append(noteAriaText);
            if (iseditable) {
                notecontent.append(noteAttachment);
            }

            notecontent.append(attachmentPreview);
            note.append(notecontent);

            if (iseditable) {
                var attachmentType = noteAttachment.find('.type');
                var attachmentInfo = noteAttachment.find('.info');
                var attachmentUrl = noteAttachment.find('.url');
                var attachmentFileInput = noteAttachment.find('.file>input');

                attachmentType.on('change', function() {
                    attachmentTypeChanged(note);
                    previewAttachment(note);
                });

                attachmentUrl.on('change', function() {
                    previewAttachment(note);
                });

                attachmentFileInput.on('change', function() {
                    preloadFile(note, function() {
                        previewAttachment(note);
                    });
                });

                attachmentInfo.on('change', function() {
                    previewAttachment(note);
                });

                if (mediaSelection==MEDIA_SELECTION_BUTTONS) {
                    var removeAttachment = $('<div class="remove remove_attachment fa fa-remove"></div>');
                    removeAttachment.hide();
                    removeAttachment.on('click', function() { attachmentType.val(0); attachmentType.trigger('change'); });
                    noteAttachment.append(removeAttachment);
                }
            }

            var column_content = $('.board_column[data-ident='+columnid+'] .board_column_content');

            if (iseditable) {
                var buttons = $('<div class="note_buttons"></div>');
                buttons.hide();
                var postbutton = $('<div class="post_button action_button" role="button" tabindex="0">'+
                                  strings.post_button_text+'</div>');
                var cancelbutton = $('<div class="cancel_button action_button" role="button" tabindex="0">'+
                                    strings.cancel_button_text+'</div>');

                buttons.append(postbutton);
                buttons.append(cancelbutton);

                if (mediaSelection==MEDIA_SELECTION_BUTTONS) {
                    buttons.append('<div class="spacer_button"></div>');
                    var ytButton = $('<div class="attachment_button youtube_button action_button fa '+
                                    attachmentFAIcons[0]+'" role="button" tabindex="0"></div>');
                    handleAction(ytButton, function() { attachmentType.val(1); attachmentType.trigger("change"); });
                    var imgButton = $('<div class="attachment_button image_button action_button fa '+
                                     attachmentFAIcons[1]+'" role="button" tabindex="0"></div>');
                    handleAction(imgButton, function() { attachmentType.val(2); attachmentType.trigger("change"); });
                    var linkButton = $('<div class="attachment_button link_button action_button fa '+
                                      attachmentFAIcons[2]+'" role="button" tabindex="0"></div>');
                    handleAction(linkButton, function() { attachmentType.val(3); attachmentType.trigger("change"); });
                    buttons.append(ytButton);
                    buttons.append(imgButton);
                    buttons.append(linkButton);
                }

                note.append(buttons);

                var removeElement = $('<div class="remove fa fa-remove delete_note" role="button" tabindex="0"></div>');
                handleAction(removeElement, function() {
                    deleteNote(ident);
                });

                if (!ident) {
                    removeElement.hide();
                }
                notecontent.append(removeElement);

                handleAction(cancelbutton, function() {
                    stopNoteEdit();
                });

                noteText.on('click', function() {
                    if ((editingNote && editingNote==ident) || !ident) {
                        noteText.editable('open');
                    }
                });

                var beginEdit = function() {
                    startNoteEdit(ident);
                };

                noteHeading.on('click', function() {
                    if ((editingNote && editingNote==ident) || !ident) {
                        noteHeading.editable('open');
                    }
                });

                attachmentPreview.on('dblclick', beginEdit);

                handleAction(postbutton, function() {
                    var sendAttach = attachmentDataForNote(note);

                    noteHeading.editable('close');

                    var theHeading = noteHeading.html();
                    noteText.editable('close');
                    var theText = noteText.html().substring(0, options.post_max_length);

                    if (!theHeading && !theText && !sendAttach.url && !sendAttach.filename) {
                        return;
                    }

                    if (postbutton.data('disabled')) {
                        return;
                    }

                    postbutton.data('disabled', true);


                    if (!ident) { // new
                        serviceCall('add_note', {columnid: columnid, heading: theHeading, content: theText,
                            attachment: sendAttach}, function(result) {
                            if (result.status) {
                                lastHistoryId = result.historyid;
                                note.remove();
                                showNewNoteButtons();
                                addNote(columnid, result.note.id, result.note.heading, result.note.content,
                                    {type: result.note.type, info: result.note.info, url: result.note.url},
                                    {id: result.note.userid}, result.note.timecreated, result.note.rating);
                                sortNotes(column_content);
                                updateNoteAria(result.note.id);
                            } else {
                                postbutton.data('disabled', false);
                            }
                        });

                    } else { // update
                        serviceCall('update_note', {id: ident, heading: theHeading, content: theText,
                            attachment: sendAttach}, function(result) {
                            if (result.status) {
                                lastHistoryId = result.historyid;
                                successNoteEdit();
                                noteText.html(result.note.content);
                                updateNoteAria(ident);
                                setAttachment(note, {type: result.note.type, info: result.note.info, url: result.note.url});
                            }
                            postbutton.data('disabled', false);
                        });
                    }
                });

                handleEditableAction(noteText, beginEdit);
                noteText.editable({
                    toggleFontSize : false,
                    closeOnEnter: false,
                    callback : function() {
                        noteText.html(noteText.html().substring(0, options.post_max_length));
                    }
                });

                handleEditableAction(noteHeading, beginEdit);
                noteHeading.editable({
                    toggleFontSize : false,
                    closeOnEnter: true
                });

                setAttachment(note, attachment);
            } else {
                previewAttachment(note, attachment);
            }

            if (ident) {
                if (ratingenabled) {
                    note.addClass('rateablenote');
                    var rateElement = $('<div class="fa fa-star rating" role="button" tabindex="0">'+rating+'</div>');

                    handleAction(rateElement, function() {
                        rateNote(ident);
                    });
                    notecontent.append(rateElement);
                }

                if (!noteHeading.html()) {
                    noteHeading.hide();
                }
                var lastOne = column_content.find(".board_note").last();
                if (lastOne.length) {
                    note.insertAfter(lastOne);
                } else {
                    column_content.prepend(note);
                }
            } else {
                $('.board_column[data-ident='+columnid+'] .board_column_newcontent').append(note);
                updateNoteAria(ident);
                noteText.editable('open'); // trigger edit of note
                beginEdit();
            }
        };

        var addColumn = function(ident, name, notes) {
            var iseditable = isEditor;
            var nameCache = null;
            var column = $('<div class="board_column board_column_hasdata" data-ident="'+ident+'"></div>');
            var column_header = $('<div class="board_column_header"></div>');
            var column_sort = $('<div class="column_sort fa"></div>');
            var column_name = $('<div class="column_name" tabindex="0" aria-level="3" role="heading">'+name+'</div>');
            var column_content = $('<div class="board_column_content"></div>');
            var column_newcontent = $('<div class="board_column_newcontent"></div>');
            column_header.append(column_sort);
            column_header.append(column_name);

            if (options.hideheaders) {
                column_name.addClass('d-none');
            }

            column_sort.on('click', function() {
                sortNotes(column_content, true);
            });

            if (iseditable) {
                column.addClass('editablecolumn');

                var removeElement = $('<div class="remove fa fa-remove delete_column" role="button" tabindex="0"></div>');
                handleAction(removeElement, function() {
                    if (confirm(strings.remove_column_text)) {
                        serviceCall('delete_column', {id: ident}, function(result) {
                            if (result.status) {
                                column.remove();
                                lastHistoryId = result.historyid;
                            }
                        });
                    }
                });

                column_header.append(removeElement);
            }

            column.append(column_header);
            column.append(column_content);
            column.append(column_newcontent);

            if (iseditable) {
                handleEditableAction(column_name, function() {
                    nameCache = column_name.html();
                }, true);

                column_name.editable({
                    toggleFontSize : false,
                    closeOnEnter: true,
                    callback : function( data ) {
                        if ( data.content ) {
                            serviceCall('update_column', {id: ident, name: column_name.html()}, function(result) {
                                if (!result.status) {
                                    column_name.html(nameCache);
                                    nameCache = null;
                                } else {
                                    lastHistoryId = result.historyid;
                                    updateColumnAria(ident);
                                }
                            }, function() {
                                column_name.html(nameCache);
                                nameCache = null;
                            });
                        } else {
                            column_name.html(nameCache);
                            nameCache = null;
                        }
                    }
                });
            }

            if (!isReadOnlyBoard) {
                column_newcontent.append('<div class="board_button newnote" role="button" tabindex="0">' +
                '<div class="button_content"><span class="fa '+options.noteicon+'"></span></div></div>');

                handleAction(column_newcontent.find('.newnote'), function() {
                    addNote(ident, 0, null, null, null, {id: userId}, 0, 0);
                });
            }

            var lastOne = $(".mod_board .board_column_hasdata").last();
            if (lastOne.length) {
                column.insertAfter(lastOne);
            } else {
                $(".mod_board").append(column);
            }

            if (notes) {
                for (var index in notes) {
                    addNote(ident, notes[index].id, notes[index].heading, notes[index].content,
                        {type: notes[index].type, info: notes[index].info, url: notes[index].url},
                        {id: notes[index].userid}, notes[index].timecreated, notes[index].rating);
                }
            }
            sortNotes(column_content);
            updateColumnAria(ident);
            isEditor && updateSortable();
        };

        var addNewColumnButton = function() {
            var column = $('<div class="board_column board_column_empty"></div>');
            var newBusy = false;
            column.append('<div class="board_button newcolumn" role="button" tabindex="0" aria-label="'+
            strings.aria_newcolumn+'"><div class="button_content"><span class="fa '+options.columnicon+
            '"></span></div></div>');

            handleAction(column.find('.newcolumn'), function() {
                if (newBusy) {
                    return;
                }
                newBusy = true;

                serviceCall('add_column', {boardid: board.id, name: strings.default_column_heading}, function(result) {
                    addColumn(result.id, strings.default_column_heading);
                    lastHistoryId = result.historyid;
                    newBusy = false;
                }, function() {
                    newBusy = false;
                });
            });

            $(".mod_board").append(column);
        };

        var processBoardHistory = function() {
            serviceCall('board_history', {id: board.id, since: lastHistoryId}, function(boardhistory) {
                for (var index in boardhistory) {
                    var item = boardhistory[index];
                    if (item.boardid!=board.id) {
                        continue; // hmm
                    }

                    var data = JSON.parse(item.content);
                    if (item.action=='add_note') {
                        addNote(data.columnid, data.id, data.heading, data.content, data.attachment,
                            {id: item.userid}, data.timecreated, data.rating);
                        updateNoteAria(data.id);
                        sortNotes($('.board_column[data-ident='+data.columnid+'] .board_column_content'));
                    } else if (item.action=='update_note') {
                        var note = getNote(data.id);
                        if (note) {
                            var heading = getNoteHeadingForNote(note);

                            var updateNote = function() {
                                heading.html(data.heading);
                                if (data.heading) {
                                    heading.show();
                                } else {
                                    heading.hide();
                                }
                                getNoteTextForNote(note).html(data.content);
                                setAttachment(note, data.attachment);
                                noteTextCache = null;
                                noteHeadingCache = null;
                                attachmentCache = null;
                                updateNoteAria(data.id);
                            };

                            if (editingNote==data.id) {
                                Notification.confirm(
                                    strings.note_changed_text.split("\n")[0], // Confirm.
                                    strings.note_changed_text.split("\n")[1], // Are you sure?
                                    strings.Ok,
                                    strings.Cancel,
                                    function() {
                                        if (editingNote==data.id) {
                                            updateNote();
                                        }
                                        stopNoteEdit();
                                    }
                                );
                            } else {
                                updateNote();
                            }
                        }
                    } else if (item.action=='delete_note') {
                        if (editingNote==data.id) {
                            Notification.alert(strings.warning, strings.note_deleted_text);
                            stopNoteEdit();
                        }
                        getNote(data.id).remove();

                    } else if (item.action=='add_column') {
                        addColumn(data.id, data.name);
                    } else if (item.action=='update_column') {
                        $(".board_column[data-ident='"+data.id+"'] .column_name").html(data.name);
                        updateColumnAria(data.id);
                    } else if (item.action=='delete_column') {
                        var column = $(".board_column[data-ident='"+data.id+"']");
                        if (editingNote && column.find('.board_note[data-ident="'+editingNote+'"]').length) {
                            stopNoteEdit();
                        }
                        column.remove();
                    } else if (item.action=='rate_note') {
                        var note = getNote(data.id);
                        note.find('.rating').html(data.rating);
                        if (sortby==SORTBY_RATING) {
                            sortNotes(note.closest('.board_column_content'));
                        }
                    }
                    lastHistoryId = item.id;
                }

                updateBoard();
            });
        };

        var updateBoard = function(instant) {
            if (instant) {
                processBoardHistory();
            } else if (options.history_refresh > 0) {
                if (reloadTimer) {
                    stopUpdating();
                }
                reloadTimer = setTimeout(processBoardHistory, options.history_refresh * 1000);
            }
        };

        var stopUpdating = function() {
            clearTimeout(reloadTimer);
            reloadTimer = null;
        };

        var sortNotes = function(content, toggle) {
            var sort_col = $(content).parent().find('.column_sort');
            var direction = $(content).data('sort');
            if (!direction) {
                if (sortby==SORTBY_RATING) {
                    direction = 'desc';
                } else {
                    direction = 'asc';
                }
            }
            if (toggle) {
                direction = direction=='asc'?'desc':'asc';
            }

            if (direction=='asc') {
                sort_col.removeClass('fa-angle-down');
                sort_col.addClass('fa-angle-up');
            } else {
                sort_col.removeClass('fa-angle-up');
                sort_col.addClass('fa-angle-down');
            }
            $(content).data('sort', direction);

            if (sortby==SORTBY_DATE) {
                var desc = function(a, b) {
                    return $(b).data("sortorder") - $(a).data("sortorder");
                };
                var asc = function(a, b) {
                    return $(a).data("sortorder") - $(b).data("sortorder");
                };
            } else if (sortby==SORTBY_RATING) {
                var desc = function(a, b) {
                    return $(b).find('.rating').text() - $(a).find('.rating').text() ||
                    $(b).data("sortorder") - $(a).data("sortorder");
                };
                var asc = function(a, b) {
                    return $(a).find('.rating').text() - $(b).find('.rating').text() ||
                    $(a).data("sortorder") - $(b).data("sortorder");
                };
            }

            $('> .board_note', $(content)).sort(direction=='asc'?asc:desc).appendTo($(content));

        };

        var updateSortable = function() {
            $( ".board_column_content" ).sortable({
                connectWith: ".board_column_content",
                stop: function(e, ui) {
                    var note = $(ui.item);
                    var tocolumn = note.closest('.board_column');
                    var columnid = tocolumn.data('ident');

                    var elem = $(this);
                    serviceCall('move_note', {id: note.data('ident'), columnid: columnid}, function(result) {
                        if (result.status) {
                            lastHistoryId = result.historyid;
                            updateNoteAria(note.data('ident'));
                            sortNotes($('.board_column[data-ident='+columnid+'] .board_column_content'));
                        } else {
                            elem.sortable('cancel');
                        }
                    });
                }
            });
        };
        var init = function() {
            serviceCall('get_board', {id: board.id}, function(columns) {
                // init
                if (columns) {
                    for (var index in columns) {
                        addColumn(columns[index].id, columns[index].name, columns[index].notes || {});
                    }
                }

                isEditor && addNewColumnButton();

                lastHistoryId = board.historyid;

                isEditor && updateSortable();

                updateBoard();
            });
        };

        // get strings
        var stringsInfo = [];
        for (var string in strings) {
            stringsInfo.push({key: string, component: 'mod_board'});
        }

        $.when(Str.get_strings(stringsInfo)).done(function(results) {
            var index = 0;
            for (string in strings) {
                strings[string] = results[index++];
            }

            init();
        });
    };
});