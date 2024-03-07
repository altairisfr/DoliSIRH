<?php
/* Copyright (C) 2023 EVARISK <dev@evarisk.com>
 * Copyright (C) 2022 Florian HENRY <florian.henry@scopen.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *   	\file       view/timespent_list.php
 *		\ingroup    dolisirh
 *		\brief      List page for timespent
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
if (!empty($conf->categorie->enabled)) {
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcategory.class.php';
	require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
}

// Load translation files required by the page
$langs->loadLangs(array("projects", "other", "salaries"));

// Get parameters
$action      = GETPOST('action', 'aZ09') ? GETPOST('action', 'aZ09') : 'view'; // The action 'add', 'create', 'edit', 'update', 'view', ...
$massaction  = GETPOST('massaction', 'alpha'); // The bulk action (combo box choice into lists)
$show_files  = GETPOST('show_files', 'int'); // Show files area generated by bulk actions ?
$confirm     = GETPOST('confirm', 'alpha'); // Result of a confirmation
$cancel      = GETPOST('cancel', 'alpha'); // We click on a Cancel button
$toselect    = GETPOST('toselect', 'array'); // Array of ids of elements selected into a list
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'booklist'; // To manage different context of search
$backtopage  = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page
$optioncss   = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')

if (!empty($conf->categorie->enabled)) {
	$search_category_array = GETPOST("search_category_".Categorie::TYPE_PROJECT."_list", "array");
}

$id = GETPOST('id', 'int');

// Load variable for pagination
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	// If $page is not defined, or '' or -1 or if we click on clear filters
	$page = 0;
}
$offset   = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

$hookmanager->initHooks(array('timespentlist')); // Note that conf->hooks_modules contains array

$versionEighteenOrMore = 0;
if ((float) DOL_VERSION >= 18.0) {
    $versionEighteenOrMore = 1;
}

$arrayfields = array(
	'rowid'          => array('tablealias' => 's.', 'fieldalias' => 'socid', 'type' => 'Societe:societe/class/societe.class.php:1:status=1 AND entity IN (__SHARED_ENTITIES__)', 'label' => "Customer", 'checked' => 1, 'position' => 10,  'visible' => 1),
	'ref'            => array('tablealias' => 'p.', 'fieldalias' => 'projectref', 'type' => 'Project:projet/class/project.class.php:1', 'label' => "Project", 'checked' => 1, 'position' => 20, 'visible' => 1),
	'p.rowid'        => array('fieldalias' => 'projectid', 'type'=> 'text', 'label' => "ProjectId", 'visible' => 0),
	'pt.rowid'       => array('fieldalias' => 'taskid', 'type' => 'Task:projet/class/task.class.php:1', 'label' => "Task", 'checked' => 1, 'position' => 30, 'visible' => 1),
	'label'          => array('tablealias' => 'pt.', 'type' => 'text', 'label' => "Label", 'checked' => 1, 'position' => 40, 'visible' => 1),
	'task_date'      => array('tablealias' => 'ptt.', 'type' => 'datetime', 'label' => "Date", 'checked' => 1, 'position' => 50,  'visible' => 1),
	'fk_user'        => array('tablealias' => 'ptt.', 'fieldalias' => 'fk_user', 'type' => 'User:user/class/user.class.php:1:(t.employee:=:1) AND (t.fk_soc:IS:NULL) AND (statut:=:1)', 'label' => "User", 'checked' => 1, 'position' => 60, 'visible' => 1),
	'task_duration'  => array('tablealias' => 'ptt.', 'type' => 'duration', 'label' => "Duration",  'checked' => 1, 'position' => 70, 'visible' => 1, 'isameasure'=>1),
	'note'           => array('tablealias' => 'ptt.', 'type' => 'text', 'label' => "Note",  'checked' => 1, 'position' => 80, 'visible' => 1),
	'thm'            => array('tablealias' => 'ptt.', 'type' => 'price', 'label' => "Value",  'checked' => 1, 'position' => 90, 'visible' => 1, 'isameasure'=>1),
	'invoice_id'     => array('tablealias' => 'ptt.', 'fieldalias' => 'invoice_id', 'type' => 'Facture:compta/facture/class/facture.class.php:1', 'label' => "Facture", 'checked' => 1, 'position' => 100, 'visible' => 1),
	'ts.rowid'       => array('fieldalias' => 'timesheetid', 'type' => 'TimeSheet:custom/dolisirh/class/timesheet.class.php:1', 'label' => "TimeSheet", 'checked' => 1, 'position' => 110, 'visible' => 1),
);

// Default sort order (if not yet defined by previous GETPOST)
if (!$sortfield) {
    if ($versionEighteenOrMore) {
        $sortfield = 'ptt.element_date'; // Set here default search field. By default 1st field in definition.
    } else {
        $sortfield = 'ptt.task_date'; // Set here default search field. By default 1st field in definition.
    }
}
if (!$sortorder) {
	$sortorder = "ASC";
}

$arrayfields = dol_sort_array($arrayfields, 'position');

// Initialize array of search criterias
$search_all = GETPOST('search_all', 'alphanohtml');
$search = array();
foreach ($arrayfields as $key => $val) {
	$keysearch = (array_key_exists('fieldalias', $val) ? $val['fieldalias'] : $key);
	if (GETPOST('search_' . $keysearch, 'alpha') !== '' && GETPOST('search_' . $keysearch, 'alpha') !== '-1') {
		$search[$keysearch] = GETPOST('search_' . $keysearch, 'alpha');
	}
	if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
		$search[$keysearch . '_dtstart'] = dol_mktime(0, 0, 0, GETPOST('search_' . $keysearch . '_dtstartmonth', 'int'), GETPOST('search_' . $keysearch . '_dtstartday', 'int'), GETPOST('search_' . $keysearch . '_dtstartyear', 'int'));
		$search[$keysearch . '_dtend'] = dol_mktime(23, 59, 59, GETPOST('search_' . $keysearch . '_dtendmonth', 'int'), GETPOST('search_' . $keysearch . '_dtendday', 'int'), GETPOST('search_' . $keysearch . '_dtendyear', 'int'));
	}
}

$permissiontoread   = $user->rights->projet->lire;
$permissiontoadd    = $user->rights->projet->creer;
$permissiontodelete = $user->rights->projet->supprimer;


// Security check (enable the most restrictive one)
if ($user->socid > 0) accessforbidden();
if (empty($conf->dolisirh->enabled)) accessforbidden('Module not enabled');

/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	$action = 'list';
	$massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
	$massaction = '';
}

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Selection of new fields
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
		foreach ($arrayfields as $key => $val) {
			$keysearch = (array_key_exists('fieldalias', $val) ? $val['fieldalias'] : $key);
			$search[$keysearch] = '';
			if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
				$search[$keysearch . '_dtstart'] = '';
				$search[$keysearch . '_dtend'] = '';
			}
		}
		$toselect = array();
		$search_array_options = array();
		$search_category_array = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = ''; // Protection to avoid mass action if we force a new search during a mass action confirmation
	}

	// Mass actions
	$objectclass = 'Task';
	$objectlabel = 'Task';
	$uploaddir = $conf->dolisirh->dir_output;
	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';
}


/*
 * View
 */

