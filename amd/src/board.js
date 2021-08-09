// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A javascript module to handle the board.
 *
 * @package    mod_board
 * @author     Karen Holland <karen@brickfieldlabs.ie>
 * @copyrigt   2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from "jquery";
import "jqueryui";
import {get_strings as getStrings} from "core/str";
import Ajax from "core/ajax";
import Notification from "core/notification";
import "mod_board/jquery.editable.amd";

/**
 * Execute a ajax call to a mod_board ajax service.
 *
 * @method _serviceCall
 * @param method
 * @param args
 * @param callback
 * @param failcallback
 * @private
 */
const _serviceCall = function(method, args, callback, failcallback) {
    Ajax.call([{
        methodname: 'mod_board_' + method,
        args: args,
        done: function(data) {
            callback(data);
        },
        fail: function(error) {
            Notification.exception(error);
            if (failcallback) {
                failcallback(error);
            }
        }
    }]);
};

/**
 * Indicates if this is a keycode we want to listend to for
 * aria purposes.
 *
 * @method isAriaTriggerKey
 * @param key
 * @returns {boolean}
 */
const isAriaTriggerKey = function(key) {
    return key == 13 || key == 32;
};

/**
 * Encodes text into html entities.
 *
 * @method encodeText
 * @param rawText
 * @returns {*|jQuery}
 */
const encodeText = function(rawText) {
    return $('<div />').text(rawText).html();
};

/**
 * Decodes text from html entities.
 *
 * @method decodeText
 * @param encodedText
 * @returns {*|jQuery}
 */
const decodeText = function(encodedText) {
    return $('<div />').html(encodedText).text();
};

/**
 * Handler for keypress and click actions.
 *
 * $method handleAction
 * @param elem
 * @param callback
 * @returns {*}
 */
const handleAction = function(elem, callback) {
    return elem.on('click keypress', function(e) {
        if (e.type == 'keypress') {
            if (isAriaTriggerKey(e.keyCode)) {
                e.preventDefault();
            } else {
                return;
            }
        }

        callback();
    });
};

/**
 * Setting up element edibility.
 *
 * @method handleEditableAction
 * @param elem
 * @param callback
 * @param callBeforeOnKeyEditing
 * @returns {*}
 */
