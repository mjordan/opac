<?php


// To do:
// Create config options to disable features not supported in targets (scan, sorting, date searching, trunction, etc.)
// Fix occasional "Connection lost" and timeout errors

$last_mod = '2007-01-09';

/* 
Exit if script can't find the yaz module
*/
if (!extension_loaded('yaz')) {
       print "Sorry, 'yaz.so' isn't loaded....";
       exit;
}

/*
To do: Set up supported function flags, such as truncation, sort, etc. and add them to config 
settings. Then, add logic so that only supported features show up in interface. We do this now for scan.
Question: do this on a target or platform basis? Maybe target is better, since features supported by a platform
may not be supported or configured locally.
*/

/*
Define yaz config settings
*/
$config_settings = array('title' => 'SFU Library Catalogue',
	'yaz_connect_string' => 'troy.lib.sfu.ca:210/innopac',
	'yaz_record_syntax' => 'opac',
	'yaz_max_records' => '1000',
	'z3950_scan' => 1 
	
);

$__config_settings = array('title' => 'U of T Library Catalogue',
	'yaz_connect_string' => 'sirsi.library.utoronto.ca:2200/UNICORN',
	'yaz_record_syntax' => 'marc21',
	'yaz_max_records' => '1000',
	'z3950_scan' => 1 
);

$_config_settings = array('title' => 'U Vic  Library Catalogue',
	'yaz_connect_string' => 'voyager.library.uvic.ca:7090/voyager',
	'yaz_record_syntax' => 'marc21',
	'yaz_max_records' => '1000',
	'z3950_scan' => 0 
);

/*
Define log file settings. Applies to function write_log ($content) 
*/
$enable_log = 0;
$log_file = '/tmp/opac.log';

/*
Get pager values, set defaults. 
*/
$page_size = 20; // The number of hits to appear on each page of search results
$pager_range = 5; // The number of "before" and "after" links to appear in pager
$page = (empty($_GET['page'])) ? '1' : $_GET['page'];

/*
Print HTML, with query preserved in form 
*/
// Get script's name
$self = $_SERVER["SCRIPT_NAME"];
$opac_function = $_REQUEST['opac_function'];

// Adding the UTF-8 encoding to the XML declaration ensures that diacritics are displayed properlyA
header('Content-type: text/html; charset=UTF-8') ;
print '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">' ."\n";
print '<meta http-equiv="Content-type" value="text/html; charset=UTF-8" />';
print '<title>Test OPAC ' . $config_settings['title'] . '</title>' . "\n";
print '<link rel="stylesheet" type="text/css" href="opac.css" />' . "\n";
print '<script language="JavaScript" type="text/javascript" src="opac.js"></script>' . "\n";
print "</head>\n";
print "<body>\n";
print '<div class="warning">Z39.50 interface to the ' . $config_settings['title'] . '</div><div class="last_mod">Last modified ' . $last_mod . "</div>\n";


print '<form name="query_form" action="' . $self . '" method="GET">';
if ($config_settings['z3950_scan']) {
   print '<select name="opac_function" onchange="toggle_add_clear_boolean();">';
   print '<option ';  if ($opac_function == 'Find') { print ' selected '; } print ' value="Find">Find</option>';
   print '<option ';  if ($opac_function == 'Browse') { print ' selected '; } print ' value="Browse">Browse for</option>';
   print '</select>';
} else {
   print '<input type="hidden" name="opac_function" value="Find" />';
   print 'Find ';
}


print ' <input type="text" name="keywords"';
if (isset($_GET['keywords'])) {
   print ' value="' . $_GET['keywords'];
}
print '" />';
print " in ";

$fields = array('1016' => 'any field', // keywords
		'4' => 'title',
		'1003' => 'author',
		'21' => 'subject',
		'8' => 'isbn',
		'7' => 'issn'
);

print '<select name="field">';
foreach($fields as $value => $label) {             
  print '<option';
  if ($_GET['field'] == $value) { print ' selected '; }
  print ' value="' . $value . '">' . $label . '</option>' . "\n";
}
print '</select>';

