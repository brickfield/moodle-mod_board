<?php
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
 * Language strings for mod_board.
 * @package     mod_board
 * @author      Karen Holland <karen@brickfieldlabs.ie>
 * @copyright   2021 Brickfield Education Labs <https://www.brickfield.ie/>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['pluginname'] = 'Tablero';
$string['modulename'] = 'Tablero';
$string['modulename_help'] = 'Esta es una nueva activida para Moodle que permite a un profesor crear un nuevo tablero de "postit".';
$string['modulenameplural'] = 'Tableros';
$string['board:addinstance'] = 'Agrega un nuevo recurso de tablero';
$string['board:deleteallcomments'] = 'Ver y borrar todos los comentarios en una publicacion';
$string['board:postcomment'] = 'Crear y ver comentarios de publicaciones';
$string['board:view'] = 'Ver el contenido del tablero y administrar las propias publicaciones.';
$string['board:manageboard'] = 'Administrar columnas y todas las publicaciones.';
$string['pluginadministration'] = 'Administracion de módulos del Tablero';
$string['hideheaders'] = 'Ocultar encabezados para los estudiantes ';
$string['completiondetail:notes'] = 'Agregar notas: {$a}';
$string['completionnotesgroup'] = 'Rquiere notas';
$string['completionnotes'] = 'Requerir a los estudiantes esta cantidad de notas para completar la actividad';

$string['enableblanktarget'] = 'Habilitar objetivo blanco';
$string['enableblanktarget_help'] = 'Cuando esta habilitado todos los enlaces abrirán en la pestaña de una nueva ventana.';
$string['blanktargetenabled'] = 'Este teablero ha sido configurado para lanzar todas las URLs / enlaces web  en una nueva ventana o pestaña.';
$string['board_column_locked'] = 'Esta columna esta bloqueada y no puede ser editada.';
$string['default_column_heading'] = 'Encabezado';
$string['post_button_text'] = 'Publicación';
$string['cancel_button_text'] = 'Cancelar';
$string['remove_note_title'] = 'Confirmar';
$string['remove_note_text'] = "¿Estás seguro de querer eliminar esta publicación y todos los datos que contiene? esto afectará también a los demás usuarios";
$string['rate_note_title'] = "Confirmar";
$string['rate_note_text'] = 'Estas seguro de querer calificar estas publicaciónes?';
$string['remove_column_title'] = 'Confirmar';
$string['remove_column_text'] = 'Estas seguro de querer eliminar esta "{$a}" columna  y todas las publicaciones que contiene?';
$string['note_changed_title'] = 'Confirmar';
$string['note_changed_text'] = "La publicación que estás editando ha cambiado.";
$string['note_deleted_text'] = 'La publicación que estás editando ha sido eliminada.';
$string['delete'] = 'Borrar';
$string['Ok'] = 'Ok';
$string['Cancel'] = 'Cancelar';
$string['warning'] = 'Notificación';
$string['choose_file'] = 'Elige Imagen de Video';
$string['option_youtube'] = 'Video (YouTube)';
$string['option_youtube_info'] = 'Título de Video ';
$string['option_youtube_url'] = 'YouTube URL';
$string['option_image'] = 'Imagen';
$string['option_image_info'] = 'Título de Imagen';
$string['option_image_url'] = 'URL Imagen';
$string['option_link'] = 'Enlace';
$string['option_link_info'] = 'Enlace title';
$string['option_link_url'] = 'Enlace URL';
$string['option_empty'] = 'Ninguno';
$string['invalid_file_extension'] = 'Extension de archivo no aceptado para subida.';
$string['invalid_file_size_min'] = 'Archivo muy pequeño para ser aceptado.';
$string['invalid_file_size_max'] = 'Archivo muy grande para ser aceptado.';
$string['form_title'] = 'Titulo de Publicacióon';
$string['form_body'] = 'Contenido';
$string['form_image_file'] = 'Archivo de Imagen';
$string['form_mediatype'] = 'Media';
$string['modal_title_new'] = 'Nueva publicacion para columna {column}';
$string['modal_title_edit'] = 'Editar publicación para columna {column}';
$string['posts'] = 'Publicaciones';