$form = new Form($db);

$now = dol_now();

$help_url = 'FR:Module_DoliSIRH';
$title    = $langs->trans($langs->transnoentitiesnoconv("TimeSpentList"));
$morejs   = array();
$morecss  = array();

// Build and execute select
// --------------------------------------------------------------------
$sql = 'SELECT ';
$sql .= 'DISTINCT ';
$sqlfields = array();
foreach ($arrayfields as $field => $data) {
	$sqlfields[] = $data['tablealias'] . $field . ((array_key_exists('fieldalias', $data) ? ' as ' . $data['fieldalias'] : ''));
}
$sql .= implode(',', $sqlfields);

// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters, $object); // Note that $action and $object may have been modified by hook
$sql .= preg_replace('/^,/', '', $hookmanager->resPrint);
$sql = preg_replace('/,\s*$/', '', $sql);
$sql .= ' FROM ' . MAIN_DB_PREFIX . 'projet as p';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet_extrafields as extra ON p.rowid = extra.fk_object';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_lead_status as cls ON p.fk_opp_status = cls.rowid';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet_task as pt ON p.rowid = pt.fk_projet';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet_task_extrafields as ef ON pt.rowid = ef.fk_object';
if ($versionEighteenOrMore) {
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'element_time as ptt ON (ptt.fk_element = t.rowid AND ptt.elementtype = "task")';
} else {
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet_task_time as ptt ON pt.rowid = ptt.fk_task';
}
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON p.fk_soc = s.rowid';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'facture as f ON ptt.invoice_id = f.rowid';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'element_element as ee on ( ee.sourcetype = "dolisirh_timesheet" AND ee.targettype = "project_task_time" AND ee.fk_target = ptt.rowid)';
$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'dolisirh_timesheet as ts ON ee.fk_source = ts.rowid';