if ($opac_function == 'Browse') {
   $add_clear_boolean_display = 'none';
} else {
   $add_clear_boolean_display = 'inline';
}

print ' <input type="submit" name="go" value="Go" /> <span style="display: ' . $add_clear_boolean_display . '" id="add_clear_keywords"><a href="#" onclick="toggle_vis(' . "'query2'" . '); clear_keywords2();">+/-</a></span>';

print '<span style="margin-left: 30px"><a href="' . $self . '">Start over</a></span>';

// Second part of form (containing query2 elements)
if ($_GET['keywords2'] == '') {
   print '<div id="query2" style="display: none">';
} else {
   print '<div id="query2" style="display: block">';
}
print '<select name="boolean_op">';
print '<option ';  if ($_GET['boolean_op'] == 'and') { print ' selected '; } print ' value="and">and&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</option>';
print '<option ';  if ($_GET['boolean_op'] == 'or') { print ' selected '; } print ' value="or">or</option>';
print '<option ';  if ($_GET['boolean_op'] == 'not') { print ' selected '; } print ' value="not">not</option>';
print '</select>';

print ' <input type="text" name="keywords2"';
if (isset($_GET['keywords2'])) {
   print ' value="' . $_GET['keywords2'];
}
print '" />';
print " in ";

print '<select name="field2">';
foreach($fields as $value => $label) {             
  print '<option';
  if ($_GET['field2'] == $value) { print ' selected '; }
  print ' value="' . $value . '">' . $label . '</option>' . "\n";
}
print '</select>'; 
print '</div>';

print '</form>';

// $history_placeholder = "Search history will go here<p /><ul><li>search 1</li><li>search 2</li><li>search 3</li></ul>";
// print '<div class="history">' . $history_placeholder  . '</div>';



/*
Connect to Z39.50 server
*/
$id = yaz_connect($config_settings['yaz_connect_string']);
yaz_syntax($id, $config_settings['yaz_record_syntax']);

/*
Get form values
*/
$keywords = trim($_GET['keywords']);
$keywords2 = trim($_GET['keywords2']);
$field = $_GET['field'];
$field2 = $_GET['field2'];
$boolean_op = $_GET['boolean_op'];

/*
Select opac_function based on URL parameer, print closing HTML
*/
switch ($opac_function) {
case 'Find':
   opac_find($id, $keywords, $keywords2, $field, $field2, $boolean_op);
   break;
case 'Browse':
   opac_scan($id, $field, $keywords);
   break;
case 'FindFromBrowse':
   opac_find_from_scan($id, $field, $keywords);
   break;
}

print "\n</body>\n</html>";


/********
Functions
********/

