define(['jquery', 'core/str', 'core/ajax', 'core/notification', 'mod_board/jquery.editable.amd'], function($, Str, Ajax, Notification) {
    
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
            warning: ''
            
        };
        
        var reloadTimer = null;
        var lastHistoryId = null;
        var isEditor = options.isEditor || false;
        var userId = options.userId || -1;
        var noteTextCache = null;
        var editingNote = 0;
        
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
        }
        
        var getNote = function(ident) {
            return $(".board_note[data-ident='"+ident+"']");
        }
        
        var getNoteText = function(ident) {
            return $(".board_note[data-ident='"+ident+"'] .note_text");
        }
        
        var showNewNoteButtons = function() {
            $('.board_column .board_column_newcontent .board_button.newnote').show();
        }
        
        var hideNewNoteButtons = function() {
            $('.board_column .board_column_newcontent .board_button.newnote').hide();
        }
        
        var stopNoteEdit = function() {
            if (noteTextCache) {
                getNoteText(editingNote).html(noteTextCache);
                noteTextCache = null;
            }
            
            var note = getNote(editingNote);
            if (note) {
                note.find('.note_buttons').hide();
                showNewNoteButtons();
            }
            
            editingNote = 0;
        }
        
        var startNoteEdit = function(ident) {
            if (editingNote) {
                if (editingNote==ident) {
                    return;
                }
                stopNoteEdit();
            }
            
            var pendingNote = getNote(0);
            if (pendingNote) {
                pendingNote.remove();
            }
            
            var note = getNote(ident);
            if (note) {
                note.find('.note_buttons').show();
                hideNewNoteButtons();
                noteTextCache = note.find('.note_text').html();
                editingNote = ident;
            }
        }
        
        var deleteNote = function(ident) {
            if (confirm(strings.remove_note_text)) {
                serviceCall('delete_note', {id: ident}, function(result) {
                    if (result.status) {
                        lastHistoryId = result.historyid;
                        getNote(ident).remove();
                    }
                });
            }
        }
        
        var addNote = function(columnid, ident, content, owner) {
            var ismynote = owner.id==userId || !ident;
            var iseditable = isEditor || ismynote;
            
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
            var noteText = $('<div class="note_text" tabindex="0">'+content+'</div>');
            notecontent.append(noteText);
            note.append(notecontent);
            
            var column_content = $('.board_column[data-ident='+columnid+'] .board_column_content');
            
            if (iseditable) {
                var buttons = $('<div class="note_buttons"></div>');
                buttons.hide();
                var postbutton = $('<div class="post_button" role="button" tabindex="0">'+strings.post_button_text+'</div>');
                var cancelbutton = $('<div class="cancel_button" role="button" tabindex="0">'+strings.cancel_button_text+'</div>');
                buttons.append(postbutton);
                buttons.append(cancelbutton);
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
                    if (!ident) {
                        note.remove();
                        showNewNoteButtons();
                    } else {
                        stopNoteEdit();
                    }
                });
                
                noteText.on('dblclick keypress', function(e) {
                    if (e.type=='keypress') {
                        if (e.keyCode===13) {
                            e.preventDefault();
                            noteText.dblclick();
                            return;
                        } else {
                            return;
                        }
                    }
                    
                    if (ident) {
                        startNoteEdit(ident);
                    } else {
                        hideNewNoteButtons();
                        buttons.show();
                    }
                });
                
                postbutton.on('click keypress', function(e) {
                    if (e.type=='keypress') {
                        if (e.keyCode===13) {
                            e.preventDefault();
                        } else {
                            return;
                        }
                    }
                    
                    if (!ident) { // new
                        serviceCall('add_note', {columnid: columnid, content: noteText.html()}, function(result) {
                            lastHistoryId = result.historyid;
                            
                            note.remove();
                            showNewNoteButtons();
                            addNote(columnid, result.id, noteText.html(), {id: userId});
                            sortNotes(column_content);
                        });
                        
                    } else { // update
                        serviceCall('update_note', {id: ident, content: noteText.html()}, function(result) {
                            if (result.status) {
                                noteTextCache = null;
                                stopNoteEdit();
                                lastHistoryId = result.historyid;
                            }
                        });
                    }
                });
                
                noteText.editable({
                    toggleFontSize : false,
                    closeOnEnter: false,
                    callback : function( data ) {
                        if (!ident && !data.$el[0].innerHTML) { // hide new note if empty
                            note.remove();
                            showNewNoteButtons();
                        }
                    }
                    
                });
            }
            
            if (ident) {
                var lastOne = column_content.find(".board_note").last();
                if (lastOne.length) {
                    note.insertAfter(lastOne);
                } else {
                    column_content.prepend(note);
                }
            } else {
                $('.board_column[data-ident='+columnid+'] .board_column_newcontent').append(note);
                noteText.dblclick(); // trigger edit of note
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
                        if (e.keyCode===13) {
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

            column_newcontent.append('<div class="board_button newnote" role="button" tabindex="0"><div class="button_content"><span class="fa '+options.noteicon+'"></span></div></div>');
            column_newcontent.on('click keypress', '.newnote', function(e) {
                if (e.type=='keypress') {
                    if (e.keyCode===13) {
                        e.preventDefault();
                    } else {
                        return;
                    }
                }
                
                addNote(ident, 0, '', {id: userId});
            });

            var lastOne = $(".mod_board .board_column_hasdata").last();
            if (lastOne.length) {
                column.insertAfter(lastOne);
            } else {
                $(".mod_board").append(column);
            }
                        
            if (notes) {
                for (index in notes) {
                    addNote(ident, notes[index].id, notes[index].content, {id: notes[index].userid});
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
                    if (item.action=='add_note') {
                        addNote(item.columnid, item.noteid, item.content, {id: item.userid});
                    } else if (item.action=='update_note') {
                        var note = getNote(item.noteid);
                        if (note) {
                            if (editingNote==item.noteid) {
                                noteTextCache = item.content;
                                
                                Notification.confirm(
                                    strings.note_changed_text.split("\n")[0], // Confirm.
                                    strings.note_changed_text.split("\n")[1], // Are you sure?
                                    strings.Ok,
                                    strings.Cancel,
                                    function() {
                                        if (editingNote==item.noteid) {
                                            note.find('.note_text').html(item.content);
                                        }
                                        stopNoteEdit();
                                    }
                                );
                            } else {
                                note.find('.note_text').html(item.content);
                            }
                        }
                    } else if (item.action=='delete_note') {
                        if (editingNote==item.noteid) {
                            Notification.alert(strings.warning, strings.note_deleted_text);
                            stopNoteEdit();
                        }
                        $(".board_note[data-ident='"+item.noteid+"']").remove();
                        
                    } else if (item.action=='add_column') {
                        addColumn(item.columnid, item.content);
                    } else if (item.action=='update_column') {
                        $(".board_column[data-ident='"+item.columnid+"'] .column_name").html(item.content);
                    } else if (item.action=='delete_column') {
                        var column = $(".board_column[data-ident='"+item.columnid+"']");
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
        }
        
        var stopUpdating = function() {
            clearTimeout(reloadTimer);
            reloadTimer = null;
        }
        
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
        }
        
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