// Add table from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListFrom', $parameters, $object); // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;
if ($object->ismultientitymanaged == 1) {
	$sql .= " WHERE p.entity IN (" . getEntity($object->element) . ")";
} else {
	$sql .= " WHERE 1 = 1";
}
if ($versionEighteenOrMore) {
    $sql .= ' AND ptt.element_duration IS NOT NULL';
} else {
    $sql .= ' AND ptt.task_duration IS NOT NULL';
}

if (GETPOST('custonly')) $sql .= ' AND s.rowid IS NOT NULL';

foreach ($search as $key => $val) {
	if (preg_match('/(_dtstart|_dtend)$/', $key) && $search[$key] != '') {
		if (preg_match('/_dtstart$/', $key)) {
            if ($versionEighteenOrMore) {
                $sql .= " AND ptt.element_date >= '" . $db->idate($search[$key]) . "'";
            } else {
                $sql .= " AND ptt.task_date >= '" . $db->idate($search[$key]) . "'";
            }
		}
		if (preg_match('/_dtend$/', $key)) {
            if ($versionEighteenOrMore) {
                $sql .= " AND ptt.element_date <= '" . $db->idate($search[$key]) . "'";
            } else {
                $sql .= " AND ptt.task_date <= '" . $db->idate($search[$key]) . "'";
            }
		}
	}
	if ($key == 'socid' && !empty($val)) $sql .= ' AND s.rowid=' . (int) $val;
	if ($key == 'projectref' && !empty($val)) $sql .= ' AND p.rowid=' . (int) $val;
	if ($key == 'taskid' && !empty($val)) $sql .= ' AND pt.rowid=' . (int) $val;
	if ($key == 'fk_user' && !empty($val)) $sql .= ' AND ptt.fk_user=' . (int) $val;
	if ($key == 'invoice_id' && !empty($val)) $sql .= ' AND ptt.invoice_id=' . (int) $val;
	if ($key == 'timesheetid' && !empty($val)) $sql .= ' AND ts.rowid=' . (int) $val;
}

if (!empty($conf->categorie->enabled)) {
	$sql .= Categorie::getFilterSelectQuery(Categorie::TYPE_PROJECT, "p.rowid", $search_category_array);
}

if ($search_all) {
	$sql .= natural_search(array_keys($fieldstosearchall), $search_all);
}
// Add where from extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_sql.tpl.php';
// Add where from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters, $object); // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;

// Count total nb of records
$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
	$sqlforcount = preg_replace('/^SELECT[a-z0-9\._\s\(\),]+FROM/i', 'SELECT COUNT(*) as nbtotalofrecords FROM', $sql);
	$resql = $db->query($sqlforcount);
	$objforcount = $db->fetch_object($resql);
	$nbtotalofrecords = $objforcount->nbtotalofrecords;
	if (($page * $limit) > $nbtotalofrecords) {    // if total of record found is smaller than page * limit, goto and load page 0
		$page = 0;
		$offset = 0;
	}
	$db->free($resql);
}

// Complete request and execute it with limit
$sql .= $db->order($sortfield, $sortorder);
if ($limit) {
	$sql .= $db->plimit($limit + 1, $offset);
}

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}

$num = $db->num_rows($resql);

// Output page
// --------------------------------------------------------------------

llxHeader('', $title, $help_url, '', 0, 0, $morejs, $morecss, '', '');

$arrayofselected = is_array($toselect) ? $toselect : array();