/*
Perform find queries
*/
function opac_find ($id, $keywords, $keywords2, $field, $field2, $boolean_op) {
   global $self, $config_settings, $page, $page_size;

   if (($keywords == '') && ($_GET['go'])) {
      print "You must enter a search query<p />";
      return;
   }

   if (!isset($_GET['page'])) {
      $start_list_at = 1;
   } else {
      $start_list_at = ($_GET['page'] - 1) * $page_size + 1;
   }

   /*
   Use
   1=4 : title
   1=21 : subject
   1=1003 : author
   1=1007 : identifier  

   Relation
   2=3 : equal

   Position
   3=1 : first in field
   3=3 : any position

   Structure
   4=1 : phrase
   4=2 : word
   4=101 : normalized

   Trunction
   5=1 : right truncate
   5=100 : do not truncate
   */
 
   // Queries look like '@and @attr 1=4 putting @attr 1=4 content ';
   $query = '@attr 4=1 @attr 1=' . $field . ' "' . $keywords . '"'; 
   if (!$keywords2 == '') {
	$query = '@' . $boolean_op . ' ' . $query . ' ' . '@attr 4=1 @attr 1=' . $field2 . ' "' . $keywords2 . '"';
   }

   /*
    For retrieving records from a scan list, prepend '@attr 5=100' to $query (do not truncate).
    We use debug_backtrace to determine the name of the calling function.
   */
   $backtrace = debug_backtrace();
   $caller = $backtrace[1]['function'];
   if ($caller == 'opac_find_from_scan') {
      $query = '@attr 5=100 ' . $query;
   }

   // Trunction: if * at end of query (in find mode) enable trunction. Not supported by all targets in find mode.
   // Question: how do we encode this in the URL for subsequent paged searches? Escape the * and add it to keywords?
   if (preg_match("/*$/", $query)) {
      $query = '@attr 5=1 ' . $query;
   }

   write_log($query);

   // $sort_criteria = '1=' . $field . ' ia';
   // yaz_sort($id, $sort_criteria);
   yaz_search($id, 'rpn', $query);
   yaz_wait();

   $error = yaz_error($id);
   if (!empty($error)) {
     echo "<p>Error: $error</p>";
     print "\n";
     exit;
   } else {
     // Create XML containing records
     $recs = '';
     $hits = yaz_hits($id);
     if ($hits == '0') {
       print "No hits\n";
       exit;
     }

     $recs .= '<?xml version="1.0"?>' . "\n";
     $recs .= "<result_set>\n";
     $recs .= "<result_count>$hits</result_count>\n";
   }
   
   for ($pos = $start_list_at; $pos <= $start_list_at + $page_size - 1; $pos++) {
     // The character set conversion parameter ensures that diacritics are displayed correctly
     // (but you also need to use UTF-8 in the XML declaration at the top of the web page). 
     $rec = yaz_record($id, $pos, "opac; charset=marc-8,utf-8"); 
     if (empty($rec)) continue;
      // Get rid of the namespace declaration since there is no namespace prefix and that screws up
      // XSL pattern matching.
      // $rec = preg_replace('#\sxmlns="http://www\.loc\.gov/MARC21/slim"#m', '', $rec);
      $rec = mb_ereg_replace('xmlns="http://www.loc.gov/MARC21/slim"', '', $rec);
      // $rec = html_encode($rec);
      $recs = $recs . $rec;
   }

   $recs = $recs . "</result_set>";
   write_log($recs);
   print records2page($recs, $start_list_at);
   print opac_find_pager($hits, $page, $page_size, $keywords, $keywords2, $field, $boolean_op, $field2);

   write_log($next_url);
}


/*
Generates "first", "next", "previous", etc. links in find queries
*/
function opac_find_pager($hits, $page, $page_size, $keywords, $keywords2, $field, $boolean_op, $field2) {
   global $pager_range;
   $keywords = urlencode($keywords);
   $keywords2 = urlencode($keywords2);
   $page_base_url = $self . '?' . 'opac_function=Find' . '&keywords=' . $keywords . '&keywords2=' . $keywords2 . '&field=' . 
	$field . '&boolean_op=' . $oolean_op . '&field2=' . $field2 . '&page=';
   $current_page = $page;
   $last_page = ceil($hits / $page_size);
   for ($page_num = 1; $page_num <= $hits / $page_size; $page_num++) {
       // Build links list before current page
       $before_pager_links_boundary = $current_page - $pager_range;
       if (($page_num < $current_page) && ($page_num > $before_pager_links_boundary)) {
          $page_url = $page_base_url . $page_num;
          $pager_link = '<a href="' . $page_url . '">' . $page_num . '</a> ';
          $before_pager_links .= $pager_link;
       }
       // Build placeholder for current page
       if ($page_num == $page) {
          $current_page_placeholder = $page_num . ' ';
       } 
       // Build link list after current page
       $after_pager_links_boundary = $current_page + $pager_range;
       if (($page_num > $current_page) && ($page_num < $after_pager_links_boundary)) {
          $page_url = $page_base_url . $page_num;
          $pager_link = '<a href="' . $page_url . '">' . $page_num . '</a> ';
          $after_pager_links .= $pager_link;
       }
       // Join them together for printing: First
       $pager_links = '<a href="' . $page_base_url . '1">First</a> ';
       // Previous
       if ($current_page != 1) {
	  $previous_page = $current_page - 1;
          $pager_links .= ' <a href="' . $page_base_url . $previous_page . '">Previous</a> ';
       }
       $pager_links .= $before_pager_links .  $current_page_placeholder;
       $pager_links .= $after_pager_links; 
       // Next
       if ($current_page != $last_page) {
	  $next_page = $current_page + 1;
          $pager_links .= ' <a href="' . $page_base_url . $next_page . '">Next</a> ';
       }
       // Last
       $pager_links .= ' <a href="' . $page_base_url . $last_page . '">Last</a>';

    }
   $pager_string = '<div class="pager">' . $pager_links . '</div>';
   return $pager_string;
}