$string['allowyoutube'] = 'Permitir a youtube';
$string['allowyoutube_desc'] = 'Si activas un botón para agregar un video de Youtube que es soportado.';
$string['new_column_icon'] = 'Nuevo icono de columna';
$string['new_column_icon_desc'] = 'Icono mostrado en el nuevo boton para columnas.';
$string['new_note_icon'] = 'Nuevo botón de publicación';
$string['new_note_icon_desc'] = 'Icono mostrado en el nuevo botón para publicaciones.';
$string['media_selection_buttons'] = 'Botones';
$string['media_selection_dropdown'] = 'Combo';
$string['media_selection'] = 'Selección media';
$string['media_selection_desc'] = 'Configura como la selección media para publicaciones será mostrada.';
$string['post_max_length'] = 'Publicar máxima longitud';
$string['post_max_length_desc'] = 'El contenido máximo permitido. Todo fuera de esta longitud sera recortado.';
$string['history'] = 'Historial deñ Tablero';
$string['historyinfo'] = 'El historial del tablero es usado sólo para guardar resgistros temporales, los cuáles son usados por javascript para refrescar las vistas del tablero, y luego son eliminados inmediatamente.';
$string['history_refresh'] = 'Reloj de refrescamiento del tablero';
$string['history_refresh_desc'] = 'Tiempo final en segundos del refrescamiento automático del tablero.Si se coloca en 0 o vacío entonces el tablero solo refrescaŕá durante las acciones del tablero agreggar/actualizar/etc';
$string['column_colours'] = 'Colores de las columnas';
$string['column_colours_desc'] = 'El color es usado al tope de cada columna. Estos son colores hex y deberían ser coloccados uno por línea como 3 o 6 caracteres. Si uno de estos valores no son iguales a un color entonces los de por defecto serán usados.';
$string['embed_width'] = 'Ancho de incrustado';
$string['embed_width_desc'] = 'Ancho para uso del iframe cuando incruste el Tablero en dentro del curso. Este deberia tener un valor CSS válido, e.j. px, rem, %, etc...';
$string['embed_height'] = 'Altura de incrustado';
$string['embed_height_desc'] = 'Altura para usar para el iframe cuando incruste el tablero dentro del curso. Este deberia tener un valor CSS válido, e.j. px, rem, %, etc...';

$string['export_board'] = 'Exportar CSV';
$string['export_submissions'] = 'Exportar entregas';
$string['export_firstname'] = 'Primer nombre';
$string['export_lastname'] = 'Apellido';
$string['export_email'] = 'Email';
$string['export_heading'] = 'Encabezado de la publicación';
$string['export_content'] = 'Texto de la publicación';
$string['export_info'] = 'Título de la publicación';
$string['export_url'] = 'URL de la publicación';
$string['export_timecreated'] = 'Fecha de creación';
$string['background_color'] = 'Color de fondo';
$string['background_color_help'] = 'Deveria ser un color hex válido, como #00cc99';
$string['background_image'] = 'Imagen de fondo';