$param = '';
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
	$param .= '&contextpage=' . urlencode($contextpage);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit=' . urlencode($limit);
}
foreach ($search as $key => $val) {
	if (is_array($search[$key]) && count($search[$key])) {
		foreach ($search[$key] as $skey) {
			if ($skey != '') {
				$param .= '&search_' . $key . '[]=' . urlencode($skey);
			}
		}
	} elseif ($search[$key] != '') {
		$param .= '&search_' . $key . '=' . urlencode($search[$key]);
	}
}
if ($optioncss != '') {
	$param .= '&optioncss=' . urlencode($optioncss);
}
// Add $param from extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_param.tpl.php';
// Add $param from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSearchParam', $parameters, $object); // Note that $action and $object may have been modified by hook
$param .= $hookmanager->resPrint;

// List of mass actions available
$arrayofmassactions = array();
if ($permissiontodelete) {
	$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("Delete");
}
if (GETPOST('nomassaction', 'int') || in_array($massaction, array('predelete'))) {
	$arrayofmassactions = array();
}
//$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

print '<form method="POST" id="searchFormList" action="' . $_SERVER["PHP_SELF"] . '">' . "\n";
if ($optioncss != '') {
	print '<input type="hidden" name="optioncss" value="' . $optioncss . '">';
}
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="' . $sortfield . '">';
print '<input type="hidden" name="sortorder" value="' . $sortorder . '">';
print '<input type="hidden" name="page" value="' . $page . '">';
print '<input type="hidden" name="contextpage" value="' . $contextpage . '">';

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'clock', 0, 0, '', $limit, 0, 0, 1);

include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

if ($search_all) {
	$setupstring = '';
	foreach ($fieldstosearchall as $key => $val) {
		$fieldstosearchall[$key] = $langs->trans($val);
		$setupstring .= $key."=".$val.";";
	}
	print '<!-- Search done like if PRODUCT_QUICKSEARCH_ON_FIELDS = '.$setupstring.' -->'."\n";
	print '<div class="divsearchfieldfilter">'.$langs->trans("FilterOnInto", $search_all).join(', ', $fieldstosearchall).'</div>'."\n";
}

$moreforfilter = '';
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters, $object); // Note that $action and $object may have been modified by hook
if (empty($reshook)) {
	$moreforfilter .= $hookmanager->resPrint;
} else {
	$moreforfilter = $hookmanager->resPrint;
}

// Filter on categories
if (!empty($conf->categorie->enabled) && $user->rights->categorie->lire) {
	$formcategory = new FormCategory($db);
	$moreforfilter .= $formcategory->getFilterBox(Categorie::TYPE_PROJECT, $search_category_array);
}

$moreforfilter .= 'Client seulement : <input type="checkbox" name="custonly"' . (GETPOST('custonly') ? ' checked=checked' : ''). '>';