/*
Preprocesses queries from browse list to pass to opac_find
*/
function opac_find_from_scan($id, $field, $start_term) {
   $start_term = urldecode($start_term);
   opac_find ($id, $start_term, '', $field, '', 'and');
   return;
}

/*
Perform scan (i.e., browse) queries
*/
function opac_scan($id, $field, $start_term) {
  if ($_GET['field'] == '1016') { print "Sorry, you can't browse in 'any field' (try title, author, or subject).<p />"; return; }
  // Set up pager
  global $page_size;
  if (!isset($_GET['page'])) {
      $start_list_at = 0;
  } else {
      $start_list_at = $_GET['page'] * $page_size;
  }
  $next_page = $_GET['page'] + 1; 
  $previous_page = $_GET['page'] - 1; 

  // Construct scan query
  $attribs = '@attr 1=' . $field . ' @attr 4=1 ';
  $start_term = '"' . $start_term . '"'; // Phrases need quotes 
  // If we're coming from a "Next" link get $start_term_pos from URL
  $start_term_pos = (empty($_GET['start_term_pos'])) ?  round($page_size / 2) : $_GET['start_term_pos'];
  // Don't know what stepSize actually does... changing the value doesn't have any effect as far as I can tell
  $scan_flags = array('number' => $page_size, 'position' => $start_term_pos, 'stepSize' => '10'); 
  yaz_scan($id, 'rpn', $attribs . " " . $start_term, $scan_flags);
  yaz_wait();
  $errno = yaz_errno($id);

  if ($errno == 0) {
    $scan_result = yaz_scan_result($id, $result_info);
    print "<table>\n";
    $heading_row = $start_list_at;
    foreach ($scan_result as $entry) {
      $heading_row++;
      // print_r($entry);
      // Pick out the heading from scan result and corresponding number of hits
      list($heading, $count) = explode('^', $entry[3]);
      $find_query = trim($heading);
      $find_query = urlencode($heading);
      /*
      We need to check the length of the headings in the scan list, since some come back from the server truncated.
      This means that when we do an exact match phrase searh, 0 hits are returned. Workaround: do an exact phrase
      search on shorter headings but do a keyword search on long ones (more than 50 chars).
     */
      if (strlen($heading) < 50) {
         $scan_retrieve_url = $self . '?' . 'opac_function=FindFromBrowse' . '&keywords=' . $find_query . '&field=' . $field;
      } else {
         $scan_retrieve_url = $self . '?' . 'opac_function=Find' . '&keywords=' . $find_query . '&field=' . $field;
      }

      // Hilight heading in list
      $toggle_highlighter = 1;  // We only want the class="scan_term_highlighter" to be active in one table row
      if ($heading_row == $start_term_pos) {
         $start_term = trim($start_term, '"');
         // If term heading is not an exact match, tell user
         if (!preg_match("/^$start_term$/i", $heading)) {
	     $toggle_highlighter = 0; // We've already used class="scan_term_highlighter", can't use it again
             print '<tr><td></td><td>&nbsp</td><td class="scan_term_highlighter">Your term (' . $start_term . ') would be here</td><td></td></tr>' . "\n";
         }
         print '<tr><td>' . $heading_row . '</td><td></td><td '; 
	 if ($toggle_highlighter) { // We haven't used class="scan_term_highlighter" so print it
    	    if (empty($_GET['start_term_pos'])) { // But only if we don't come from a "Next" URL
	       print 'class="scan_term_highlighter" '; 
	    }
	 } 
	 print '><a href="' . $scan_retrieve_url  . '">' . $heading . "</td><td>$count</td></tr>\n";
      } else {
         print '<tr><td>' . $heading_row . '</td><td>&nbsp</td><td><a href="' . $scan_retrieve_url  . '">' . $heading . "</td><td>$count</td></tr>\n";
      }
   }
   print '</table>';
   // Use start_term_pos = 1 since we want next heading at top of subsequent page
   print '<div class="pager">';
   if ($_GET['page'] >= 1) {
      // TO DO: Fix backward link
      $backward_scan_retrieve_url = $self . '?opac_function=Browse' . '&start_term_pos=1&keywords=' . $heading . '&field=' . $field . '&page=' . $previous_page;
      print '<a href="' . $backward_scan_retrieve_url  . '">Backward</a> ';
   }
   // We can use $heading as the parameter for the Next link because it's the last item in the foreach loop
   $forward_scan_retrieve_url = $self . '?opac_function=Browse' . '&start_term_pos=1&keywords=' . $heading . '&field=' . $field . '&page=' . $next_page;
   print '<a href="' . $forward_scan_retrieve_url  . '">Forward</a></div>';
  } else {
   print "Scan failed. Error: " . yaz_error($id) . "<br />";
  }
}

