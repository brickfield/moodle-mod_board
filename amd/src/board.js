define(['jquery', 'core/str', 'core/ajax', 'core/notification', 'core/templates', 'mod_board/jquery.editable.amd'], function($, Str, Ajax, Notification, Templates) {
    
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
        
    return function(board, options) {
        var strings = {
            default_column_heading: '',
            post_button_text: '',
            cancel_button_text: '',
            remove_note_text: '',
            remove_column_text: '',
            note_changed_text: '',
            note_deleted_text: '',
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
        };
        
        const MEDIA_SELECTION_BUTTONS = 1;
        const MEDIA_SELECTION_DROPDOWN = 2;
        var reloadTimer = null;
        var lastHistoryId = null;
        var isEditor = options.isEditor || false;
        var userId = options.userId || -1;
        var mediaSelection = options.mediaselection || MEDIA_SELECTION_BUTTONS;
        var noteTextCache = null, noteHeadingCache = null, attachmentCache = null;
        var editingNote = 0;
        var isReadOnlyBoard = options.readonly || false;
        
        var serviceCall = function(method, args, callback, failcallback) {
            if (method!=='board_history') {
                stopUpdating();
            }
            _serviceCall(method, args, function() {
                callback.apply(null, arguments);
                if (method!=='board_history') {
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
        
        var attachmentTypeChanged = function(note) {
            var noteAttachment = getNoteAttachmentsForNote(note);
            var type = noteAttachment.find('.type').val();
            
            var attachmentInfo = noteAttachment.find('.info');
            var attachmentUrl = noteAttachment.find('.url');
            
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
                attachmentUrl.show();
                
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
                if (mediaSelection==MEDIA_SELECTION_BUTTONS) {
                    attachmentIcon.hide();
                }
                attachmentInfo.val('');
                attachmentUrl.val('');
                if (mediaSelection==MEDIA_SELECTION_BUTTONS) {
                    getNoteButtonsForNote(note).find('.attachment_button').show();
                }
            }
        }
        
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
                    noteAttachment.find('.info').val(attachment.info);
                    noteAttachment.find('.url').val(attachment.url);
                }
                attachmentTypeChanged(note, attachment);
            }
            previewAttachment(note, attachment);
        };
        
        var attachmentDataForNote = function(note) {
            var attachment = { type: 0, info: null, url: null };
            var noteAttachment = getNoteAttachmentsForNote(note);
            if (noteAttachment) {
                attachment.type = noteAttachment.find('.type').val();
                attachment.info = noteAttachment.find('.info').val();
                attachment.url = noteAttachment.find('.url').val();
            }
            if (!attachment.info.length && !attachment.url.length) {
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
        }
        
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
            if (attachment.url) {
                switch(parseInt(attachment.type)) {
                    case 1: //youtube
                        elem.html('<iframe src="'+ fixEmbedUrlIfNeeded(attachment.url) +'" class="preview_element" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>');
                        elem.addClass('wrapper_youtube');
                        elem.show();
                    break;
                    case 2: // image
                        elem.html('<a href="'+ attachment.url +'" target="_blank"><img src="'+ attachment.url +'" class="preview_element" alt="'+ attachment.info +'"/></a>');
                        elem.addClass('wrapper_image');
                        elem.show();
                    break;
                    case 3: // url
                        elem.html('<a href="'+ attachment.url +'" class="preview_element" target="_blank">' + (attachment.info || attachment.url) + '</a>');
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
        
        var addNote = function(columnid, ident, heading, content, attachment, owner) {
            var ismynote = owner.id==userId || !ident;
            var iseditable = isEditor || (ismynote && !isReadOnlyBoard);
            
            if (!ident) {
                var pendingNote = getNote(0);
                if (pendingNote) {
                    pendingNote.remove();
                }
            }
            
            var note = $('<div class="board_note" data-ident="'+ident+'"></div>');
            if (ismynote) {
                note.addClass('mynote');
            }
            if (iseditable) {
                note.addClass('editablenote');
            }
            
            var notecontent = $('<div class="note_content"></div>');
            var noteHeading = $('<div class="note_heading" tabindex="0">' + (heading?heading:'') + '</div>');
            var noteText = $('<div class="note_text" tabindex="0">' + (content?content:'') + '</div>');
            var attachmentPreview = $('<div class="preview"></div>');
            if (iseditable) {
                var noteAttachment = $('<div class="note_attachment form-group row" tabindex="0">' +
                                    '<select class="type form-control form-control-sm '+(mediaSelection==MEDIA_SELECTION_BUTTONS?'hidden':'')+'">' +
                                        '<option value="0">'+strings.option_empty+'</option>' +
                                        '<option value="1">'+strings.option_youtube+'</option>' +
                                        '<option value="2">'+strings.option_image+'</option>' +
                                        '<option value="3">'+strings.option_link+'</option>' +
                                    '</select>' +
                                    '<span class="type_icon fa '+(mediaSelection==MEDIA_SELECTION_DROPDOWN?'hidden':'')+'"></span>'+
                                    '<input type="text" class="info form-control form-control-sm col-sm-12 '+(mediaSelection==MEDIA_SELECTION_BUTTONS?'with_type_icon':'')+'" placeholder="">' +
                                    '<input type="text" class="url form-control form-control-sm col-sm-12" placeholder="">' +
                                '</div>'
                            );
                
                noteAttachment.hide();
            }
            
            notecontent.append(noteHeading);
            notecontent.append(noteText);
            if (iseditable) {
                notecontent.append(noteAttachment);
            }
            
            notecontent.append(attachmentPreview);
            note.append(notecontent);
            
            if (iseditable) {
                var attachmentType = noteAttachment.find('.type');
                var attachmentInfo = noteAttachment.find('.info');
                var attachmentUrl = noteAttachment.find('.url');
                var attachmentIcon = noteAttachment.find('.type_icon');
                
                attachmentType.on('change', function() {
                    attachmentTypeChanged(note);
                    previewAttachment(note);
                });
                
                attachmentUrl.on('change', function() {
                    previewAttachment(note);
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
                var postbutton = $('<div class="post_button action_button" role="button" tabindex="0">'+strings.post_button_text+'</div>');
                var cancelbutton = $('<div class="cancel_button action_button" role="button" tabindex="0">'+strings.cancel_button_text+'</div>');
                
                buttons.append(postbutton);
                buttons.append(cancelbutton);
                
                if (mediaSelection==MEDIA_SELECTION_BUTTONS) {
                    buttons.append('<div class="spacer_button"></div>');
                    var ytButton = $('<div class="attachment_button youtube_button action_button fa '+attachmentFAIcons[0]+'" role="button" tabindex="0"></div>');
                    ytButton.on('click', function() { attachmentType.val(1); attachmentType.trigger("change"); });
                    var imgButton = $('<div class="attachment_button image_button action_button fa '+attachmentFAIcons[1]+'" role="button" tabindex="0"></div>');
                    imgButton.on('click', function() { attachmentType.val(2); attachmentType.trigger("change"); });
                    var linkButton = $('<div class="attachment_button link_button action_button fa '+attachmentFAIcons[2]+'" role="button" tabindex="0"></div>');
                    linkButton.on('click', function() { attachmentType.val(3); attachmentType.trigger("change"); });
                    buttons.append(ytButton);
                    buttons.append(imgButton);
                    buttons.append(linkButton);
                }
                
                note.append(buttons);
                
                var removeElement = $('<div class="remove fa fa-remove" role="button" tabindex="0"></div>');
                removeElement.on('click keypress', function(e) {
                    if (e.type=='keypress') {
                        if (e.keyCode===13) {
                            e.preventDefault();
                        } else {
                            return;
                        }
                    }
                    
                    deleteNote(ident);
                });
                if (!ident) {
                    removeElement.hide();
                }
                notecontent.append(removeElement);
                
                cancelbutton.on('click keypress', function(e) {
                    if (e.type=='keypress') {
                        if (e.keyCode===13) {
                            e.preventDefault();
                        } else {
                            return;
                        }
                    }
                    
                    stopNoteEdit();
                });
                
                noteText.on('click', function(e) {
                    if ((editingNote && editingNote==ident) || !ident) {
                        noteText.editable('open');
                    }
                });
                
                var beginEdit = function() {
                    startNoteEdit(ident);
                };
                
                noteText.on('dblclick keypress', function(e) {
                    if (e.type=='keypress') {
                        if (e.keyCode===13 && !noteText.is(':editing')) {
                            e.preventDefault();
                            noteText.dblclick();
                            return;
                        } else {
                            return;
                        }
                    }
                    
                    beginEdit();
                });
                
                noteHeading.on('click', function(e) {
                    if ((editingNote && editingNote==ident) || !ident) {
                        noteHeading.editable('open');
                    }
                });
                
                noteHeading.on('dblclick keypress', function(e) {
                    if (e.type=='keypress') {
                        if (e.keyCode===13 && !noteHeading.is(':editing')) {
                            e.preventDefault();
                            noteHeading.dblclick();
                            return;
                        } else {
                            return;
                        }
                    }
                    
                    beginEdit();
                });
                
                attachmentPreview.on('dblclick', function() {
                    beginEdit();
                });
                
                postbutton.on('click keypress', function(e) {
                    if (e.type=='keypress') {
                        if (e.keyCode===13) {
                            e.preventDefault();
                        } else {
                            return;
                        }
                    }
                    
                    var sendAttach = attachmentDataForNote(note);
                    
                    if (!ident) { // new
                        var addHeading = noteHeading.html();
                        var addText = noteText.html();
                        
                        serviceCall('add_note', {columnid: columnid, heading: addHeading, content: addText, attachment: sendAttach}, function(result) {
                            lastHistoryId = result.historyid;
                            
                            note.remove();
                            showNewNoteButtons();
                            addNote(columnid, result.id, addHeading, addText, sendAttach, {id: userId});
                            sortNotes(column_content);
                        });
                        
                    } else { // update
                        serviceCall('update_note', {id: ident, heading: noteHeading.html(), content: noteText.html(), attachment: sendAttach}, function(result) {
                            if (result.status) {
                                setAttachment(note, sendAttach);
                                successNoteEdit();
                                lastHistoryId = result.historyid;
                            }
                        });
                    }
                });
                
                noteText.editable({
                    toggleFontSize : false,
                    closeOnEnter: false,
                    /*
                    callback : function( data ) {
                        if (!ident && !noteText.html()) { // hide new note if empty
                            note.remove();
                            showNewNoteButtons();
                        }
                    }
                    */
                });
                
                noteHeading.editable({
                    toggleFontSize : false,
                    closeOnEnter: true
                });
                
                setAttachment(note, attachment);
            } else {
                previewAttachment(note, attachment);
            }
            
            if (ident) {
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
                noteText.dblclick() // trigger edit of note
            }
        };
        
        var addColumn = function(ident, name, notes, sort) {
            var iseditable = isEditor;
            var nameCache = null;
            var column = $('<div class="board_column board_column_hasdata" data-ident="'+ident+'"></div>');
            var column_header = $('<div class="board_column_header"></div>');
            var column_sort = $('<div class="column_sort fa"></div>');
            var column_name = $('<div class="column_name" tabindex="0">'+name+'</div>');
            var column_content = $('<div class="board_column_content"></div>');
            var column_newcontent = $('<div class="board_column_newcontent"></div>');
            column_header.append(column_sort);
            column_header.append(column_name);
            
            column_sort.on('click', function() {
                sortNotes(column_content, true);
            });
            
            if (iseditable) {
                column.addClass('editablecolumn');

                var removeElement = $('<div class="remove fa fa-remove" role="button" tabindex="0"></div>');
                removeElement.on('click keypress', function(e) {
                    if (e.type=='keypress') {
                        if (e.keyCode===13) {
                            e.preventDefault();
                        } else {
                            return;
                        }
                    }
                    
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
                column_name.on('dblclick keypress', function(e) {
                    if (e.type=='keypress') {
                        if (e.keyCode===13 && !column_name.is(':editing')) {
                            e.preventDefault();
                            column_name.dblclick();
                        } else {
                            return;
                        }
                    }
                    
                    nameCache = column_name.html();
                });
                
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
                                }
                            }, function() {
                                column_name.html(nameCache);
                                nameCache = null;
                            });
                        }
                    }
                });
            }

            if (!isReadOnlyBoard) {
                column_newcontent.append('<div class="board_button newnote" role="button" tabindex="0"><div class="button_content"><span class="fa '+options.noteicon+'"></span></div></div>');
                column_newcontent.on('click keypress', '.newnote', function(e) {
                    if (e.type=='keypress') {
                        if (e.keyCode===13) {
                            e.preventDefault();
                        } else {
                            return;
                        }
                    }
                    
                    addNote(ident, 0, null, null, null, {id: userId});
                });
            }

            var lastOne = $(".mod_board .board_column_hasdata").last();
            if (lastOne.length) {
                column.insertAfter(lastOne);
            } else {
                $(".mod_board").append(column);
            }
                        
            if (notes) {
                for (index in notes) {
                    addNote(ident, notes[index].id, notes[index].heading, notes[index].content, {type: notes[index].type, info: notes[index].info, url: notes[index].url}, {id: notes[index].userid});
                }
            }
            sortNotes(column_content, true);
        };
        
        var addNewColumnButton = function() {
            var column = $('<div class="board_column board_column_empty"></div>');
            var newBusy = false;
            column.append('<div class="board_button newcolumn" role="button" tabindex="0"><div class="button_content"><span class="fa '+options.columnicon+'"></span></div></div>');
            column.on('click keypress', '.newcolumn', function(e) {
                if (newBusy) {
                    return;
                }
                newBusy = true;
                if (e.type=='keypress') {
                    if (e.keyCode===13) {
                        e.preventDefault();
                    } else {
                        return;
                    }
                }
                
                serviceCall('add_column', {boardid: board.id, name: strings.default_column_heading}, function(result) {
                    addColumn(result.id, strings.default_column_heading);
                    lastHistoryId = result.historyid;
                    newBusy = false;
                }, function(error) {
                    newBusy = false;
                });
            });
            
            $(".mod_board").append(column);
        };
        
        var processBoardHistory = function() {
            serviceCall('board_history', {id: board.id, since: lastHistoryId}, function(boardhistory) {
                for (index in boardhistory) {
                    var item = boardhistory[index];
                    if (item.boardid!=board.id) {
                        continue; // hmm
                    }
                    
                    var data = JSON.parse(item.content);
                    if (item.action=='add_note') {
                        addNote(data.columnid, data.id, data.heading, data.content, data.attachment, {id: item.userid});
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
                    } else if (item.action=='delete_column') {
                        var column = $(".board_column[data-ident='"+data.id+"']");
                        if (editingNote && column.find('.board_note[data-ident="'+editingNote+'"]').length) {
                            stopNoteEdit();
                        }
                        column.remove();
                    }
                    lastHistoryId = item.id;
                }
                
                updateBoard();
            });
        };
        
        var updateBoard = function(instant) {
            if (instant) {
                processBoardHistory();
            } else {
                if (reloadTimer) {
                    stopUpdating();
                }
                reloadTimer = setTimeout(processBoardHistory, 2000);
            }
        };
        
        var stopUpdating = function() {
            clearTimeout(reloadTimer);
            reloadTimer = null;
        };
        
        var sortNotes = function(content, toggle) {
            var sort_col = $(content).parent().find('.column_sort');
            var direction = $(content).data('sort');
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
            
            $('> .board_note', $(content)).sort(direction=='asc'?asc:desc).appendTo($(content));
            function desc(a, b) { return $(b).data("ident") < $(a).data("ident") ? -1 : 1; }
            function asc(a, b) { return $(b).data("ident") < $(a).data("ident") ? 1 : -1; }
        };
        
        var init = function() {
            serviceCall('get_board', {id: board.id}, function(columns) {
                // init
                if (columns) {
                    for (index in columns) {
                        addColumn(columns[index].id, columns[index].name, columns[index].notes || {});
                    }
                }
                if (isEditor) {
                    addNewColumnButton();
                }
                
                lastHistoryId = board.historyid;
            });
        };
   
        // get strings
        var stringsInfo = [];
        for (string in strings) {
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