$string['event_add_column'] = 'Columna agregada';
$string['event_add_column_desc'] = 'Usuario con la id \'{$a->userid}\' creó una columna de tablero con id \'{$a->objectid}\' y nombre \'{$a->name}\'.';
$string['event_update_column'] = 'Columna actualizada';
$string['event_update_column_desc'] = 'El usuario con el id \'{$a->userid}\' actualizó tablero column with id \'{$a->objectid}\' a \'{$a->name}\'.';
$string['event_delete_column'] = 'Column borrada';
$string['event_delete_column_desc'] = 'Usuario con el id \'{$a->userid}\' borrado tablero columna con id \'{$a->objectid}\'.';
$string['event_add_comment'] = 'Comentario agregado';
$string['event_add_comment_desc'] = 'Usuario con el id \'{$a->userid}\' agregó a comentario con id \'{$a->objectid}\', contenido \'{$a->content}\' con id de nota \'{$a->noteid}\'.';
$string['event_add_note'] = 'Publicación agregada';
$string['event_add_note_desc'] = 'Usuario con el id \'{$a->userid}\' creó una publicación de tablero con id \'{$a->objectid}\', encabezado \'{$a->heading}\', contenido \'{$a->content}\', media \'{$a->media}\' con id de columna \'{$a->columnid}\', id de grupo \'{$a->groupid}\'.';
$string['event_update_note'] = 'Publicación actualizada';
$string['event_update_note_desc'] = 'Usuario con el id \'{$a->userid}\' actualizó una publicación de tablero con id \'{$a->objectid}\' a encabezado \'{$a->heading}\', contenido \'{$a->content}\', media \'{$a->media}\' en id de columna \'{$a->columnid}\'.';
$string['event_delete_note'] = 'Publicación borrada';
$string['event_delete_note_desc'] = 'Usuario con el id \'{$a->userid}\' borró una publicación de tablero con id \'{$a->objectid}\' desde columna id \'{$a->columnid}\'.';
$string['event_move_note'] = 'Publicación movida';
$string['event_move_note_desc'] = 'Usuario con el id \'{$a->userid}\' movió una publicación de tablero con id \'{$a->objectid}\' a id de columna \'{$a->columnid}\'.';
$string['event_rate_note'] = 'Publicación calificada';
$string['event_rate_note_desc'] = 'Usuario con el id \'{$a->userid}\' calificó una publicación de tablero con id \'{$a->objectid}\' con una calificación \'{$a->rating}\'.';

$string['groupingid_required'] = 'Un agrupamiento de curso debe ser seleccionado para este modo de grupo.';

$string['aria_newcolumn'] = 'Agregar una nueva columna';
$string['aria_newpost'] = 'Agregar una nueva publicación a la columna {column}';
$string['aria_movecolumn'] = 'Mover columna {column}';
$string['aria_deletecolumn'] = 'Borrar columna {column}';
$string['aria_deletepost'] = 'Borrar publicación {post} desde columna {column}';
$string['aria_movepost'] = 'Move publicación {post}';
$string['aria_editpost'] = 'Editar publicación {post}';
$string['aria_addmedia'] = 'Agregar {type} para la publicación {post} desde columna {column}';
$string['aria_addmedianew'] = 'Agregar {type} para la publicación nueva desde columna {column}';
$string['aria_deleteattachment'] = 'Borrar adjunto para la publicación {post} desde columna {column}';
$string['aria_lockcolumn'] = 'Bloquear columna {column}';
$string['aria_postedit'] = 'Guardar publicación, editar para la publicación {post} desde columna {column}';
$string['aria_canceledit'] = 'Cancelar publicación, editar para la publicación {post} desde columna {column}';
$string['aria_postnew'] = 'Guardar nueva publicación para columna {column}';
$string['aria_cancelnew'] = 'Cancelar nueva publicación para la columna {column}';
$string['aria_choosefilenew'] = 'Seleccione un archivo para la nueva publicación desde columna {column}';
$string['aria_choosefileedit'] = 'Seleccione un archivo para la publicación {post} desde columna {column}';
$string['aria_ratepost'] = 'Califique la publicación {post} desde columna {column}';

$string['addrating'] = 'Calificando publicaciones';
$string['addrating_none'] = 'Deshabilitado';
$string['addrating_students'] = 'Por estudiantes';
$string['addrating_teachers'] = 'Por profesores';
$string['addrating_all'] = 'Por todos';
$string['boardhasnotes'] = 'Este tablero ya tiene publicaciones, cambiar el modo de usuario no está permitido';
$string['ratings'] = 'Calificaciones';
$string['selectuser'] = 'Seleccione un usuario';
$string['selectuserplease'] = 'Por favor seleccione a un usuario';
$string['nopermission'] = 'No tienes permisos para ver este tablero.';
$string['nousers'] = 'Esta actividad de tablero no tiene usuarios inscritos';
$string['singleusermode'] = ' modo un sólo usuario';
$string['singleusermodenone'] = 'Deshabilitado';
$string['singleusermodeprivate'] = 'modo de un sólo usuario  (privado)';
$string['singleusermodepublic'] = 'modo de un sólo usuario  (público)';
$string['singleusermode_desc'] = 'En modo de un solo usuario, los usuarios sólo pueden agregar una publicación en su propio tablero, si esta en privado, los usuarios no pueden ver los tableros de otros usuarios, si está en público, los tableros de usuario están disponibles en una lista desplegable.';
$string['sortby'] = 'Ordenado por';
$string['sortbydate'] = 'Fecha de creación';
$string['sortbyrating'] = 'Calificación';
$string['sortbynone'] = 'Ninguno';