if (!empty($moreforfilter)) {
	print '<div class="liste_titre liste_titre_bydiv centpercent">';
	print $moreforfilter;
	print '</div>';
}

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields
$selectedfields .= (is_array($arrayofmassactions) && count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="tagtable nobottomiftotal liste' . ($moreforfilter ? " listwithfilterbefore" : "") . '">' . "\n";


// --------------------------------------------------------------------
print '<tr class="liste_titre">';
foreach ($arrayfields as $key => $val) {
	$keysearch = (array_key_exists('fieldalias', $val) ? $val['fieldalias'] : $key);
	$cssforfield = (empty($val['csslist']) ? (empty($val['css']) ? '' : $val['css']) : $val['csslist']);
	if ($key == 'status') {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	} elseif (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
		$cssforfield .= ($cssforfield ? ' ' : '').'center';
	} elseif (in_array($val['type'], array('timestamp'))) {
		$cssforfield .= ($cssforfield ? ' ' : '').'nowrap';
	} elseif (in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && $val['label'] != 'TechnicalID' && empty($val['arrayofkeyval'])) {
		$cssforfield .= ($cssforfield ? ' ' : '').'right';
	}
	if (!empty($arrayfields[$key]['checked'])) {
		print '<td class="liste_titre' . ($cssforfield ? ' ' . $cssforfield : '') . '">';
		if ($val['fieldalias'] == 'fk_user') {
			print $form->select_dolusers(($search[$keysearch] > 0 ? $search[$keysearch] : -1), 'search_fk_user', 1, null, 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth250');
		} elseif (in_array($val['fieldalias'], array('socid','projectref', 'taskid', 'invoice_id', 'timesheetid'))) {
			print $form->selectForForms($val['type'], 'search_' . $keysearch, $search[$keysearch], 1, '', '', $morecss);
		} elseif (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
			print '<div class="nowrap">';
			print $form->selectDate($search[$key . '_dtstart'], "search_" . $key . "_dtstart", 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
			print '</div>';
			print '<div class="nowrap">';
			print $form->selectDate($search[$key . '_dtend'], "search_" . $key . "_dtend", 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
			print '</div>';
		}
		print '</td>';
	}
}
// Extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_input.tpl.php';

// Fields from hook
$parameters = array('arrayfields' => $arrayfields);
$reshook = $hookmanager->executeHooks('printFieldListOption', $parameters, $object); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;
// Action column
print '<td class="liste_titre maxwidthsearch">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';
print '</tr>' . "\n";

// Fields title label
// --------------------------------------------------------------------
print '<tr class="liste_titre">';
foreach ($arrayfields as $key => $val) {
	$cssforfield = (empty($val['csslist']) ? (empty($val['css']) ? '' : $val['css']) : $val['csslist']);
	if ($key == 'status') {
		$cssforfield .= ($cssforfield ? ' ' : '') . 'center';
	} elseif (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
		$cssforfield .= ($cssforfield ? ' ' : '') . 'center';
	} elseif (in_array($val['type'], array('timestamp'))) {
		$cssforfield .= ($cssforfield ? ' ' : '') . 'nowrap';
	} elseif (in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real',
										   'price')) && $val['label'] != 'TechnicalID' && empty($val['arrayofkeyval'])) {
		$cssforfield .= ($cssforfield ? ' ' : '') . 'right';
	}
	if (!empty($arrayfields[$key]['checked'])) {
		print getTitleFieldOfList($arrayfields[$key]['label'], 0, $_SERVER['PHP_SELF'], $val['tablealias'] . $key, '', $param, ($cssforfield ? 'class="' . $cssforfield . '"' : ''), $sortfield, $sortorder, ($cssforfield ? $cssforfield . ' ' : '')) . "\n";
	}
}
// Extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_title.tpl.php';
// Hook fields
$parameters = array('arrayfields' => $arrayfields, 'param' => $param, 'sortfield' => $sortfield, 'sortorder'   => $sortorder);
$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters, $object); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;
// Action column
print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ') . "\n";
print '</tr>' . "\n";

// Loop on record
// --------------------------------------------------------------------
$i = 0;
$totalarray = array();
$totalarray['nbfield'] = 0;
while ($i < ($limit ? min($num, $limit) : $num)) {
	$obj = $db->fetch_object($resql);
	if (empty($obj)) {
		break; // Should not happen
	}

	// Show here line of result
	print '<tr class="oddeven">';
	foreach ($arrayfields as $key => $val) {
		if ($val['visible'] == 1) {
			$cssforfield = (empty($val['csslist']) ? (empty($val['css']) ? '' : $val['css']) : $val['csslist']);
			if (in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
				$cssforfield .= ($cssforfield ? ' ' : '').'center';
			} elseif ($key == 'status') {
				$cssforfield .= ($cssforfield ? ' ' : '').'center';
			}

			if (in_array($val['type'], array('timestamp'))) {
				$cssforfield .= ($cssforfield ? ' ' : '').'nowrap';
			} elseif ($key == 'ref') {
				$cssforfield .= ($cssforfield ? ' ' : '').'nowrap';
			}

			if (in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && !in_array($key, array('rowid', 'status')) && empty($val['arrayofkeyval'])) {
				$cssforfield .= ($cssforfield ? ' ' : '').'right';
			}

			if (!empty($arrayfields[$key]['checked'])) {
				print '<td' . ($cssforfield ? ' class="' . $cssforfield . '"' : '') . '>';
				if (in_array($val['fieldalias'], array('socid', 'projectref', 'fk_user', 'taskid', 'invoice_id', 'timesheetid'))) {
					$InfoFieldList = explode(':', $val['type']);
					$classname = $InfoFieldList[0];
					$classpath = $InfoFieldList[1];
					if (!empty($classpath)) {
						dol_include_once($InfoFieldList[1]);
						if ($classname && class_exists($classname)) {
							$object = new $classname($db);
							if ($val['fieldalias'] == 'projectref') {
								$object->fetch($obj->projectid);
								print $object->getNomUrl(1, '', 1);
							} else {
								$object->fetch($obj->{$val['fieldalias']});
								if (!empty($obj->{$val['fieldalias']})) {
									print $object->getNomUrl(1);
								}
							}
						}
					}
				} elseif ($key == 'task_date') {
					print dol_print_date($obj->task_date, 'day');
				} elseif ($key == 'title' || $key === 'label' || $key == 'note') {
					print $obj->{$key};
				} elseif ($key == 'task_duration') {
					print convertSecondToTime($obj->task_duration, 'allhourmin');
					$totalarray['type'][$i]='duration';
				} elseif ($key == 'thm') {
					$value = price2num($obj->thm * $obj->task_duration / 3600, 'MT', 1);
					print '<span class="amount" title="'.$langs->trans("THM").': '.price($obj->thm).'">';
					print price($value, 1, $langs, 1, -1, -1, $conf->currency);
				}
				print '</td>';
				if (!$i) {
					$totalarray['nbfield']++;
				}
				if (!empty($val['isameasure']) && $val['isameasure'] == 1) {
					if (!$i) {
						$totalarray['pos'][$totalarray['nbfield']] = $key;
						if ($key == 'task_duration') {
							$totalarray['type'][$totalarray['nbfield']]='duration';
						}
					}
					if (!isset($totalarray['val'])) {
						$totalarray['val'] = array();
					}
					if (!isset($totalarray['val'][$key])) {
						$totalarray['val'][$key] = 0;
					}
					if ($key == 'thm') {
						$totalarray['val'][$key] += $value;
					} else {
                        $totalarray['val'][$key] += $obj->{$key};
                    }
                    if (!$i) {
                        $totalarray['total'.$key] = $totalarray['nbfield'];
                    }
				}
			}
		}
	}
	// Fields from hook
	$parameters = array('arrayfields' => $arrayfields, 'object' => $object, 'obj' => $obj, 'i' => $i, 'totalarray'  => &$totalarray);
	$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters, $object); // Note that $action and $object may have been modified by hook
	// Action column
	print '<td class="nowrap center">';
	if ($massactionbutton || $massaction) { // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
		$selected = 0;
		if (in_array($object->id, $arrayofselected)) {
			$selected = 1;
		}
		print '<input id="cb'.$object->id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$object->id.'"'.($selected ? ' checked="checked"' : '').'>';
	}
	print '</td>';
	print $hookmanager->resPrint;
	if (!$i) {
		$totalarray['nbfield']++;
	}
	print '<td></td>';
	print '</tr>' . "\n";

	$i++;
}