const handleEditableAction = function(elem, callback, callBeforeOnKeyEditing) {
    if (elem.is(':editable')) {
        throw new Error('handleEditableAction - must be called before setting the element as editable');
    }

    // Can't use on(edit) here because we want to do actions (save cache) before the control goes into edit mode
    return elem.on('dblclick keypress', function(e) {
        if (e.type == 'keypress') {
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

/**
 * The default function of the module, which does the setup of the page.
 *
 * @param board
 * @param options
 */
export default function(board, options) {
    // An array of strings to load as a batch later.
    // Not necessary, but used to load all the strings in one ajax call.
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

    const MEDIA_SELECTION_BUTTONS = 1,
          MEDIA_SELECTION_DROPDOWN = 2,
          ATTACHMENT_VIDEO = 1,
          ATTACHMENT_IMAGE = 2,
          ATTACHMENT_LINK = 3,
          SORTBY_DATE = 1,
          SORTBY_RATING = 2;
    var reloadTimer = null,
        lastHistoryId = null,
        isEditor = options.isEditor || false,
        userId = options.userId || -1,
        mediaSelection = options.mediaselection || MEDIA_SELECTION_BUTTONS,
        noteTextCache = null,
        noteHeadingCache = null,
        attachmentCache = null,
        editingNote = 0,
        isReadOnlyBoard = options.readonly || false,
        acceptedFileExtensions = options.file.extensions,
        acceptedFileSizeMin = options.file.size_min,
        acceptedFileSizeMax = options.file.size_max,
        ratingenabled = options.ratingenabled,
        sortby = options.sortby || SORTBY_DATE;

    /**
     * Helper method to make calles to mod_board external services.
     *
     * @method serviceCall
     * @param method
     * @param args
     * @param callback
     * @param failcallback
     */
    var serviceCall = function(method, args, callback, failcallback) {
        if (method !== 'board_history') {
            stopUpdating();
        }
        _serviceCall(method, args, function() {
            callback.apply(null, arguments);
            if (method !== 'board_history' && method != 'get_board') {
                updateBoard(true);
            }
        }, failcallback);
    };

    /**
     * Returns the jquery element of a given note identifier.
     *
     * @method getNote
     * @param ident
     * @returns {jQuery|HTMLElement|*}
     */
    var getNote = function(ident) {
        return $(".board_note[data-ident='" + ident + "']");
    };

    /**
     * Returns the jquery element of the note text for the given note identifier.
     *
     * @method getNoteText
     * @param ident
     * @returns {jQuery|HTMLElement|*}
     */
    var getNoteText = function(ident) {
        return $(".board_note[data-ident='" + ident + "'] .mod_board_note_text");
    };

    /**
     * Returns the jquery element of the note text for the given note element.
     *
     * @method getNoteTextForNote
     * @param note
     * @returns {*|jQuery}
     */
    var getNoteTextForNote = function(note) {
        return $(note).find(".mod_board_note_text");
    };

    /**
     * Returns the jquery element of the note heading for the given note identifier.
     *
     * @method getNoteHeading
     * @param ident
     * @returns {jQuery|HTMLElement|*}
     */
    var getNoteHeading = function(ident) {
        return $(".board_note[data-ident='" + ident + "'] .mod_board_note_heading");
    };

    /**
     * Returns the jquery element of the note heading for the given note element.
     *
     * @method getNoteHeadingForNote
     * @param note
     * @returns {*|jQuery}
     */
    var getNoteHeadingForNote = function(note) {
        return $(note).find(".mod_board_note_heading");
    };

    /**
     * Returns the jquery element of the note border for the given note element.
     *
     * @method getNoteBorderForNote
     * @param note
     * @returns {*|jQuery}
     */
    var getNoteBorderForNote = function(note) {
        return $(note).find(".mod_board_note_border");
    };

    /**
     * Returns the jquery element of the note buttons for the given note element.
     *
     * @method getNoteButtonsForNote
     * @param note
     * @returns {*|jQuery}
     */
    var getNoteButtonsForNote = function(note) {
        return $(note).find(".mod_board_note_buttons");
    };

    /**
     * Shows the buttons creation new notes.
     *
     * @method showNewNoteButtons
     */
    var showNewNoteButtons = function() {
        $('.board_column .board_column_newcontent .board_button.newnote').show();
    };

    /**
     * Hides the buttons for creating new notes.
     *
     * @method hideNewNoteButtons
     */
    var hideNewNoteButtons = function() {
        $('.board_column .board_column_newcontent .board_button.newnote').hide();
    };

    /**
     * Gets a jquery node for the attachments of a given note.
     *
     * @method getNoteAttachmentsForNote
     * @param note
     * @returns {*|jQuery}
     */
    var getNoteAttachmentsForNote = function(note) {
        return $(note).find(".mod_board_note_attachment");
    };

    /**
     * Creates text identifier for a given node.
     *
     * @method textIdentifierForNote
     * @param note
     * @returns {null|*|jQuery}
     */
    var textIdentifierForNote = function(note) {
        var noteText = getNoteTextForNote(note).html(),
            noteHeading = getNoteHeadingForNote(note).html(),
            noteAttachment = attachmentDataForNote(note);

        if (noteHeading.length > 0) {
            return noteHeading;
        }
        if (noteText.length > 0) {
            return noteText.replace(/<br\s*\/?>/gi, " ").replace(/\n/g, " ").split(/\s+/).slice(0, 5).join(" ");
        }
        if (noteAttachment.info && noteAttachment.info.length > 0) {
            return noteAttachment.info;
        }
        return null;
    };

    /**
     * Update the Aria info for a given note id.
     *
     * @method updateNoteAria
     * @param noteId
     */
    var updateNoteAria = function(noteId) {
        var note = getNote(noteId),
            columnIdentifier = note.closest('.board_column').find('.mod_board_column_name').text(),
            postButton = "",
            cancelButton = "",
            addYoutube = "",
            addImage = "",
            addLink = "",
            removeAttachment = "",
            chooseFileButton = "";

        if (!noteId) { // New post
            postButton = strings.aria_postnew.replace('{column}', columnIdentifier);
            cancelButton = strings.aria_cancelnew.replace('{column}', columnIdentifier);
            addYoutube = strings.aria_addmedianew.replace('{type}', strings.option_youtube).replace('{column}',
                          columnIdentifier);
            addImage = strings.aria_addmedianew.replace('{type}', strings.option_image).replace('{column}', columnIdentifier);
            addLink = strings.aria_addmedianew.replace('{type}', strings.option_link).replace('{column}', columnIdentifier);
            chooseFileButton = strings.aria_choosefilenew.replace('{column}', strings.columnIdentifier);
        } else {
            var noteIdentifier = textIdentifierForNote(note);

            postButton = strings.aria_postedit.replace('{column}', columnIdentifier).replace('{post}', noteIdentifier);
            cancelButton = strings.aria_canceledit.replace('{column}', columnIdentifier).replace('{post}', noteIdentifier);
            addYoutube = strings.aria_addmedia.replace('{type}', strings.option_youtube).replace('{column}',
                          columnIdentifier).replace('{post}', noteIdentifier);
            addImage = strings.aria_addmedia.replace('{type}', strings.option_image).replace('{column}',
                          columnIdentifier).replace('{post}', noteIdentifier);
            addLink = strings.aria_addmedia.replace('{type}', strings.option_link).replace('{column}',
                          columnIdentifier).replace('{post}', noteIdentifier);
            removeAttachment = strings.aria_deleteattachment.replace('{column}',
            columnIdentifier).replace('{post}', noteIdentifier);
            chooseFileButton = strings.aria_choosefileedit.replace('{column}',
            columnIdentifier).replace('{post}', noteIdentifier);

            note.find('.delete_note').attr('aria-label', strings.aria_deletepost.replace('{column}',
                columnIdentifier).replace('{post}', noteIdentifier));
            note.find('.mod_board_rating').attr('aria-label', strings.aria_ratepost.replace('{column}',
                columnIdentifier).replace('{post}', noteIdentifier));
            note.find('.note_ariatext').html(noteIdentifier);
        }

        // Attach media buttons, if set.
        if (mediaSelection == MEDIA_SELECTION_BUTTONS) {
            if (noteId) {
                var attRemove = note.find('.mod_board_remove_attachment');
                if (attRemove) {
                    attRemove.attr('aria-label', removeAttachment);
                }
            }

            note.find('.mod_board_attachment_button.youtube_button').attr('aria-label', addYoutube);
            note.find('.mod_board_attachment_button.image_button').attr('aria-label', addImage);
            note.find('.mod_board_attachment_button.link_button').attr('aria-label', addLink);
        }
        note.find('.post_button').attr('aria-label', postButton);
        note.find('.cancel_button').attr('aria-label', cancelButton);
        note.find('.choose_file_button').attr('aria-label', chooseFileButton);
    };

    /**
     * Update the Aria information for a given column id.
     *
     * @method updateColumnAria
     * @param columnId
     */
    var updateColumnAria = function(columnId) {
        var column = $('.board_column[data-ident=' + columnId + ']'),
            columnIdentifier = column.find('.mod_board_column_name').text();
        column.find('.newnote').attr('aria-label', strings.aria_newpost.replace('{column}', columnIdentifier));
        column.find('.delete_column').attr('aria-label', strings.aria_deletecolumn.replace('{column}', columnIdentifier));

        column.find(".board_note").each(function(index, note) {
            updateNoteAria($(note).data('ident'));
        });
    };

    /**
     * Clean things up after successfully editing a note.
     *
     * @method successNoteEdit
     */
    var successNoteEdit = function() {
        noteTextCache = null;
        noteHeadingCache = null;
        attachmentCache = null;
        stopNoteEdit();
    };

    /**
     * Stop the current note editing process.
     *
     * @method stopNoteEdit
     */
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
            var noteText = getNoteTextForNote(note);
            var noteBorder = getNoteBorderForNote(note);
            if (!noteHeading.html()) {
                noteHeading.hide();
                noteBorder.hide();
            }
            if (!noteText.html() && noteHeading.html()) {
                noteText.hide();
                noteBorder.hide();
            }
        }

        editingNote = 0;
    };

    /**
     * Start the editing of a particular note, by identifier.
     *
     * @method startNoteEdit
     * @param ident
     */
    var startNoteEdit = function(ident) {
        if (editingNote) {
            if (editingNote == ident) {
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
            var noteText = getNoteTextForNote(note);
            var noteBorder = getNoteBorderForNote(note);
            if (ident) {
                attachmentCache = attachmentDataForNote(note);
                noteTextCache = noteText.html();
                noteHeadingCache = noteHeading.html();
                editingNote = ident;
            }
            noteHeading.show();
            noteBorder.show();
            noteText.show();
        }
    };

    /**
     * Delete a given note, by identifier.
     *
     * @method deleteNote
     * @param ident
     */
    var deleteNote = function(ident) {
        Notification.confirm(
            strings.remove_note_text.split("\n")[1], // Are you sure?
            strings.remove_note_text.split("\n")[0], // This will effect others.
            strings.Ok,
            strings.Cancel,
            function() {
                serviceCall('delete_note', {id: ident}, function(result) {
                    if (result.status) {
                        lastHistoryId = result.historyid;
                        getNote(ident).remove();
                    }
                });
            }
        );
    };

    /**
     * Rate (star) a give note, by identifier.
     *
     * @method rateNote
     * @param ident
     */
    var rateNote = function(ident) {
        if (!ratingenabled) {
            return;
        }
        if (isReadOnlyBoard) {
            return;
        }

        var note = getNote(ident),
            rating = note.find('.mod_board_rating');
        if (rating.data('disabled')) {
            return;
        }
        rating.data('disabled', true);

        serviceCall('can_rate_note', {id: ident}, function(canrate) {
            if (canrate) {
                Notification.confirm(
                    strings.rate_note_text, // Are you sure?
                    null,
                    strings.Ok,
                    strings.Cancel,
                    function() {
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
                );

            }
        });
    };

    /**
     * Update the attachment information of a note.
     *
     * @method attachmentTypeChanged
     * @param note
     */
    var attachmentTypeChanged = function(note) {
        var noteAttachment = getNoteAttachmentsForNote(note),
            type = noteAttachment.find('.mod_board_type').val(),
            attachmentInfo = noteAttachment.find('.info'),
            attachmentUrl = noteAttachment.find('.url'),
            attachmentFile = noteAttachment.find('.mod_board_file');

        if (mediaSelection == MEDIA_SELECTION_BUTTONS) {
            var attachmentIcon = noteAttachment.find('.mod_board_type_icon'),
                removeAttachment = noteAttachment.find('.mod_board_remove_attachment');
        } else {
            getNoteButtonsForNote(note).find('.mod_board_attachment_button').hide();
        }

        if (type > "0") {
            if (mediaSelection == MEDIA_SELECTION_BUTTONS) {
                removeAttachment.show();
            }
            attachmentInfo.prop('placeholder', strings['option_' + attachmentTypeToString(type) + '_info']);
            attachmentUrl.prop('placeholder', strings['option_' + attachmentTypeToString(type) + '_url']);

            attachmentInfo.show();
            if (type == ATTACHMENT_IMAGE && FileReader) {
                attachmentFile.show();
                attachmentUrl.hide();
            } else {
                attachmentFile.hide();
                attachmentUrl.show();
            }

            if (mediaSelection == MEDIA_SELECTION_BUTTONS) {
                attachmentIcon.removeClass().addClass(['mod_board_type_icon', 'fa', attachmentFAIcon(type)]);
                attachmentIcon.show();
                getNoteButtonsForNote(note).find('.mod_board_attachment_button').hide();
            }
        } else {
            if (mediaSelection == MEDIA_SELECTION_BUTTONS) {
                removeAttachment.hide();
            }
            attachmentInfo.hide();
            attachmentUrl.hide();
            attachmentFile.hide();
            if (mediaSelection == MEDIA_SELECTION_BUTTONS) {
                attachmentIcon.hide();
            }
            attachmentInfo.val('');
            attachmentUrl.val('');
            if (mediaSelection == MEDIA_SELECTION_BUTTONS) {
                getNoteButtonsForNote(note).find('.mod_board_attachment_button').show();
            }
        }
    };

    /**
     * Set the attachment of a note.
     *
     * @method setAttachment
     * @param note
     * @param attachment
     */
    var setAttachment = function(note, attachment) {
        var noteAttachment = getNoteAttachmentsForNote(note);
        if (noteAttachment) {
            if (!attachment) {
                attachment = {type: "0"};
            } else {
                attachment.type += "";// Just in case
            }
            var attType = noteAttachment.find('.mod_board_type');
            attType.val(attachment.type ? attachment.type : "0");
            if (attType.val() > "0") {
                noteAttachment.find('.info').val(decodeText(attachment.info));
                noteAttachment.find('.url').val(decodeText(attachment.url));
            }
            attachmentTypeChanged(note, attachment);
        }
        previewAttachment(note, attachment);
    };

    /**
     * Returns an object with various information about a note's attachment.
     *
     * @method attachmentDataForNote
     * @param note
     * @returns {{filename: null, filecontents: null, type: number, url: null, info: null}}
     */
    var attachmentDataForNote = function(note) {
        var attachment = {type: 0, info: null, url: null, filename: null, filecontents: null},
            noteAttachment = getNoteAttachmentsForNote(note);
        if (noteAttachment.length) {
            attachment.type = noteAttachment.find('.mod_board_type').val();
            attachment.info = encodeText(noteAttachment.find('.info').val());
            attachment.url = encodeText(noteAttachment.find('.url').val());
            var fileElem = noteAttachment.find('.mod_board_file>input');
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

    /**
     * Get the string type of a attachment type number.
     *
     * @method attachmentTypeToString
     * @param type
     * @returns {string|null}
     */
    var attachmentTypeToString = function(type) {
        switch (type) {
            case "1": return 'youtube';
            case "2": return 'image';
            case "3": return 'link';
            default: return null;
        }
    };

    var attachmentFAIcons = ['fa-youtube', 'fa-picture-o', 'fa-link'];
    /**
     * Get the fa icon for a given numeric attachment type.
     *
     * @method attachmentFAIcon
     * @param type
     * @returns {string|null}
     */
    var attachmentFAIcon = function(type) {
        return attachmentFAIcons[type - 1] || null;
    };

    /**
     * Attempt to preload a give note file.
     *
     * @method preloadFile
     * @param note
     * @param callback
     */
    var preloadFile = function(note, callback) {
        var noteAttachment = getNoteAttachmentsForNote(note);
        if (noteAttachment.length) {
            var fileElem = noteAttachment.find('.mod_board_file>input');
            if (FileReader && fileElem.prop('files').length) {
                var file = fileElem.prop('files')[0];
                if (acceptedFileExtensions.indexOf(file.name.split('.').pop().toLowerCase()) == -1) { // Wrong exception
                    Notification.alert(strings.warning, strings.invalid_file_extension);
                } else if (file.size < acceptedFileSizeMin) {
                    Notification.alert(strings.warning, strings.invalid_file_size_min);
                } else if (file.size > acceptedFileSizeMax) {
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

    /**
     * Display the attachment preview for a note.
     *
     * @method previewAttachment
     * @param note
     * @param attachment
     */
    var previewAttachment = function(note, attachment) {
        var elem = note.find('.mod_board_preview');
        if (!attachment) {
            attachment = attachmentDataForNote(note);
        }
        var fixEmbedUrlIfNeeded = function(url) {
            return url.replace(/watch\?v=/gi, '/embed/').replace(/youtu\.be/, 'youtube.com/embed');
        };

        if (!getNoteTextForNote(note).html().length) {
            elem.addClass('mod_board_notext');
        } else {
            elem.removeClass('mod_board_notext');
        }

        elem.removeClass('wrapper_youtube');
        elem.removeClass('wrapper_image');
        elem.removeClass('wrapper_url');
        if (attachment.filename && parseInt(attachment.type) == ATTACHMENT_IMAGE) { // Before uploading
            elem.html('<img src="' + attachment.filecontents + '" class="mod_board_preview_element" alt="' +
            attachment.info + '"/>');
            elem.addClass('wrapper_image');
            elem.show();
        } else if (attachment.url) {
            switch (parseInt(attachment.type)) {
                case ATTACHMENT_VIDEO: // Youtube
                    elem.html('<iframe src="' + fixEmbedUrlIfNeeded(attachment.url) +
                    '" class="mod_board_preview_element" frameborder="0" allow="accelerometer; autoplay; clipboard-write;' +
                    'encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>');
                    elem.addClass('wrapper_youtube');
                    elem.show();
                break;
                case ATTACHMENT_IMAGE: // Image
                    elem.html('<img src="' + attachment.url + '" class="mod_board_preview_element" alt="' +
                    attachment.info + '"/>');
                    elem.addClass('wrapper_image');
                    elem.show();
                break;
                case ATTACHMENT_LINK: // Url
                    elem.html('<a href="' + attachment.url + '" class="mod_board_preview_element" target="_blank">' +
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

    /**
     * Add a new note with the given information.
     *
     * @method addNote
     * @param columnid
     * @param ident
     * @param heading
     * @param content
     * @param attachment
     * @param owner
     * @param sortorder
     * @param rating
     */
    var addNote = function(columnid, ident, heading, content, attachment, owner, sortorder, rating) {
        var ismynote = owner.id == userId || !ident;
        var iseditable = isEditor || (ismynote && !isReadOnlyBoard);

        if (!ident) {
            var pendingNote = getNote(0);
            if (pendingNote) {
                pendingNote.remove();
            }
        }

        var note = $('<div class="board_note" data-ident="' + ident + '" data-sortorder="' + sortorder + '"></div>');
        if (ismynote) {
            note.addClass('mod_board_mynote');
        }
        if (iseditable) {
            note.addClass('mod_board_editablenote');
        }

        var notecontent = $('<div class="mod_board_note_content"></div>'),
            noteHeading = $('<div class="mod_board_note_heading" tabindex="0">' + (heading ? heading : '') + '</div>'),
            noteBorder = $('<div class="mod_board_note_border"></div>'),
            noteText = $('<div class="mod_board_note_text" tabindex="0">' + (content ? content : '') + '</div>'),
            noteAriaText = $('<div class="note_ariatext hidden" role="heading" aria-level="4" tabindex="0"></div>'),
            attachmentPreview = $('<div class="mod_board_preview"></div>');
        if (iseditable) {
            var noteAttachment = $('<div class="mod_board_note_attachment form-group row" tabindex="0">' +
                                '<select class="mod_board_type form-control form-control-sm ' +
                                (mediaSelection == MEDIA_SELECTION_BUTTONS ? 'hidden' : '') + '">' +
                                    '<option value="0">' + strings.option_empty + '</option>' +
                                    '<option value="' + ATTACHMENT_VIDEO + '">' + strings.option_youtube + '</option>' +
                                    '<option value="' + ATTACHMENT_IMAGE + '">' + strings.option_image + '</option>' +
                                    '<option value="' + ATTACHMENT_LINK + '">' + strings.option_link + '</option>' +
                                '</select>' +
                                '<span class="mod_board_type_icon fa ' + (mediaSelection == MEDIA_SELECTION_DROPDOWN ?
                                    'hidden' : '') + '">' +
                                '</span>' +
                                '<input type="text" class="info form-control form-control-sm col-sm-12 ' +
                                (mediaSelection == MEDIA_SELECTION_BUTTONS ? 'mod_board_with_type_icon' : '') +
                                '" placeholder="">' +
                                '<input type="text" class="url form-control form-control-sm col-sm-12" placeholder="">' +
                                '<div class="mod_board_file form-control form-control-sm"><label for="file' + ident +
                                '" class="choose_file_button mod_board_action_button p-0 w-100" tabindex="0">' +
                                strings.choose_file + '</label><input id="file' + ident + '" type="file" class="d-none"></div>' +
                            '</div>'
                        );

            noteAttachment.hide();
        }

        notecontent.append(noteHeading);
        notecontent.append(noteBorder);
        notecontent.append(noteText);
        notecontent.append(noteAriaText);
        if (iseditable) {
            notecontent.append(noteAttachment);
        }

        notecontent.append(attachmentPreview);
        note.append(notecontent);

        if (iseditable) {
            var attachmentType = noteAttachment.find('.mod_board_type'),
                attachmentInfo = noteAttachment.find('.info'),
                attachmentUrl = noteAttachment.find('.url'),
                attachmentFileInput = noteAttachment.find('.mod_board_file>input');

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

            if (mediaSelection == MEDIA_SELECTION_BUTTONS) {
                var removeAttachmentButton = $('<div class="mod_board_remove_attachment_button fa fa-remove"></div>');
                var removeAttachment = $('<div class="mod_board_remove_attachment"></div>');
                removeAttachment.append(removeAttachmentButton);
                removeAttachment.hide();
                removeAttachmentButton.on('click', function() {
                    attachmentType.val(0); attachmentType.trigger('change');
                });
                noteAttachment.append(removeAttachment);
            }
        }

        var column_content = $('.board_column[data-ident=' + columnid + '] .board_column_content');

        if (iseditable) {
            var buttons = $('<div class="mod_board_note_buttons"></div>');
            buttons.hide();
            var postbutton = $('<div class="post_button mod_board_action_button" role="button" tabindex="0">' +
                              strings.post_button_text + '</div>');
            var cancelbutton = $('<div class="cancel_button mod_board_action_button" role="button" tabindex="0">' +
                                strings.cancel_button_text + '</div>');

            buttons.append(postbutton);
            buttons.append(cancelbutton);

            if (mediaSelection == MEDIA_SELECTION_BUTTONS) {
                buttons.append('<div class="mod_board_spacer_button"></div>');
                var ytButton = $('<div class="mod_board_attachment_button youtube_button mod_board_action_button fa ' +
                                attachmentFAIcons[0] + '" role="button" tabindex="0"></div>');
                handleAction(ytButton, function() {
                    attachmentType.val(1); attachmentType.trigger("change");
                });
                var imgButton = $('<div class="mod_board_attachment_button image_button mod_board_action_button fa ' +
                                 attachmentFAIcons[1] + '" role="button" tabindex="0"></div>');
                handleAction(imgButton, function() {
                    attachmentType.val(2); attachmentType.trigger("change");
                });
                var linkButton = $('<div class="mod_board_attachment_button link_button mod_board_action_button fa ' +
                                  attachmentFAIcons[2] + '" role="button" tabindex="0"></div>');
                handleAction(linkButton, function() {
                    attachmentType.val(3); attachmentType.trigger("change");
                });
                buttons.append(ytButton);
                buttons.append(imgButton);
                buttons.append(linkButton);
            }

            note.append(buttons);

            var removeElement = $('<div class="mod_board_remove fa fa-remove delete_note" role="button" tabindex="0"></div>');
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
                if ((editingNote && editingNote == ident) || !ident) {
                    noteText.editable('open');
                }
            });

            var beginEdit = function() {
                startNoteEdit(ident);
            };

            noteHeading.on('click', function() {
                if ((editingNote && editingNote == ident) || !ident) {
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


                if (!ident) { // New
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

                } else { // Update
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
                toggleFontSize: false,
                closeOnEnter: false,
                callback: function() {
                    noteText.html(noteText.html().substring(0, options.post_max_length));
                }
            });

            handleEditableAction(noteHeading, beginEdit);
            noteHeading.editable({
                toggleFontSize: false,
                closeOnEnter: true
            });

            handleEditableAction(noteBorder, beginEdit);

            setAttachment(note, attachment);
        } else {
            previewAttachment(note, attachment);
        }

        if (ident) {
            if (ratingenabled) {
                note.addClass('mod_board_rateablenote');
                var rateElement = $('<div class="fa fa-star mod_board_rating" role="button" tabindex="0">' + rating + '</div>');

                handleAction(rateElement, function() {
                    rateNote(ident);
                });
                notecontent.append(rateElement);
            }

            if (!noteHeading.html()) {
                noteHeading.hide();
                noteBorder.hide();
            }
            if (!noteText.html() && noteHeading.html()) {
                noteText.hide();
                noteBorder.hide();
            }

            var lastOne = column_content.find(".board_note").last();
            if (lastOne.length) {
                note.insertAfter(lastOne);
            } else {
                column_content.prepend(note);
            }
        } else {
            $('.board_column[data-ident=' + columnid + '] .board_column_newcontent').append(note);
            updateNoteAria(ident);
            noteText.editable('open'); // Trigger edit of note
            beginEdit();
        }
    };

    /**
     * Add a new column.
     *
     * @method addColumn
     * @param ident
     * @param name
     * @param notes
     */
    var addColumn = function(ident, name, notes) {
        var iseditable = isEditor,
            nameCache = null,
            column = $('<div class="board_column board_column_hasdata" data-ident="' + ident + '"></div>'),
            columnHeader = $('<div class="board_column_header"></div>'),
            columnSort = $('<div class="mod_board_column_sort fa"></div>'),
            columnName = $('<div class="mod_board_column_name" tabindex="0" aria-level="3" role="heading">' + name + '</div>'),
            columnContent = $('<div class="board_column_content"></div>'),
            columnNewContent = $('<div class="board_column_newcontent"></div>');
        columnHeader.append(columnSort);
        columnHeader.append(columnName);

        if (options.hideheaders) {
            columnName.addClass('d-none');
        }

        columnSort.on('click', function() {
            sortNotes(columnContent, true);
        });

        if (iseditable) {
            column.addClass('mod_board_editablecolumn');

            var removeElement = $('<div class="mod_board_remove fa fa-remove delete_column" role="button" tabindex="0"></div>');
            handleAction(removeElement, function() {
                Notification.confirm(
                    strings.remove_column_text.split(". ")[1], // Are you sure?
                    strings.remove_column_text.split(". ")[0], // This will effect others.
                    strings.Ok,
                    strings.Cancel,
                    function() {
                        serviceCall('delete_column', {id: ident}, function(result) {
                            if (result.status) {
                                column.remove();
                                lastHistoryId = result.historyid;
                            }
                        });
                    }
                );
            });

            columnHeader.append(removeElement);
        }

        column.append(columnHeader);
        column.append(columnContent);
        column.append(columnNewContent);

        if (iseditable) {
            handleEditableAction(columnName, function() {
                nameCache = columnName.html();
            }, true);

            columnName.editable({
                toggleFontSize: false,
                closeOnEnter: true,
                callback: function(data) {
                    if (data.content) {
                        serviceCall('update_column', {id: ident, name: columnName.html()}, function(result) {
                            if (!result.status) {
                                columnName.html(nameCache);
                                nameCache = null;
                            } else {
                                lastHistoryId = result.historyid;
                                updateColumnAria(ident);
                            }
                        }, function() {
                            columnName.html(nameCache);
                            nameCache = null;
                        });
                    } else {
                        columnName.html(nameCache);
                        nameCache = null;
                    }
                }
            });
        }

        if (!isReadOnlyBoard) {
            columnNewContent.append('<div class="board_button newnote" role="button" tabindex="0">' +
            '<div class="button_content"><span class="fa ' + options.noteicon + '"></span></div></div>');

            handleAction(columnNewContent.find('.newnote'), function() {
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
        sortNotes(columnContent);
        updateColumnAria(ident);
        if (isEditor) {
            updateSortable();
        }
    };

    /**
     * Add the new column button.
     *
     * @method addNewColumnButton
     */
    var addNewColumnButton = function() {
        var column = $('<div class="board_column board_column_empty"></div>'),
            newBusy = false;
        column.append('<div class="board_button newcolumn" role="button" tabindex="0" aria-label="' +
        strings.aria_newcolumn + '"><div class="button_content"><span class="fa ' + options.columnicon +
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

    /**
     * Update a note with the provided information.
     *
     * @method updateNote
     * @param note
     * @param heading
     * @param data
     */
    var updateNote = function(note, heading, data) {
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

    /**
     * Fetch and process the recent board history.
     *
     * @method processBoardHistory
     */
    var processBoardHistory = function() {
        serviceCall('board_history', {id: board.id, since: lastHistoryId}, function(boardhistory) {
            for (var index in boardhistory) {
                var item = boardhistory[index];
                if (item.boardid != board.id) {
                    continue; // Hmm
                }

                var data = JSON.parse(item.content);
                if (item.action == 'add_note') {
                    addNote(data.columnid, data.id, data.heading, data.content, data.attachment,
                        {id: item.userid}, data.timecreated, data.rating);
                    updateNoteAria(data.id);
                    sortNotes($('.board_column[data-ident=' + data.columnid + '] .board_column_content'));
                } else if (item.action == 'update_note') {
                    var note = getNote(data.id);
                    if (note) {
                        var heading = getNoteHeadingForNote(note);

                        if (editingNote == data.id) {
                            Notification.confirm(
                                strings.note_changed_text.split("\n")[0], // Confirm.
                                strings.note_changed_text.split("\n")[1], // Are you sure?
                                strings.Ok,
                                strings.Cancel,
                                function(note, heading, data) {
                                    updateNote(note, heading, data);
                                    stopNoteEdit();
                                }
                            );
                        } else {
                            updateNote(note, heading, data);
                        }
                    }
                } else if (item.action == 'delete_note') {
                    if (editingNote == data.id) {
                        Notification.alert(strings.warning, strings.note_deleted_text);
                        stopNoteEdit();
                    }
                    getNote(data.id).remove();

                } else if (item.action == 'add_column') {
                    addColumn(data.id, data.name);
                } else if (item.action == 'update_column') {
                    $(".board_column[data-ident='" + data.id + "'] .mod_board_column_name").html(data.name);
                    updateColumnAria(data.id);
                } else if (item.action == 'delete_column') {
                    var column = $(".board_column[data-ident='" + data.id + "']");
                    if (editingNote && column.find('.board_note[data-ident="' + editingNote + '"]').length) {
                        stopNoteEdit();
                    }
                    column.remove();
                } else if (item.action == 'rate_note') {
                    var note = getNote(data.id);
                    note.find('.mod_board_rating').html(data.rating);
                    if (sortby == SORTBY_RATING) {
                        sortNotes(note.closest('.board_column_content'));
                    }
                }
                lastHistoryId = item.id;
            }

            updateBoard();
        });
    };

    /**
     * Trigger a board update.
     *
     * @method updateBoard
     * @param instant
     */
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

    /**
     * Stop/prevent the board reload timer from firing.
     *
     * @method stopUpdating
     */
    var stopUpdating = function() {
        clearTimeout(reloadTimer);
        reloadTimer = null;
    };

    /**
     * Sort a set of notes.
     *
     * @sortNotes
     * @param content
     * @param toggle
     */
    var sortNotes = function(content, toggle) {
        var sortCol = $(content).parent().find('.mod_board_column_sort'),
            direction = $(content).data('sort');
        if (!direction) {
            if (sortby == SORTBY_RATING) {
                direction = 'desc';
            } else {
                direction = 'asc';
            }
        }
        if (toggle) {
            direction = direction == 'asc' ? 'desc' : 'asc';
        }

        if (direction == 'asc') {
            sortCol.removeClass('fa-angle-down');
            sortCol.addClass('fa-angle-up');
        } else {
            sortCol.removeClass('fa-angle-up');
            sortCol.addClass('fa-angle-down');
        }
        $(content).data('sort', direction);

        var desc,
            asc;
        if (sortby == SORTBY_DATE) {
            desc = function(a, b) {
                return $(b).data("sortorder") - $(a).data("sortorder");
            };
            asc = function(a, b) {
                return $(a).data("sortorder") - $(b).data("sortorder");
            };
        } else {
            desc = function(a, b) {
                return $(b).find('.mod_board_rating').text() - $(a).find('.mod_board_rating').text() ||
                $(b).data("sortorder") - $(a).data("sortorder");
            };
            asc = function(a, b) {
                return $(a).find('.mod_board_rating').text() - $(b).find('.mod_board_rating').text() ||
                $(a).data("sortorder") - $(b).data("sortorder");
            };
        }

        $('> .board_note', $(content)).sort(direction === 'asc' ? asc : desc).appendTo($(content));

    };

    /**
     * Update sorting of sortable content.
     *
     * @method updateSortable
     */
    var updateSortable = function() {
        $(".board_column_content").sortable({
            connectWith: ".board_column_content",
            stop: function(e, ui) {
                var note = $(ui.item),
                    tocolumn = note.closest('.board_column'),
                    columnid = tocolumn.data('ident'),
                    elem = $(this);

                serviceCall('move_note', {id: note.data('ident'), columnid: columnid}, function(result) {
                    if (result.status) {
                        lastHistoryId = result.historyid;
                        updateNoteAria(note.data('ident'));
                        sortNotes($('.board_column[data-ident=' + columnid + '] .board_column_content'));
                    } else {
                        elem.sortable('cancel');
                    }
                });
            }
        });
    };

    /**
     * Initialize board.
     *
     * @method init
     */
    var init = function() {
        serviceCall('get_board', {id: board.id}, function(columns) {
            // Init
            if (columns) {
                for (var index in columns) {
                    addColumn(columns[index].id, columns[index].name, columns[index].notes || {});
                }
            }

            if (isEditor) {
                addNewColumnButton();
            }

            lastHistoryId = board.historyid;

            if (isEditor) {
                updateSortable();
            }

            updateBoard();
        });
    };

    // Get strings
    var stringsInfo = [];
    for (var string in strings) {
        stringsInfo.push({key: string, component: 'mod_board'});
    }

    $.when(getStrings(stringsInfo)).done(function(results) {
        var index = 0;
        for (string in strings) {
            strings[string] = results[index++];
        }

        init();
    });
}