/*
Generates "next", "previous", etc. links in find queries
*/
function opac_scan_pager() {
   // Not currently used
}

/*
Apply external stylesheet to group of records in XML. 
*/
function records2page($xmldata, $start_list_at) {
  $xml = new DOMDocument;
  $xml->loadXML($xmldata);

  $xsl = new DOMDocument;
  $xsl->load('opac.xsl');

  $proc = new XSLTProcessor;
  $proc->registerPHPFunctions();
  $proc->importStyleSheet($xsl); 

  $proc->setParameter('', 'start_list_at', $start_list_at);
  return $proc->transformToXML($xml);
}

/*
XSL extension to pick ISBNs out of 020 elements. XSL has substring-before() 
but that's about it.
*/
function opac_clean_isbn ($isbn) {
  $isbn = preg_replace('/\s.+$/', '', $isbn);
  return $isbn;
}

/*
XSL extension to format headings in records so they become hyperlinks to searches.
*/
function opac_format_heading_links ($field, $heading) {
  global $self;
  $heading_query_string = trim($heading);
  $heading_query_string = preg_replace('/\W+/', '+', $heading_query_string);
  $heading_query_string = trim($heading_query_string, '+');
  $heading_query_string = $self . '?opac_function=Find&keywords=' . $heading_query_string . '&field=' . $field;
  return $heading_query_string; 
}


/*
XSL extension to format tables of contents (MARC 505)
*/
function opac_format_toc ($toc) {
  $toc = preg_replace('/\-\-/', '<br />', $toc);
  return $toc; 
}

/*
Log $content to a file
*/
function write_log ($content) {
  global $log_file, $enable_log;
  if ($enable_log) {
     $handle = fopen($log_file, 'a');
     $timestamp = date("Y-m-d H:i:s");
     $startstamp = "\n" . 'START ' . $timestamp . "\n";
     $endstamp = "\n" . 'END ' . $timestamp . "\n";
     fwrite($handle, $startstamp); 
     fwrite($handle, $content); 
     fwrite($handle, $endstamp); 
     fclose($handle);
  }
}

/**
 * Encodes HTML safely for UTF-8. Use instead of htmlentities.
 *
 * @param string $var
 * @return string
 */
function html_encode($text) {
	// return htmlentities($var, ENT_QUOTES, 'UTF-8') ;
	return mb_convert_encoding($text, 'HTML-ENTITIES', "UTF-8");
}

?> 