$string['postbydate'] = 'Publicación por fecha';
$string['boardsettings'] = 'Configuración de Tablero';
$string['postbyenabled'] = 'Límite de estudiantes publicando por fecha';
$string['userscanedit'] = 'Permite a todos los usuarios editar  el lugar de sus propias publicaciones.';
$string['embedboard'] = 'Incruste el tablero dentro de la página del curso';

$string['invalid_youtube_url'] = 'URL de YouTube inválida';

$string['privacy:metadata:board_comments'] = 'Comentarios por cada publicación de tablero .';
$string['privacy:metadata:board_comments:content'] = 'El contenido del comentario en la publicación';
$string['privacy:metadata:board_comments:noteid'] = 'El ID de la publicación relacionada';
$string['privacy:metadata:board_comments:timecreated'] = 'El tiempo cuando el comentario de la publicación fué creado';
$string['privacy:metadata:board_comments:userid'] = 'El ID del usuario que agregó el comentario en la publicación';
$string['privacy:metadata:board_notes'] = 'Información acerca de las publicaciones individuales por cada tablero.';
$string['privacy:metadata:board_notes:columnid'] = 'La columna locación de la publicación';
$string['privacy:metadata:board_notes:content'] = 'El contenido del publicación';
$string['privacy:metadata:board_notes:heading'] = 'El encabezado del publicación';
$string['privacy:metadata:board_notes:info'] = 'La infomracion de los medios de la publicación';
$string['privacy:metadata:board_notes:timecreated'] = 'El tiempo cuando la publicación fue creada';
$string['privacy:metadata:board_notes:url'] = 'El media URL del publicación';
$string['privacy:metadata:board_notes:userid'] = 'El ID del usuario que creó the publicación';
$string['privacy:metadata:board_history'] = 'Temporalmente la historia del tablero registra información, usada por procesos de javascript para refrescar las vistas del tablero, y luego borrarla inmediatamente.';
$string['privacy:metadata:board_history:action'] = 'El acción completada';
$string['privacy:metadata:board_history:boardid'] = 'El ID del tablero';
$string['privacy:metadata:board_history:content'] = 'Los datos de JSON de la acción ejecutada';
$string['privacy:metadata:board_history:timecreated'] = 'El tiempo cuando acción fué ejecutada';
$string['privacy:metadata:board_history:userid'] = 'El ID del usuario que ejecutó la acción';
$string['privacy:metadata:board_note_ratings'] = 'Información acerca de la calificaciones individuales para cada publicación de tablero .';
$string['privacy:metadata:board_note_ratings:noteid'] = 'El ID de la publicación relacionada';
$string['privacy:metadata:board_note_ratings:timecreated'] = 'El tiempo cuando la calificación de la publicación fué creada';
$string['privacy:metadata:board_note_ratings:userid'] = 'El ID del usuario que creó la calificación de la publicación';

$string['deletecomment'] = 'Borrar comentario';
$string['comments'] = '{$a} Comentarios';
$string['comment'] = 'Comentario';
$string['addcomment'] = 'Agregar comentario';

$string['move_to_firstitemcolumn'] = 'Mover a la columna {$a}';
$string['move_to_afterpost'] = 'Mover después de la publicación {$a}';
$string['move_column_to_firstplace'] = 'Mover columna al primer lugar';
$string['move_column_to_aftercolumn'] = 'Mover columna después de la columna {$a}';

$string['opensinnewwindow'] = 'Abre en una nueva ventana';
$string['brickfieldlogo'] = 'Potenciado por el logo Brickfield';
$string['singleusermodenotembed'] = 'El Tablero no permite que un tablero de un sólo usuario pueda ser incrustado. Por favor cambia tus configuraciónes.';