// Show total line
print '<tr class="liste_total">';
$i = 0;
while ($i < $totalarray['nbfield']) {
    $i++;
    if ($i == 1) {
        if ($num < $limit && empty($offset)) {
            print '<td class="left">'.$langs->trans("Total").'</td>';
        } else {
            print '<td class="left">'.$langs->trans("Totalforthispage").'</td>';
        }
    } elseif ($totalarray['totaltask_duration'] == $i) {
        print '<td class="left">'.convertSecondToTime($totalarray['val']['task_duration'], 'allhourmin').'</td>';
    } elseif ($totalarray['totalthm'] == $i) {
        print '<td class="right">'.price($totalarray['val']['thm']).'</td>';
    } else {
        print '<td></td>';
    }
}
print '</tr>';

// If no record found
if ($num == 0) {
	$colspan = 1;
	foreach ($arrayfields as $key => $val) {
		if (!empty($val['checked'])) {
			$colspan++;
		}
	}
	print '<tr><td colspan="' . $colspan . '"><span class="opacitymedium">' . $langs->trans("NoRecordFound") . '</span></td></tr>';
}

$db->free($resql);

$parameters = array('arrayfields' => $arrayfields, 'sql' => $sql);
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters, $object); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

print '</table>' . "\n";
print '</div>' . "\n";

print '</form>' . "\n";

// End of page
llxFooter();
$db->close();
