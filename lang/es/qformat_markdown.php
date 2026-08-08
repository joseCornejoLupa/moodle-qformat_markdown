<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Spanish strings for qformat_markdown.
 *
 * @package    qformat_markdown
 * @copyright  2026 José Cornejo <jose.cornejo.lupa@gmail.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['noquestionsfound'] = 'No se encontraron preguntas en el archivo. Verifica que cada pregunta empiece con "## " y esté seguida de al menos dos opciones "- [ ]", una o más respuestas "= ", o una o más respuestas numéricas "=# ".';
$string['pluginname'] = 'Formato Markdown';
$string['pluginname_help'] = 'Importa preguntas escritas en una sintaxis simple de Markdown: cada pregunta es un encabezado "## " seguido de opciones "- [ ]" / "- [x]" (opción múltiple o verdadero/falso), líneas "= " (respuesta corta), o líneas "=# " (numérica). Una línea opcional "> " agrega retroalimentación general. Un bloque opcional "---" al inicio del archivo puede definir "category" y/o "defaultmark" para todas las preguntas.';
$string['privacy:metadata'] = 'El plugin de formato de preguntas Markdown no almacena ningún dato personal.';
